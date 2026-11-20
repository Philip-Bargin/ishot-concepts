<?php
namespace IShot\Booking\Privacy;

use IShot\Booking\Persistence\Schema;
use wpdb;

/**
 * Removes a client's personal data from one booking while keeping the studio's records whole:
 * the time, service, status and deposit amount stay so reports and the audit trail still add up.
 *
 * Wiped: name, email, phone, location, notes, account link, deposit reference, the public token,
 * every manage-booking link, the email snapshots in the outbox, and free text (notes, reasons)
 * in the audit log. Idempotent: an anonymised booking is skipped.
 */
final class Anonymiser {

	public const EVENT = 'anonymised';

	private readonly wpdb $db;

	public function __construct( ?wpdb $db = null ) {
		global $wpdb;
		$this->db = $db ?? $wpdb;
	}

	/** @return bool False when the booking does not exist, is a staff block, or was already anonymised. */
	public function anonymise( int $booking_id, string $reason, ?int $actor_user_id = null ): bool {
		$now = gmdate( 'Y-m-d H:i:s' );
		$this->db->query( 'START TRANSACTION' );
		try {
			$row = $this->db->get_row(
				$this->db->prepare( 'SELECT kind, anonymised_at FROM ' . Schema::bookings() . ' WHERE id = %d FOR UPDATE', $booking_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
			if ( null === $row || 'booking' !== $row['kind'] || null !== $row['anonymised_at'] ) {
				$this->db->query( 'COMMIT' );
				return false;
			}

			$this->db->query(
				$this->db->prepare(
					'UPDATE ' . Schema::bookings() . " SET client_name = %s, client_email = %s, client_phone = '', location = '', notes = NULL,
						client_user_id = NULL, deposit_reference = '', public_token_hash = NULL, idempotency_key = NULL,
						anonymised_at = %s, updated_at = %s, version = version + 1
					WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					wp_privacy_anonymize_data( 'text' ),
					wp_privacy_anonymize_data( 'email' ),
					$now,
					$now,
					$booking_id
				)
			);
			$this->db->delete( Schema::notifications(), array( 'booking_id' => $booking_id ) );
			$this->db->delete( Schema::links(), array( 'booking_id' => $booking_id ) );
			$this->scrubEvents( $booking_id );
			$this->db->insert(
				Schema::events(),
				array(
					'booking_id'    => $booking_id,
					'actor_user_id' => $actor_user_id,
					'action'        => self::EVENT,
					'payload'       => wp_json_encode( array( 'why' => $reason ) ),
					'created_at'    => $now,
				)
			);
			$this->db->query( 'COMMIT' );
			return true;
		} catch ( \Throwable $e ) {
			$this->db->query( 'ROLLBACK' );
			throw $e;
		}
	}

	/** Drops the free-text fields staff or clients typed; keeps the structured history. */
	private function scrubEvents( int $booking_id ): void {
		$events = $this->db->get_results(
			$this->db->prepare( 'SELECT id, payload FROM ' . Schema::events() . ' WHERE booking_id = %d AND payload IS NOT NULL', $booking_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		foreach ( $events as $event ) {
			$payload = json_decode( (string) $event['payload'], true );
			if ( ! is_array( $payload ) ) {
				continue;
			}
			$kept = array_diff_key( $payload, array_flip( array( 'note', 'reason' ) ) );
			if ( $kept !== $payload ) {
				$this->db->update( Schema::events(), array( 'payload' => array() === $kept ? null : wp_json_encode( $kept ) ), array( 'id' => (int) $event['id'] ) );
			}
		}
	}
}
