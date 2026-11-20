<?php
namespace IShot\Booking\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\Availability;
use IShot\Booking\Domain\BookingPolicy;
use IShot\Booking\Domain\OpeningHours;
use PHPUnit\Framework\TestCase;

/**
 * Free start times for a service on one studio day: inside opening hours, allowed by policy,
 * and whose buffered range touches no locked unit. Studio is Africa/Monrovia (= UTC).
 */
final class AvailabilityTest extends TestCase {

	private Availability $availability;

	protected function setUp(): void {
		$hours = new OpeningHours(
			array( 1 => array( '09:00', '12:00' ) ), // Mondays only, short for readable fixtures
			new DateTimeZone( 'Africa/Monrovia' )
		);
		$policy             = new BookingPolicy( lead_time_hours: 48, horizon_days: 180, granularity_minutes: 30, hours: $hours );
		$this->availability = new Availability( $policy );
	}

	private static function now( string $at = '2026-12-01 12:00' ): DateTimeImmutable {
		return new DateTimeImmutable( $at, new DateTimeZone( 'UTC' ) );
	}

	public function test_open_day_with_no_bookings_offers_every_fitting_start(): void {
		$starts = $this->availability->startTimes( '2026-12-07', 60, 0, 0, array(), self::now() );

		$this->assertSame( array( '09:00', '09:30', '10:00', '10:30', '11:00' ), $starts );
	}

	public function test_locked_unit_removes_every_start_that_would_touch_it(): void {
		$starts = $this->availability->startTimes( '2026-12-07', 60, 0, 0, array( '2026-12-07 10:00:00' ), self::now() );

		// 09:30 (09:30–10:30) and 10:00 (10:00–11:00) both need the 10:00 unit.
		$this->assertSame( array( '09:00', '10:30', '11:00' ), $starts );
	}

	public function test_buffers_widen_the_range_that_must_be_free(): void {
		// 30 min buffer after: 09:00 blocks 09:00–10:30 and hits the 10:00 lock (so do 09:30, 10:00).
		// Without the buffer 09:00 would be free, so this proves buffers are applied.
		// 11:00 blocks 11:00–12:30: a pack-up buffer may run past closing.
		$starts = $this->availability->startTimes( '2026-12-07', 60, 0, 30, array( '2026-12-07 10:00:00' ), self::now() );

		$this->assertSame( array( '10:30', '11:00' ), $starts );
	}

	public function test_closed_day_has_no_starts(): void {
		$this->assertSame( array(), $this->availability->startTimes( '2026-12-08', 60, 0, 0, array(), self::now() ) );
	}

	public function test_starts_inside_the_lead_time_are_hidden(): void {
		// Now is Sat 10:00, so Monday starts before 10:00 are under 48 hours away.
		$starts = $this->availability->startTimes( '2026-12-07', 60, 0, 0, array(), self::now( '2026-12-05 10:00' ) );

		$this->assertSame( array( '10:00', '10:30', '11:00' ), $starts );
	}

	public function test_service_longer_than_the_day_has_no_starts(): void {
		$this->assertSame( array(), $this->availability->startTimes( '2026-12-07', 240, 0, 0, array(), self::now() ) );
	}
}
