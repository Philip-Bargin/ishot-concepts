<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Notifications\Outbox;
use IShot\Booking\Notifications\Reminders;
use IShot\Booking\Persistence\Schema;
use WP_UnitTestCase;

/** Time-based emails: queued once per booking time, never for the wrong status. */
final class RemindersTest extends WP_UnitTestCase {

	use BookingFixtures;

	public function set_up(): void {
		parent::set_up();
		$this->resetBookings();
	}

	private static function utc( string $utc ): DateTimeImmutable {
		return new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) );
	}

	/** @return string[] */
	private function timed( int $id ): array {
		return array_values( array_intersect( array_column( ( new Outbox() )->forBooking( $id ), 'template' ), array( 'reminder', 'deposit_reminder', 'hold_expiring' ) ) );
	}

	private function backdate( int $id, string $updated_at ): void {
		global $wpdb;
		$wpdb->update( Schema::bookings(), array( 'updated_at' => $updated_at ), array( 'id' => $id ) );
	}

	public function test_a_confirmed_session_gets_one_reminder_in_the_day_before(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1, 1 );
		$reminders = new Reminders();

		$this->assertSame( 0, $reminders->queueDue( self::utc( '2026-12-13 09:00:00' ) ), 'too early' );
		$this->assertSame( 1, $reminders->queueDue( self::utc( '2026-12-13 10:30:00' ) ) );
		$this->assertSame( 0, $reminders->queueDue( self::utc( '2026-12-13 11:00:00' ) ), 'never twice' );
		$this->assertSame( array( 'reminder' ), $this->timed( $id ) );
	}

	public function test_a_moved_session_gets_a_reminder_for_its_new_time(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1, 1 );
		( new Reminders() )->queueDue( self::utc( '2026-12-13 10:30:00' ) );
		$this->repo->reschedule( $id, self::utc( '2026-12-20 10:00:00' ), 2, 1 );

		( new Reminders() )->queueDue( self::utc( '2026-12-19 11:00:00' ) );
		$this->assertSame( array( 'reminder', 'reminder' ), $this->timed( $id ) );
	}

	public function test_requests_cancelled_and_past_sessions_get_no_session_reminder(): void {
		$requested = $this->bookingAt( '2026-12-14 10:00:00' );
		$cancelled = $this->bookingAt( '2026-12-14 12:00:00' );
		$this->repo->transition( $cancelled, BookingStatus::Confirmed, 1, 1 );
		$this->repo->transition( $cancelled, BookingStatus::Cancelled, 2, 1 );
		$past = $this->bookingAt( '2026-12-13 08:00:00' );
		$this->repo->transition( $past, BookingStatus::Confirmed, 1, 1 );

		( new Reminders() )->queueDue( self::utc( '2026-12-13 10:30:00' ) );
		$this->assertSame( array(), array_merge( $this->timed( $requested ), $this->timed( $cancelled ), $this->timed( $past ) ) );
	}

	public function test_an_unpaid_deposit_gets_one_reminder_before_the_hold_lapses(): void {
		$id = $this->bookingAt( '2026-12-20 10:00:00', array( 'hold' => '2026-12-05 08:00:00' ) );
		$this->repo->transition( $id, BookingStatus::PendingDeposit, 1, 1, array( 'deposit_status' => 'awaiting' ) );
		$this->backdate( $id, '2026-12-03 08:00:00' );

		$this->assertSame( 0, ( new Reminders() )->queueDue( self::utc( '2026-12-03 09:00:00' ) ), 'more than a day left' );
		( new Reminders() )->queueDue( self::utc( '2026-12-04 09:00:00' ) );
		( new Reminders() )->queueDue( self::utc( '2026-12-04 10:00:00' ) );
		$this->assertSame( array( 'deposit_reminder' ), $this->timed( $id ) );
	}

	public function test_no_deposit_reminder_right_after_the_deposit_was_asked_for(): void {
		$id = $this->bookingAt( '2026-12-20 10:00:00', array( 'hold' => '2026-12-05 08:00:00' ) );
		$this->repo->transition( $id, BookingStatus::PendingDeposit, 1, 1, array( 'deposit_status' => 'awaiting' ) );
		$this->backdate( $id, '2026-12-04 09:00:00' );

		( new Reminders() )->queueDue( self::utc( '2026-12-04 10:00:00' ) );
		$this->assertSame( array(), $this->timed( $id ), 'the instructions went out an hour ago' );
	}

	public function test_the_studio_is_warned_before_an_unanswered_request_lapses(): void {
		$id = $this->bookingAt( '2026-12-20 10:00:00', array( 'hold' => '2026-12-05 08:00:00' ) );

		( new Reminders() )->queueDue( self::utc( '2026-12-04 12:00:00' ) );
		$this->assertSame( array(), $this->timed( $id ), 'more than 12 hours left' );
		( new Reminders() )->queueDue( self::utc( '2026-12-04 21:00:00' ) );
		( new Reminders() )->queueDue( self::utc( '2026-12-04 22:00:00' ) );
		$this->assertSame( array( 'hold_expiring' ), $this->timed( $id ) );
		$this->assertSame( 'studio', array_column( ( new Outbox() )->forBooking( $id ), 'recipient', 'template' )['hold_expiring'] );
	}
}
