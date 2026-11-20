<?php
namespace IShot\Booking\Notifications;

use DateTimeImmutable;
use IShot\Booking\Persistence\Schema;
use wpdb;

/**
 * Time-based emails, queued by the five-minute tick. Each is keyed to the booking time it is
 * about (session start or hold expiry), so it is queued exactly once per time: a moved session
 * earns a fresh reminder, a repeated tick never adds a second one.
 */
final class Reminders {

	/** Session reminder: this long before the start. */
	public const SESSION_LEAD = DAY_IN_SECONDS;
	/** Deposit reminder: this long before an unpaid hold lapses... */
	public const DEPOSIT_LEAD = DAY_IN_SECONDS;
	/** ...but not when the deposit was asked for less than this long ago. */
	public const DEPOSIT_QUIET = 12 * HOUR_IN_SECONDS;
	/** Studio warning: this long before an unanswered request lapses. */
	public const STUDIO_LEAD = 12 * HOUR_IN_SECONDS;

	private const DATETIME = 'Y-m-d H:i:s';

	private readonly wpdb $db;
	private readonly Outbox $outbox;

	public function __construct( ?wpdb $db = null ) {
		global $wpdb;
		$this->db     = $db ?? $wpdb;
		$this->outbox = new Outbox( $this->db );
	}

	/** @return int How many emails were queued. */
	public function queueDue( DateTimeImmutable $now ): int {
		$ts = $now->getTimestamp();
		$at = static fn( int $offset ) => gmdate( self::DATETIME, $ts + $offset );

		$queued = 0;
		foreach ( $this->due( "status = 'confirmed' AND start_utc > %s AND start_utc <= %s", array( $at( 0 ), $at( self::SESSION_LEAD ) ) ) as $b ) {
			$queued += (int) $this->outbox->queueTimed( (int) $b['id'], 'reminder', "reminder:{$b['id']}:{$b['start_utc']}" );
		}
		foreach ( $this->due( "status = 'pending_deposit' AND hold_expires_at > %s AND hold_expires_at <= %s AND updated_at <= %s", array( $at( 0 ), $at( self::DEPOSIT_LEAD ), $at( -self::DEPOSIT_QUIET ) ) ) as $b ) {
			$queued += (int) $this->outbox->queueTimed( (int) $b['id'], 'deposit_reminder', "deposit_reminder:{$b['id']}:{$b['hold_expires_at']}" );
		}
		foreach ( $this->due( "status = 'requested' AND hold_expires_at > %s AND hold_expires_at <= %s", array( $at( 0 ), $at( self::STUDIO_LEAD ) ) ) as $b ) {
			$queued += (int) $this->outbox->queueTimed( (int) $b['id'], 'hold_expiring', "hold_expiring:{$b['id']}:{$b['hold_expires_at']}" );
		}
		if ( $queued > 0 ) {
			do_action( Outbox::QUEUED_HOOK ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the constant is 'ishot_booking_notifications_queued'.
		}
		return $queued;
	}

	/** @return array<int, array<string, string>> */
	private function due( string $where, array $args ): array {
		return $this->db->get_results(
			$this->db->prepare( 'SELECT id, start_utc, hold_expires_at FROM ' . Schema::bookings() . " WHERE kind = 'booking' AND {$where} LIMIT 200", ...$args ), // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);
	}
}
