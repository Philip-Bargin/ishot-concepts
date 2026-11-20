<?php
namespace IShot\Booking\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\BookingPolicy;
use IShot\Booking\Domain\OpeningHours;
use IShot\Booking\Domain\PolicyViolation;
use IShot\Booking\Domain\TimeRange;
use PHPUnit\Framework\TestCase;

/**
 * Server-side booking rules. The studio clock is Africa/Monrovia (UTC+0, no DST), so local
 * wall-clock times in these fixtures equal UTC; one test uses a UTC+1 studio to prove the
 * opening-hours check happens in studio time, not UTC.
 */
final class BookingPolicyTest extends TestCase {

	private const NOW = '2026-12-01 12:00'; // Tuesday.

	private static function hours( string $tz = 'Africa/Monrovia' ): OpeningHours {
		return new OpeningHours(
			array(
				1 => array( '09:00', '18:00' ), // Mon
				2 => array( '09:00', '18:00' ),
				3 => array( '09:00', '18:00' ),
				4 => array( '09:00', '18:00' ),
				5 => array( '09:00', '18:00' ),
				6 => array( '10:00', '16:00' ), // Sat; Sunday (7) closed
			),
			new DateTimeZone( $tz ),
			array(
				'2026-12-25' => null,                      // closed for the holiday
				'2026-12-24' => array( '09:00', '13:00' ), // short day
			)
		);
	}

	private static function policy( ?OpeningHours $hours = null ): BookingPolicy {
		return new BookingPolicy( lead_time_hours: 48, horizon_days: 180, granularity_minutes: 30, hours: $hours ?? self::hours() );
	}

	private static function session( string $start, string $end, string $tz = 'Africa/Monrovia' ): TimeRange {
		$zone = new DateTimeZone( $tz );
		return new TimeRange( new DateTimeImmutable( $start, $zone ), new DateTimeImmutable( $end, $zone ) );
	}

	private static function now(): DateTimeImmutable {
		return new DateTimeImmutable( self::NOW, new DateTimeZone( 'UTC' ) );
	}

	/** @return array<string, array{string, string, ?PolicyViolation}> */
	public static function cases(): array {
		return array(
			'ordinary weekday session'          => array( '2026-12-07 10:00', '2026-12-07 11:00', null ),
			'exactly 48h ahead is allowed'      => array( '2026-12-03 12:00', '2026-12-03 13:00', null ),
			'one slot under 48h is too soon'    => array( '2026-12-03 11:30', '2026-12-03 12:30', PolicyViolation::TooSoon ),
			'in the past is too soon'           => array( '2026-11-30 10:00', '2026-11-30 11:00', PolicyViolation::TooSoon ),
			'months in the past is too soon'    => array( '2026-05-31 10:00', '2026-05-31 11:00', PolicyViolation::TooSoon ),
			'day 181 is too far'                => array( '2027-05-31 10:00', '2027-05-31 11:00', PolicyViolation::TooFar ),
			'start off the 30-minute grid'      => array( '2026-12-07 10:15', '2026-12-07 11:15', PolicyViolation::Misaligned ),
			'starts before opening'             => array( '2026-12-07 08:30', '2026-12-07 09:30', PolicyViolation::OutsideHours ),
			'ends exactly at closing is fine'   => array( '2026-12-07 17:00', '2026-12-07 18:00', null ),
			'runs past closing'                 => array( '2026-12-07 17:30', '2026-12-07 18:30', PolicyViolation::OutsideHours ),
			'Sunday is closed'                  => array( '2026-12-06 10:00', '2026-12-06 11:00', PolicyViolation::OutsideHours ),
			'Saturday uses Saturday hours'      => array( '2026-12-05 09:00', '2026-12-05 10:00', PolicyViolation::OutsideHours ),
			'holiday exception closes the day'  => array( '2026-12-25 10:00', '2026-12-25 11:00', PolicyViolation::OutsideHours ),
			'short-day exception ends at 13:00' => array( '2026-12-24 12:30', '2026-12-24 13:30', PolicyViolation::OutsideHours ),
			'short-day exception morning is ok' => array( '2026-12-24 09:00', '2026-12-24 10:00', null ),
		);
	}

	/** @dataProvider cases */
	public function test_session_is_checked_against_studio_rules( string $start, string $end, ?PolicyViolation $expected ): void {
		$this->assertSame( $expected, self::policy()->check( self::session( $start, $end ), self::now() ) );
	}

	public function test_opening_hours_are_read_in_the_studio_timezone(): void {
		// Studio in UTC+1: 08:30 UTC is 09:30 local, inside 09:00–18:00.
		$policy = self::policy( self::hours( 'Africa/Lagos' ) );
		$utc    = self::session( '2026-12-07 08:30', '2026-12-07 09:30', 'UTC' );

		$this->assertNull( $policy->check( $utc, self::now() ) );
	}

	public function test_violation_has_a_client_facing_message(): void {
		$this->assertSame(
			'That time is outside studio opening hours.',
			PolicyViolation::OutsideHours->message()
		);
	}
}
