<?php
namespace IShot\Booking\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\SlotUnits;
use IShot\Booking\Domain\TimeRange;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * A booking occupies every granularity unit its buffered range touches. Two bookings conflict
 * exactly when they share a unit, which is what the database unique key enforces.
 */
final class SlotUnitsTest extends TestCase {

	private static function range( string $start, string $end ): TimeRange {
		$utc = new DateTimeZone( 'UTC' );
		return new TimeRange( new DateTimeImmutable( $start, $utc ), new DateTimeImmutable( $end, $utc ) );
	}

	public function test_aligned_range_covers_each_unit_once(): void {
		$units = SlotUnits::cover( self::range( '2026-12-14 10:00', '2026-12-14 11:00' ), 30 );

		$this->assertSame( array( '2026-12-14 10:00:00', '2026-12-14 10:30:00' ), $units );
	}

	public function test_unaligned_range_widens_to_whole_units(): void {
		// A 15-minute buffer either side still blocks the whole 30-minute units it touches.
		$units = SlotUnits::cover( self::range( '2026-12-14 09:45', '2026-12-14 11:15' ), 30 );

		$this->assertSame(
			array( '2026-12-14 09:30:00', '2026-12-14 10:00:00', '2026-12-14 10:30:00', '2026-12-14 11:00:00' ),
			$units
		);
	}

	public function test_back_to_back_bookings_share_no_unit(): void {
		$first  = SlotUnits::cover( self::range( '2026-12-14 10:00', '2026-12-14 11:00' ), 30 );
		$second = SlotUnits::cover( self::range( '2026-12-14 11:00', '2026-12-14 12:00' ), 30 );

		$this->assertSame( array(), array_values( array_intersect( $first, $second ) ) );
	}

	public function test_overlapping_bookings_share_a_unit(): void {
		$first  = SlotUnits::cover( self::range( '2026-12-14 10:00', '2026-12-14 11:00' ), 30 );
		$second = SlotUnits::cover( self::range( '2026-12-14 10:59', '2026-12-14 12:00' ), 30 );

		$this->assertSame( array( '2026-12-14 10:30:00' ), array_values( array_intersect( $first, $second ) ) );
	}

	public function test_range_crossing_midnight_covers_both_days(): void {
		$units = SlotUnits::cover( self::range( '2026-12-14 23:30', '2026-12-15 00:30' ), 30 );

		$this->assertSame( array( '2026-12-14 23:30:00', '2026-12-15 00:00:00' ), $units );
	}

	public function test_non_utc_input_is_normalised_to_utc(): void {
		$lagos = new DateTimeZone( 'Africa/Lagos' ); // UTC+1, so 11:00 local = 10:00 UTC.
		$range = new TimeRange( new DateTimeImmutable( '2026-12-14 11:00', $lagos ), new DateTimeImmutable( '2026-12-14 11:30', $lagos ) );

		$this->assertSame( array( '2026-12-14 10:00:00' ), SlotUnits::cover( $range, 30 ) );
	}

	public function test_empty_or_reversed_range_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		self::range( '2026-12-14 11:00', '2026-12-14 11:00' );
	}

	public function test_granularity_must_divide_an_hour(): void {
		$this->expectException( InvalidArgumentException::class );
		SlotUnits::cover( self::range( '2026-12-14 10:00', '2026-12-14 11:00' ), 25 );
	}
}
