<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Notifications\Outbox;
use IShot\Booking\Persistence\SlotConflict;
use IShot\Booking\Persistence\StaleBooking;
use WP_UnitTestCase;

/**
 * Notifications are written to the outbox inside the booking's own transaction, so only a
 * committed state change can ever produce an email.
 */
final class NotificationOutboxTest extends WP_UnitTestCase {

	use BookingFixtures;

	private int $kicks = 0;

	public function set_up(): void {
		parent::set_up();
		$this->resetBookings();
		$this->kicks = 0;
		add_action( Outbox::QUEUED_HOOK, array( $this, 'countKick' ) );
	}

	public function tear_down(): void {
		remove_action( Outbox::QUEUED_HOOK, array( $this, 'countKick' ) );
		parent::tear_down();
	}

	public function countKick(): void {
		++$this->kicks;
	}

	/** @return string[] Templates queued for a booking, in order. */
	private function queued( int $booking_id ): array {
		return array_column( ( new Outbox() )->forBooking( $booking_id ), 'template' );
	}

	public function test_a_new_request_queues_the_client_and_studio_emails(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );

		$this->assertSame( array( 'request_received', 'new_request' ), $this->queued( $id ) );
		$rows = ( new Outbox() )->forBooking( $id );
		$this->assertSame( array( 'pending', 'pending' ), array_column( $rows, 'status' ) );
		$this->assertSame( array( 'client', 'studio' ), array_column( $rows, 'recipient' ) );
		$this->assertSame( 1, $this->kicks, 'delivery is kicked once, after commit' );
	}

	public function test_the_snapshot_records_the_booking_as_it_was_without_secrets(): void {
		global $wpdb;
		$id = $this->bookingAt( '2026-12-14 10:00:00', array( 'email' => 'ada@example.com' ) );
		$wpdb->update( $wpdb->prefix . 'ishot_bookings', array( 'public_token_hash' => str_repeat( 'a', 64 ) ), array( 'id' => $id ) );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1 );

		$row      = array_values( array_filter( ( new Outbox() )->forBooking( $id ), static fn( $r ) => 'confirmed' === $r['template'] ) )[0];
		$snapshot = json_decode( $row['snapshot'], true );
		$this->assertSame( 'confirmed', $snapshot['status'] );
		$this->assertSame( 2, $snapshot['version'] );
		$this->assertSame( '2026-12-14 10:00:00', $snapshot['start_utc'] );
		$this->assertSame( 'ada@example.com', $snapshot['client_email'] );
		$this->assertSame( 'REQUEST', $row['calendar_method'] );
		$this->assertStringNotContainsString( str_repeat( 'a', 64 ), $row['snapshot'] );
		$this->assertArrayNotHasKey( 'public_token_hash', $snapshot );
		$this->assertArrayNotHasKey( 'client_phone', $snapshot );
	}

	public function test_a_rolled_back_booking_queues_nothing(): void {
		$first = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->kicks = 0;

		try {
			$this->bookingAt( '2026-12-14 10:30:00', array( 'email' => 'loser@example.com' ) );
			$this->fail( 'expected a slot conflict' );
		} catch ( SlotConflict $e ) {
			// expected
		}

		$this->assertSame( 2, ( new Outbox() )->count(), 'only the first booking has emails queued' );
		$this->assertSame( array( 'request_received', 'new_request' ), $this->queued( $first ) );
		$this->assertSame( 0, $this->kicks );
	}

	public function test_a_stale_status_change_queues_nothing(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1 );
		$before = ( new Outbox() )->count();

		try {
			$this->repo->transition( $id, BookingStatus::Cancelled, 1 );
			$this->fail( 'expected a stale booking' );
		} catch ( StaleBooking $e ) {
			// expected
		}
		$this->assertSame( $before, ( new Outbox() )->count() );
	}

	public function test_staff_time_blocks_never_notify_anyone(): void {
		$this->bookingAt( '2026-12-14 10:00:00', array( 'kind' => 'block', 'status' => BookingStatus::Confirmed ) );

		$this->assertSame( 0, ( new Outbox() )->count() );
		$this->assertSame( 0, $this->kicks );
	}

	public function test_a_dedupe_key_can_only_be_queued_once(): void {
		$id     = $this->bookingAt( '2026-12-14 10:00:00' );
		$outbox = new Outbox();
		$before = $outbox->count();

		$this->assertTrue( $outbox->queueTimed( $id, 'reminder', 'reminder:' . $id . ':2026-12-14 10:00:00' ) );
		$this->assertFalse( $outbox->queueTimed( $id, 'reminder', 'reminder:' . $id . ':2026-12-14 10:00:00' ) );
		$this->assertSame( $before + 1, $outbox->count() );
	}

	public function test_a_client_cancelling_through_their_link_also_tells_the_studio(): void {
		$id       = $this->bookingAt( '2026-12-14 10:00:00' );
		$policy   = \IShot\Booking\Settings::policy();
		$bookings = new \IShot\Booking\Application\ClientBookings( $this->repo, new \IShot\Booking\Application\BookingRequests( $this->repo, $policy, 48 ), $policy, 72 );

		$bookings->cancel( $this->repo->find( $id ), new \DateTimeImmutable( '2026-12-01 08:00:00', new \DateTimeZone( 'UTC' ) ) );

		$this->assertSame( array( 'request_received', 'new_request', 'cancelled', 'client_cancelled' ), $this->queued( $id ) );
	}

	public function test_a_lapsed_hold_tells_the_client_and_the_studio(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00', array( 'hold' => '2026-12-02 08:00:00' ) );

		( new \IShot\Booking\Application\HoldExpiry( $this->repo ) )->run( new \DateTimeImmutable( '2026-12-02 09:00:00', new \DateTimeZone( 'UTC' ) ) );

		$this->assertSame( array( 'request_received', 'new_request', 'hold_expired', 'hold_expired_studio' ), $this->queued( $id ) );
	}

	public function test_each_lifecycle_step_queues_its_own_emails(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->repo->transition( $id, BookingStatus::PendingDeposit, 1, 1, array( 'deposit_status' => 'awaiting' ) );
		$this->repo->transition( $id, BookingStatus::Confirmed, 2, 1, array( 'deposit_status' => 'received' ) );
		$this->repo->reschedule( $id, new \DateTimeImmutable( '2026-12-15 10:00:00', new \DateTimeZone( 'UTC' ) ), 3, 1 );
		$this->repo->transition( $id, BookingStatus::Cancelled, 4, 1, array(), array( 'by' => 'studio' ) );

		$this->assertSame(
			array( 'request_received', 'new_request', 'deposit_requested', 'confirmed', 'rescheduled', 'cancelled' ),
			$this->queued( $id )
		);
		$calendar = array_column( ( new Outbox() )->forBooking( $id ), 'calendar_method', 'template' );
		$this->assertSame( array( null, null, null, 'REQUEST', 'REQUEST', 'CANCEL' ), array_values( $calendar ) );
	}
}
