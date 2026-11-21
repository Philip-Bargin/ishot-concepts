<?php
namespace IShot\Booking\Persistence;

use wpdb;

/**
 * Manage-booking links issued in emails. Each email gets its own random token; only its
 * SHA-256 hash is stored, so neither the database nor the outbox can reveal a working link.
 * Earlier emails' links keep working (they expire with the booking, see ClientBookings).
 */
final class ManageLinks {

	private readonly wpdb $db;

	public function __construct( ?wpdb $db = null ) {
		global $wpdb;
		$this->db = $db ?? $wpdb;
	}

	/** @return array{token: string, hash: string} The raw token goes into the email and nowhere else. */
	public function issue( int $booking_id ): array {
		$token = bin2hex( random_bytes( 32 ) );
		$hash  = hash( 'sha256', $token );
		$this->db->insert(
			Schema::links(),
			array(
				'token_hash' => $hash,
				'booking_id' => $booking_id,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		return array(
			'token' => $token,
			'hash'  => $hash,
		);
	}

	/** Withdraws a link whose email was never delivered. */
	public function revoke( string $hash ): void {
		$this->db->delete( Schema::links(), array( 'token_hash' => $hash ) );
	}

	public function bookingFor( string $hash ): ?int {
		$id = $this->db->get_var(
			$this->db->prepare( 'SELECT booking_id FROM ' . Schema::links() . ' WHERE token_hash = %s', $hash ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		return null === $id ? null : (int) $id;
	}
}
