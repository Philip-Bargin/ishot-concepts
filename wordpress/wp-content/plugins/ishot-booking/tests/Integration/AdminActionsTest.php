<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Admin\ActionHandler;
use IShot\Booking\Admin\BookingScreen;
use IShot\Booking\Domain\BookingStatus;
use PHPUnit\Framework\TestCase;

/**
 * wp-admin form posts. Every button goes through ActionHandler, which checks the nonce, the
 * capability for that action and the version the screen was rendered with.
 */
final class AdminActionsTest extends TestCase {

	use BookingFixtures;

	protected function setUp(): void {
		DeadlockingWpdb::settle();
		$this->resetBookings();
		$this->makeStaff();
	}

	protected function tearDown(): void {
		$this->removeStaff();
		DeadlockingWpdb::settle();
	}

	private static function post( int $id, string $action, int $version = 1, array $extra = array() ): array {
		return array_merge(
			array(
				'booking'        => (string) $id,
				'version'        => (string) $version,
				'booking_action' => $action,
				'_wpnonce'       => wp_create_nonce( ActionHandler::nonceAction( $id ) ),
			),
			$extra
		);
	}

	public function test_a_valid_post_performs_the_action(): void {
		$id = $this->bookingAt( '2026-12-14 10:00' );
		$this->actAs( 'ishot_photographer' );

		$result = ActionHandler::process( self::post( $id, 'confirm' ) );

		$this->assertSame( array( 'success', 'confirm' ), array( $result['type'], $result['code'] ) );
		$this->assertSame( 'confirmed', $this->repo->find( $id )['status'] );
	}

	public function test_a_missing_or_foreign_nonce_is_refused(): void {
		$id    = $this->bookingAt( '2026-12-14 10:00' );
		$other = $this->bookingAt( '2026-12-15 10:00' );
		$this->actAs( 'ishot_manager' );

		$this->assertSame( 'bad_nonce', ActionHandler::process( array( '_wpnonce' => '' ) + self::post( $id, 'cancel' ) )['code'] );
		$foreign = array( '_wpnonce' => wp_create_nonce( ActionHandler::nonceAction( $other ) ) ) + self::post( $id, 'cancel' );
		$this->assertSame( 'bad_nonce', ActionHandler::process( $foreign )['code'] );
		$this->assertSame( 'requested', $this->repo->find( $id )['status'] );
	}

	public function test_an_action_beyond_the_users_role_is_refused(): void {
		$id = $this->bookingAt( '2026-12-14 10:00' );
		$this->actAs( 'ishot_photographer' );

		$this->assertSame( 'forbidden', ActionHandler::process( self::post( $id, 'cancel' ) )['code'] );
		$this->assertSame( 'requested', $this->repo->find( $id )['status'] );
	}

	public function test_stale_clashing_and_unknown_cases_are_reported(): void {
		$id = $this->bookingAt( '2026-12-14 10:00' );
		$this->bookingAt( '2026-12-14 14:00' );
		$this->actAs( 'ishot_manager' );

		$this->assertSame( 'stale', ActionHandler::process( self::post( $id, 'confirm', 7 ) )['code'] );
		$this->assertSame( 'slot_taken', ActionHandler::process( self::post( $id, 'reschedule', 1, array( 'start' => '2026-12-14T13:30' ) ) )['code'] );
		$this->assertSame( 'invalid', ActionHandler::process( self::post( $id, 'reschedule', 1, array( 'start' => 'soon' ) ) )['code'] );
		$this->assertSame( 'invalid', ActionHandler::process( self::post( $id, 'complete' ) )['code'] );
		$this->assertSame( 'invalid', ActionHandler::process( self::post( $id, 'drop_table' ) )['code'] );
		$this->assertSame( 'not_found', ActionHandler::process( self::post( 999, 'confirm' ) )['code'] );
		$this->assertSame( '1', $this->repo->find( $id )['version'] );
	}

	public function test_deposit_flow_records_amount_and_reference(): void {
		$id = $this->bookingAt( '2026-12-14 10:00' );
		$this->actAs( 'ishot_manager' );

		ActionHandler::process( self::post( $id, 'request_deposit', 1, array( 'deposit_amount' => '75' ) ) );
		ActionHandler::process( self::post( $id, 'deposit_received', 2, array( 'note' => 'MoMo ref 991', 'reference' => '  MP2611  20.991 ' ) ) );

		$row = $this->repo->find( $id );
		$this->assertSame( array( 'confirmed', 'received', '75.00' ), array( $row['status'], $row['deposit_status'], $row['deposit_amount'] ) );
		$this->assertSame( array( 'manual', 'MP2611 20.991' ), array( $row['deposit_gateway'], $row['deposit_reference'] ) );
		$events = $this->repo->events( $id );
		$this->assertSame( array( 'note' => 'MoMo ref 991' ), json_decode( end( $events )['payload'], true ) );
	}

	public function test_a_change_that_keeps_losing_deadlocks_shows_busy_and_changes_nothing(): void {
		$id = $this->bookingAt( '2026-12-14 10:00' );
		$this->actAs( 'ishot_photographer' );

		DeadlockingWpdb::swapIn()->deadlock_row_locks = true;
		try {
			$result = ActionHandler::process( self::post( $id, 'confirm' ) );
		} finally {
			DeadlockingWpdb::restore();
		}

		$this->assertSame( array( 'error', 'busy' ), array( $result['type'], $result['code'] ) );
		$this->assertNotSame( '', ActionHandler::message( 'busy' ), 'the notice has words' );
		$this->assertSame( 'requested', $this->repo->find( $id )['status'] );
	}

	public function test_a_block_that_keeps_losing_deadlocks_shows_busy(): void {
		$this->actAs( 'ishot_photographer' );
		$block = array(
			'start'    => '2026-12-14T09:00',
			'end'      => '2026-12-14T11:00',
			'reason'   => 'Lens repair',
			'_wpnonce' => wp_create_nonce( ActionHandler::BLOCK_NONCE ),
		);

		DeadlockingWpdb::swapIn();
		try {
			$result = ActionHandler::processBlock( $block );
		} finally {
			DeadlockingWpdb::restore();
		}

		$this->assertSame( array( 'error', 'busy' ), array( $result['type'], $result['code'] ) );
		global $wpdb;
		$this->assertSame( '0', $wpdb->get_var( 'SELECT COUNT(*) FROM ' . \IShot\Booking\Persistence\Schema::bookings() ), 'no block row was saved' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function test_an_unblock_that_keeps_losing_deadlocks_shows_busy_and_keeps_the_block(): void {
		$this->actAs( 'ishot_photographer' );
		$block = array(
			'start'    => '2026-12-14T09:00',
			'end'      => '2026-12-14T11:00',
			'reason'   => 'Lens repair',
			'_wpnonce' => wp_create_nonce( ActionHandler::BLOCK_NONCE ),
		);
		$this->assertSame( 'blocked', ActionHandler::processBlock( $block )['code'] );

		DeadlockingWpdb::swapIn()->deadlock_row_locks = true;
		try {
			$result = ActionHandler::processUnblock( array( 'booking' => '1', '_wpnonce' => wp_create_nonce( ActionHandler::nonceAction( 1 ) ) ) );
		} finally {
			DeadlockingWpdb::restore();
		}

		$this->assertSame( array( 'error', 'busy' ), array( $result['type'], $result['code'] ) );
		$this->assertSame( 'confirmed', $this->repo->find( 1 )['status'] );
	}

	public function test_staff_block_and_unblock_time_from_the_calendar(): void {
		$this->actAs( 'ishot_photographer' );
		$block = array(
			'start'    => '2026-12-14T09:00',
			'end'      => '2026-12-14T11:00',
			'reason'   => 'Lens repair',
			'_wpnonce' => wp_create_nonce( ActionHandler::BLOCK_NONCE ),
		);

		$this->assertSame( 'blocked', ActionHandler::processBlock( $block )['code'] );
		$this->assertSame( 'slot_taken', ActionHandler::processBlock( $block )['code'] );
		$this->assertSame( 'invalid', ActionHandler::processBlock( array( 'end' => '2026-12-14T08:00' ) + $block )['code'] );

		$this->assertSame( 'unblocked', ActionHandler::processUnblock( array( 'booking' => '1', '_wpnonce' => wp_create_nonce( ActionHandler::nonceAction( 1 ) ) ) )['code'] );
		$this->assertSame( 'cancelled', $this->repo->find( 1 )['status'] );

		$this->actAs( 'ishot_client' );
		$this->assertSame( 'forbidden', ActionHandler::processBlock( array( '_wpnonce' => wp_create_nonce( ActionHandler::BLOCK_NONCE ) ) + $block )['code'] );
	}

	public function test_buttons_offered_match_the_booking_state_the_clock_and_the_role(): void {
		$now       = new DateTimeImmutable( '2026-12-10 12:00', new DateTimeZone( 'UTC' ) );
		$requested = $this->repo->find( $this->bookingAt( '2026-12-14 10:00' ) );
		$confirmed = $this->repo->find( $this->bookingAt( '2026-12-09 10:00', array( 'status' => BookingStatus::Confirmed ) ) );
		$future    = $this->repo->find( $this->bookingAt( '2026-12-20 10:00', array( 'status' => BookingStatus::Confirmed ) ) );
		$pending   = $this->bookingAt( '2026-12-21 10:00' );
		$this->repo->transition( $pending, BookingStatus::PendingDeposit, 1 );
		$pending = $this->repo->find( $pending );

		$this->actAs( 'ishot_manager' );
		$this->assertSame( array( 'confirm', 'request_deposit', 'reschedule', 'cancel' ), BookingScreen::availableActions( $requested, $now ) );
		$this->assertSame( array( 'deposit_received', 'confirm', 'reschedule', 'cancel' ), BookingScreen::availableActions( $pending, $now ) );
		$this->assertSame( array( 'complete', 'no_show', 'reschedule', 'cancel' ), BookingScreen::availableActions( $confirmed, $now ) );
		$this->assertSame( array( 'reschedule', 'cancel' ), BookingScreen::availableActions( $future, $now ) );

		$this->actAs( 'ishot_photographer' );
		$this->assertSame( array( 'confirm' ), BookingScreen::availableActions( $requested, $now ) );
		$this->assertSame( array( 'complete', 'no_show' ), BookingScreen::availableActions( $confirmed, $now ) );
	}
}
