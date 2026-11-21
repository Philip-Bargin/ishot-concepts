<?php
namespace IShot\Booking\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Calendar\Ics;
use IShot\Booking\Calendar\IcsEvent;
use PHPUnit\Framework\TestCase;

/** RFC 5545 output (and the RFC 5546 methods the emails use). */
final class IcsTest extends TestCase {

	private static function utc( string $utc ): DateTimeImmutable {
		return new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) );
	}

	private static function event( array $o = array() ): IcsEvent {
		return new IcsEvent(
			uid: $o['uid'] ?? 'booking-7@ishot.example',
			sequence: $o['sequence'] ?? 3,
			start: self::utc( '2026-12-14 10:00:00' ),
			end: self::utc( '2026-12-14 11:00:00' ),
			summary: $o['summary'] ?? 'Portrait session',
			location: $o['location'] ?? 'Studio',
			description: $o['description'] ?? '',
			organizer_email: 'studio@ishot.example',
			organizer_name: 'iShot Concept',
			attendee_email: array_key_exists( 'attendee', $o ) ? $o['attendee'] : 'ada@example.com',
			attendee_name: 'Ada Test',
			status: $o['status'] ?? IcsEvent::CONFIRMED
		);
	}

	/** @return string[] Unfolded content lines. */
	private static function lines( string $ics ): array {
		return explode( "\r\n", rtrim( preg_replace( "/\r\n[ \t]/", '', $ics ), "\r\n" ) );
	}

	public function test_a_request_is_a_complete_crlf_calendar_with_one_event(): void {
		$ics = Ics::calendar( Ics::REQUEST, array( self::event() ), self::utc( '2026-12-01 08:30:00' ) );

		$this->assertStringEndsWith( "END:VCALENDAR\r\n", $ics );
		$this->assertDoesNotMatchRegularExpression( "/(?<!\r)\n/", $ics, 'every line ends in CRLF' );
		$lines = self::lines( $ics );
		$this->assertSame( 'BEGIN:VCALENDAR', $lines[0] );
		foreach ( array( 'VERSION:2.0', 'METHOD:REQUEST', 'BEGIN:VEVENT', 'END:VEVENT', 'UID:booking-7@ishot.example', 'SEQUENCE:3', 'DTSTAMP:20261201T083000Z', 'DTSTART:20261214T100000Z', 'DTEND:20261214T110000Z', 'STATUS:CONFIRMED', 'SUMMARY:Portrait session' ) as $expected ) {
			$this->assertContains( $expected, $lines );
		}
		$this->assertMatchesRegularExpression( '/^PRODID:.+/m', $ics );
		$this->assertContains( 'ORGANIZER;CN=iShot Concept:mailto:studio@ishot.example', $lines );
		$this->assertContains( 'ATTENDEE;CN=Ada Test;ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED:mailto:ada@example.com', $lines );
	}

	public function test_a_cancellation_keeps_the_uid_and_marks_the_event_cancelled(): void {
		$lines = self::lines( Ics::calendar( Ics::CANCEL, array( self::event( array( 'sequence' => 5, 'status' => IcsEvent::CANCELLED ) ) ), self::utc( '2026-12-02 09:00:00' ) ) );

		$this->assertContains( 'METHOD:CANCEL', $lines );
		$this->assertContains( 'UID:booking-7@ishot.example', $lines );
		$this->assertContains( 'SEQUENCE:5', $lines );
		$this->assertContains( 'STATUS:CANCELLED', $lines );
	}

	public function test_text_values_are_escaped(): void {
		$lines = self::lines(
			Ics::calendar(
				Ics::REQUEST,
				array( self::event( array( 'summary' => 'Family, kids; pets \\ more', 'description' => "Line one\nLine two" ) ) ),
				self::utc( '2026-12-01 08:30:00' )
			)
		);
		$this->assertContains( 'SUMMARY:Family\\, kids\\; pets \\\\ more', $lines );
		$this->assertContains( 'DESCRIPTION:Line one\\nLine two', $lines );
	}

	public function test_long_lines_are_folded_at_75_octets_without_splitting_a_character(): void {
		$summary = str_repeat( 'Séance photo ', 20 );
		$ics     = Ics::calendar( Ics::REQUEST, array( self::event( array( 'summary' => $summary ) ) ), self::utc( '2026-12-01 08:30:00' ) );

		foreach ( explode( "\r\n", $ics ) as $physical ) {
			$this->assertLessThanOrEqual( 75, strlen( $physical ), "line too long: {$physical}" );
			$this->assertTrue( mb_check_encoding( $physical, 'UTF-8' ), 'a fold split a multibyte character' );
		}
		$this->assertContains( 'SUMMARY:' . $summary, self::lines( $ics ) );
	}

	public function test_a_published_feed_has_no_attendees_and_may_hold_many_events(): void {
		$lines = self::lines(
			Ics::calendar(
				Ics::PUBLISH,
				array( self::event( array( 'attendee' => null ) ), self::event( array( 'uid' => 'booking-8@ishot.example', 'attendee' => null, 'status' => IcsEvent::TENTATIVE ) ) ),
				self::utc( '2026-12-01 08:30:00' )
			)
		);
		$this->assertContains( 'METHOD:PUBLISH', $lines );
		$this->assertCount( 2, array_keys( $lines, 'BEGIN:VEVENT', true ) );
		$this->assertContains( 'STATUS:TENTATIVE', $lines );
		$this->assertEmpty( preg_grep( '/^ATTENDEE/', $lines ) );
	}
}
