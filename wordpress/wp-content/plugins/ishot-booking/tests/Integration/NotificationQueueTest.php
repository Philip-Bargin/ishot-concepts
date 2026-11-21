<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Notifications\Outbox;
use IShot\Booking\Notifications\Queue;
use IShot\Booking\Persistence\SlotConflict;
use WP_UnitTestCase;

/** Action Scheduler wiring: committed changes kick delivery; the tick retries and reminds. */
final class NotificationQueueTest extends WP_UnitTestCase {

	use BookingFixtures;

	public function set_up(): void {
		parent::set_up();
		$this->resetBookings();
		as_unschedule_all_actions( Queue::SEND_HOOK );
		as_unschedule_all_actions( Queue::TICK_HOOK );
		reset_phpmailer_instance();
		// The test site's default sender (wordpress@localhost) is not a valid address.
		\IShot\Booking\Settings::update( array( 'email_from_address' => 'bookings@ishot.example' ) );
	}

	private static function pending( string $hook ): int {
		return count( as_get_scheduled_actions( array( 'hook' => $hook, 'status' => \ActionScheduler_Store::STATUS_PENDING, 'per_page' => 50 ), 'ids' ) );
	}

	public function test_a_committed_booking_enqueues_one_delivery_job(): void {
		$this->bookingAt( '2026-12-14 10:00:00' );
		$this->bookingAt( '2026-12-15 10:00:00' );

		$this->assertSame( 1, self::pending( Queue::SEND_HOOK ), 'one unique job drains the whole outbox' );
	}

	public function test_a_rolled_back_booking_enqueues_nothing(): void {
		$this->bookingAt( '2026-12-14 10:00:00' );
		as_unschedule_all_actions( Queue::SEND_HOOK );

		try {
			$this->bookingAt( '2026-12-14 10:30:00' );
		} catch ( SlotConflict $e ) {
			// expected
		}
		$this->assertSame( 0, self::pending( Queue::SEND_HOOK ) );
	}

	public function test_the_recurring_tick_is_scheduled_once(): void {
		Queue::ensureScheduled();
		Queue::ensureScheduled();

		$this->assertSame( 1, self::pending( Queue::TICK_HOOK ) );
	}

	public function test_running_the_delivery_job_sends_the_queued_emails(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );

		do_action( Queue::SEND_HOOK );

		$this->assertCount( 2, tests_retrieve_phpmailer_instance()->mock_sent );
		$this->assertSame( array( 'sent', 'sent' ), array_column( ( new Outbox() )->forBooking( $id ), 'status' ) );
	}

	public function test_deactivation_removes_the_jobs(): void {
		Queue::ensureScheduled();
		$this->bookingAt( '2026-12-14 10:00:00' );

		Queue::unschedule();

		$this->assertSame( 0, self::pending( Queue::TICK_HOOK ) + self::pending( Queue::SEND_HOOK ) );
	}
}
