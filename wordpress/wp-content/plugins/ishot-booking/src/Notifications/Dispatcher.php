<?php
namespace IShot\Booking\Notifications;

use DateTimeImmutable;
use IShot\Booking\Persistence\ManageLinks;
use IShot\Booking\Persistence\Schema;
use Throwable;
use wpdb;

/**
 * Delivers due outbox rows.
 *
 * - Exactly one worker sends a row: it is claimed with a conditional UPDATE (pending → sending),
 *   and only the worker whose UPDATE changed the row sends it.
 * - A failed send is retried with growing back-off; after MAX_ATTEMPTS it is marked failed and
 *   recorded in the booking's audit log. Stored errors have email addresses redacted.
 * - Emails to the same person about the same booking go out in order: a later one waits while
 *   an earlier one is still pending, so a client never reads "confirmed" before "received".
 * - A worker that dies mid-send leaves its claim behind; recoverStale() requeues it after
 *   STALE_AFTER seconds. That one case is at-least-once: the email may already have gone.
 */
final class Dispatcher {

	public const MAX_ATTEMPTS = 5;

	/** Seconds to wait after the Nth failed attempt. */
	private const BACKOFF = array(
		1 => 5 * MINUTE_IN_SECONDS,
		2 => 15 * MINUTE_IN_SECONDS,
		3 => HOUR_IN_SECONDS,
		4 => 4 * HOUR_IN_SECONDS,
	);

	public const STALE_AFTER = 15 * MINUTE_IN_SECONDS;

	private const DATETIME = 'Y-m-d H:i:s';

	private readonly wpdb $db;

	public function __construct(
		?wpdb $db = null,
		private readonly Mailer $mailer = new Mailer(),
		private readonly ?Renderer $renderer = null
	) {
		global $wpdb;
		$this->db = $db ?? $wpdb;
	}

	/** @return int How many emails were sent. */
	public function sendDue( DateTimeImmutable $now, int $limit = 50 ): int {
		$now_sql = gmdate( self::DATETIME, $now->getTimestamp() );
		$open    = $this->db->get_results(
			'SELECT id, booking_id, recipient, status, next_attempt_at FROM ' . Schema::notifications() . " WHERE status IN ('pending', 'sending') ORDER BY id LIMIT 500", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$sent    = 0;
		$waiting = array(); // booking|recipient pairs with an earlier email still unsettled
		foreach ( $open as $row ) {
			$key = $row['booking_id'] . '|' . $row['recipient'];
			if ( isset( $waiting[ $key ] ) ) {
				continue;
			}
			if ( 'sending' === $row['status'] || $row['next_attempt_at'] > $now_sql || $sent >= $limit ) {
				$waiting[ $key ] = true;
				continue;
			}
			if ( ! $this->claim( (int) $row['id'], $now_sql ) ) {
				$waiting[ $key ] = true; // another worker has it
				continue;
			}
			$outcome = $this->deliver( (int) $row['id'], $now );
			if ( 'sent' === $outcome ) {
				++$sent;
			} elseif ( 'retry' === $outcome ) {
				$waiting[ $key ] = true;
			}
		}
		return $sent;
	}

	/** Requeues claims abandoned by a worker that died mid-send. @return int Rows requeued. */
	public function recoverStale( DateTimeImmutable $now ): int {
		return (int) $this->db->query(
			$this->db->prepare(
				'UPDATE ' . Schema::notifications() . " SET status = 'pending', claimed_at = NULL, updated_at = %s WHERE status = 'sending' AND claimed_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				gmdate( self::DATETIME, $now->getTimestamp() ),
				gmdate( self::DATETIME, $now->getTimestamp() - self::STALE_AFTER )
			)
		);
	}

	private function claim( int $id, string $now_sql ): bool {
		return 1 === (int) $this->db->query(
			$this->db->prepare(
				'UPDATE ' . Schema::notifications() . " SET status = 'sending', claimed_at = %s, attempts = attempts + 1, updated_at = %s WHERE id = %d AND status = 'pending' AND next_attempt_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$now_sql,
				$now_sql,
				$id,
				$now_sql
			)
		);
	}

	/** @return string 'sent' | 'skipped' | 'retry' | 'failed' */
	private function deliver( int $id, DateTimeImmutable $now ): string {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM ' . Schema::notifications() . ' WHERE id = %d', $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		if ( ! isset( Rules::TEMPLATES[ $row['template'] ] ) || ! Templates::get( $row['template'] )['enabled'] ) {
			$this->settle( $id, 'skipped', $now );
			return 'skipped';
		}

		$message = null;
		try {
			$message = ( $this->renderer ?? new Renderer() )->render( $row, $now );
			$error   = is_email( $message->to ) ? $this->mailer->send( $message ) : 'No valid recipient address.';
		} catch ( Throwable $e ) {
			$error = 'Could not build the email: ' . $e->getMessage();
		}

		if ( null === $error ) {
			$this->settle( $id, 'sent', $now );
			return 'sent';
		}

		if ( null !== $message?->link_hash ) {
			( new ManageLinks( $this->db ) )->revoke( $message->link_hash ); // that link was never delivered
		}
		$attempts = (int) $row['attempts'];
		$error    = self::redact( $error );
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$this->settle( $id, 'failed', $now, $error );
			$this->auditFailure( $row, $attempts, $error, $now );
			return 'failed';
		}
		$this->db->update(
			Schema::notifications(),
			array(
				'status'          => 'pending',
				'claimed_at'      => null,
				'last_error'      => $error,
				'next_attempt_at' => gmdate( self::DATETIME, $now->getTimestamp() + self::BACKOFF[ $attempts ] ),
				'updated_at'      => gmdate( self::DATETIME, $now->getTimestamp() ),
			),
			array( 'id' => $id )
		);
		return 'retry';
	}

	private function settle( int $id, string $status, DateTimeImmutable $now, string $error = '' ): void {
		$at = gmdate( self::DATETIME, $now->getTimestamp() );
		$this->db->update(
			Schema::notifications(),
			array(
				'status'     => $status,
				'claimed_at' => null,
				'last_error' => $error,
				'sent_at'    => 'sent' === $status ? $at : null,
				'updated_at' => $at,
			),
			array( 'id' => $id )
		);
	}

	/** Delivery failures belong in the booking's audit trail, where staff look for what happened. */
	private function auditFailure( array $row, int $attempts, string $error, DateTimeImmutable $now ): void {
		$this->db->insert(
			Schema::events(),
			array(
				'booking_id' => (int) $row['booking_id'],
				'action'     => 'notification_failed',
				'payload'    => wp_json_encode(
					array(
						'template'  => $row['template'],
						'recipient' => $row['recipient'],
						'attempts'  => $attempts,
						'error'     => $error,
					)
				),
				'created_at' => gmdate( self::DATETIME, $now->getTimestamp() ),
			)
		);
	}

	/** Transport errors often quote addresses; keep personal data out of stored diagnostics. */
	public static function redact( string $error ): string {
		$error = (string) preg_replace( '/[^\s<>"\'(),;:]+@[^\s<>"\'(),;:]+/', '[address]', $error );
		$error = (string) preg_replace( '/[0-9a-f]{64}/i', '[token]', $error );
		return mb_substr( trim( $error ), 0, 250 );
	}
}
