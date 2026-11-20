<?php
namespace IShot\Booking\Notifications;

use IShot\Booking\Persistence\Schema;
use IShot\Booking\Persistence\TransientLockError;
use wpdb;

/**
 * The notification outbox (transactional outbox pattern).
 *
 * BookingRepository calls recordEvent() inside the same transaction that changes the booking,
 * so a rolled-back change leaves no email behind and a committed one always has its emails
 * queued. Each row's UNIQUE dedupe_key makes queuing the same email twice impossible.
 *
 * Rows hold a snapshot of the booking at the moment of the event: a retry hours later still
 * describes what happened then. The snapshot deliberately leaves out the token hash, phone
 * and notes; it keeps only what the emails print.
 */
final class Outbox {

	/** Fired after COMMIT when the transaction queued at least one email. Delivery listens. */
	public const QUEUED_HOOK = 'ishot_booking_notifications_queued';

	private const DATETIME = 'Y-m-d H:i:s';

	private readonly wpdb $db;

	public function __construct( ?wpdb $db = null ) {
		global $wpdb;
		$this->db = $db ?? $wpdb;
	}

	/**
	 * Queues the emails an audit event calls for. Must run inside the event's transaction.
	 *
	 * @param array<string, mixed> $payload
	 * @return int How many emails were queued.
	 */
	public function recordEvent( int $booking_id, int $event_id, string $action, ?string $from, ?string $to, ?int $actor_user_id, array $payload ): int {
		$booking = $this->booking( $booking_id );
		if ( null === $booking || 'booking' !== $booking['kind'] ) {
			return 0; // staff time blocks have no client
		}

		$extra  = 'rescheduled' === $action && isset( $payload['from'] ) ? array( 'previous_start_utc' => (string) $payload['from'] ) : array();
		$queued = 0;
		foreach ( Rules::forEvent( $action, $from, $to, $payload, null !== $actor_user_id, $booking['status'] ) as $planned ) {
			$queued += (int) $this->insert( $booking, $planned->template, "e{$event_id}:{$planned->template}", $planned->calendar, $event_id, $extra );
		}
		return $queued;
	}

	/** Queues a time-based email (reminders). False when that dedupe key was already queued. */
	public function queueTimed( int $booking_id, string $template, string $dedupe_key ): bool {
		$booking = $this->booking( $booking_id );
		return null !== $booking && $this->insert( $booking, $template, $dedupe_key, null, null, array() );
	}

	/** @return array<int, array<string, mixed>> In queue order. */
	public function forBooking( int $booking_id ): array {
		return $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM ' . Schema::notifications() . ' WHERE booking_id = %d ORDER BY id', $booking_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
	}

	public function count(): int {
		return (int) $this->db->get_var( 'SELECT COUNT(*) FROM ' . Schema::notifications() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** @return array<string, mixed>|null */
	private function booking( int $id ): ?array {
		return $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM ' . Schema::bookings() . ' WHERE id = %d', $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
	}

	/**
	 * What an email may print about a booking, frozen at the time of the event.
	 *
	 * @param array<string, mixed> $booking
	 * @return array<string, mixed>
	 */
	public static function snapshot( array $booking ): array {
		$service_id = null === $booking['service_id'] ? null : (int) $booking['service_id'];
		return array(
			'id'              => (int) $booking['id'],
			'status'          => (string) $booking['status'],
			'version'         => (int) $booking['version'],
			'service_id'      => $service_id,
			'service_title'   => null === $service_id ? '' : html_entity_decode( get_the_title( $service_id ), ENT_QUOTES, 'UTF-8' ),
			'start_utc'       => (string) $booking['start_utc'],
			'end_utc'         => (string) $booking['end_utc'],
			'client_name'     => (string) $booking['client_name'],
			'client_email'    => (string) $booking['client_email'],
			'location'        => (string) $booking['location'],
			'deposit_amount'  => null === $booking['deposit_amount'] ? null : (string) $booking['deposit_amount'],
			'deposit_status'  => (string) $booking['deposit_status'],
			'hold_expires_at' => $booking['hold_expires_at'],
		);
	}

	/** @param array<string, mixed> $booking */
	private function insert( array $booking, string $template, string $dedupe_key, ?string $calendar, ?int $event_id, array $extra ): bool {
		$now      = gmdate( self::DATETIME );
		$previous = $this->db->suppress_errors( true );
		try {
			$inserted = $this->db->insert(
				Schema::notifications(),
				array(
					'booking_id'      => (int) $booking['id'],
					'event_id'        => $event_id,
					'template'        => $template,
					'recipient'       => Rules::TEMPLATES[ $template ],
					'dedupe_key'      => $dedupe_key,
					'calendar_method' => $calendar,
					'snapshot'        => wp_json_encode( self::snapshot( $booking ) + $extra ),
					'status'          => 'pending',
					'next_attempt_at' => $now,
					'created_at'      => $now,
					'updated_at'      => $now,
				)
			);
		} finally {
			$this->db->suppress_errors( $previous );
		}
		$error = $this->db->last_error;
		if ( false === $inserted && ( str_contains( $error, 'Deadlock found' ) || str_contains( $error, 'Lock wait timeout' ) ) ) {
			throw new TransientLockError( $error ); // the repository re-runs the whole transaction
		}
		if ( false === $inserted && ! str_contains( $error, 'Duplicate entry' ) ) {
			// Anything but a duplicate must abort the booking change: an email must never be lost silently.
			throw new \RuntimeException( 'Could not queue a notification: ' . $this->db->last_error );
		}
		return false !== $inserted;
	}
}
