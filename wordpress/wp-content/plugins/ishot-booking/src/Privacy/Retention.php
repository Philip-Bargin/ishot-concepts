<?php
namespace IShot\Booking\Privacy;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Notifications\Queue;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Settings;

/**
 * Daily Action Scheduler job: anonymises client bookings whose session ended more than
 * Settings retention_months ago. 0 months turns it off.
 */
final class Retention {

	public const HOOK   = 'ishot_booking_retention';
	private const BATCH = 200;

	public static function ensureScheduled(): void {
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::HOOK, array(), Queue::GROUP ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK, array(), Queue::GROUP, true );
		}
	}

	public static function unschedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), Queue::GROUP );
		}
	}

	public static function run(): void {
		( new self() )->purge( new DateTimeImmutable( 'now' ) );
	}

	/** @return int How many bookings were anonymised. */
	public function purge( DateTimeImmutable $now ): int {
		global $wpdb;
		$months = Settings::int( 'retention_months' );
		if ( $months <= 0 ) {
			return 0;
		}
		$cutoff = $now->setTimezone( new DateTimeZone( 'UTC' ) )->modify( "-{$months} months" )->format( 'Y-m-d H:i:s' );
		$ids    = $wpdb->get_col(
			$wpdb->prepare( 'SELECT id FROM ' . Schema::bookings() . " WHERE anonymised_at IS NULL AND end_utc < %s AND kind = 'booking' ORDER BY id LIMIT %d", $cutoff, self::BATCH ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$anonymiser = new Anonymiser();
		$done       = 0;
		foreach ( $ids as $id ) {
			$done += (int) $anonymiser->anonymise( (int) $id, 'retention' );
		}
		if ( self::BATCH === count( $ids ) && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array(), Queue::GROUP, true ); // more to do: continue soon
		}
		return $done;
	}
}
