<?php
namespace IShot\Booking\Application;

use DateTimeImmutable;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\CalendarBusy;
use IShot\Booking\Persistence\StaleBooking;
use IShot\Booking\Settings;
use InvalidArgumentException;
use RuntimeException;

/**
 * Expires unpaid requests whose hold has lapsed, releasing their slots.
 *
 * Runs in two places so a lapsed hold never blocks anyone, whether or not cron fires:
 * lazily before every availability read and booking change, and as a five-minute sweep.
 */
final class HoldExpiry {

	public const HOOK     = 'ishot_booking_expire_holds';
	public const INTERVAL = 'ishot_five_minutes';

	public function __construct( private readonly BookingRepository $repo ) {}

	/** @return int How many bookings were expired. */
	public function run( DateTimeImmutable $now ): int {
		$expired = 0;
		foreach ( $this->repo->lapsedHolds( gmdate( 'Y-m-d H:i:s', $now->getTimestamp() ) ) as $hold ) {
			try {
				$this->repo->transition( (int) $hold['id'], BookingStatus::Expired, (int) $hold['version'], null, array(), array( 'reason' => 'hold_lapsed' ) );
				++$expired;
			} catch ( StaleBooking | InvalidArgumentException $e ) {
				// Staff or the client acted on it at the same moment; their change stands.
				continue;
			} catch ( CalendarBusy $e ) {
				// Lost its row lock under contention: the next sweep (or request) expires it.
				continue;
			} catch ( RuntimeException $e ) {
				// Any other failure (nothing was changed: the transaction rolled back) must not stop the
				// holds behind it, nor the public request this sweep runs inside. It is retried next run.
				do_action( 'ishot_booking_hold_expiry_failed', (int) $hold['id'], $e );
				continue;
			}
		}
		return $expired;
	}

	/** @param array<string, array{interval: int, display: string}> $schedules */
	public static function addInterval( array $schedules ): array {
		$schedules[ self::INTERVAL ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'ishot-booking' ),
		);
		return $schedules;
	}

	public static function ensureScheduled(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::INTERVAL, self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/** Cron callback: expires lapsed holds, then frees any lock row a failed change left behind. */
	public static function sweep(): void {
		global $wpdb;
		$repo = new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
		( new self( $repo ) )->run( new DateTimeImmutable( 'now' ) );
		try {
			$repo->releaseOrphanLocks();
		} catch ( RuntimeException $e ) {
			// Nothing was released (rolled back); the next sweep tries again.
			do_action( 'ishot_booking_hold_expiry_failed', 0, $e );
		}
	}
}
