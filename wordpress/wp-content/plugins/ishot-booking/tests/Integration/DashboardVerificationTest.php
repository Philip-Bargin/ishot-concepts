<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Admin\ActionHandler;
use IShot\Booking\Admin\BookingScreen;
use IShot\Booking\Admin\CalendarScreen;
use IShot\Booking\Admin\CalendarWeek;
use IShot\Booking\Admin\DashboardWidget;
use IShot\Booking\Admin\ListScreen;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Settings;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * Milestone verification for the admin dashboard: blocked time vs reservations, status changes
 * reaching calendar + database + history, empty states, errors and conflicting updates.
 */
final class DashboardVerificationTest extends TestCase {

	use BookingFixtures;

	protected function setUp(): void {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		$this->resetBookings();
		$this->makeStaff();
		$_GET = array();
	}

	protected function tearDown(): void {
		$_GET = array();
		$this->removeStaff();
	}

	private static function post( int $id, string $action, int $version, array $extra = array() ): array {
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

	private static function block( string $start, string $end ): array {
		return ActionHandler::processBlock(
			array(
				'start'    => $start,
				'end'      => $end,
				'_wpnonce' => wp_create_nonce( ActionHandler::BLOCK_NONCE ),
			)
		);
	}

	private static function render( callable $screen ): string {
		ob_start();
		$screen();
		return (string) ob_get_clean();
	}

	private function week( string $day = '2026-12-14' ): array {
		return CalendarWeek::build( $this->repo, new DateTimeImmutable( $day, wp_timezone() ), Settings::policy()->hours );
	}

	private function locks( string $day = '2026-12-14' ): array {
		return $this->repo->lockedUnits( 1, "{$day} 00:00:00", "{$day} 23:59:59" );
	}

	// ── Blocking never compromises reservations ──────────────────────────────────────────

	public function test_blocking_over_an_existing_booking_is_refused_and_the_booking_keeps_its_slot(): void {
		$id     = $this->bookingAt( '2026-12-14 10:00', array( 'after' => 30 ) );
		$before = $this->locks();
		$this->actAs( 'ishot_photographer' );

		$this->assertSame( 'slot_taken', self::block( '2026-12-14T11:00', '2026-12-14T12:00' )['code'] ); // touches the buffer
		$this->assertSame( 'slot_taken', self::block( '2026-12-14T08:00', '2026-12-14T18:00' )['code'] ); // swallows it

		$this->assertSame( $before, $this->locks() );
		$this->assertSame( 'requested', $this->repo->find( $id )['status'] );
		$this->assertCount( 1, $this->repo->between( '2026-12-14 00:00:00', '2026-12-15 00:00:00' ) );
	}

	public function test_a_client_cannot_book_into_blocked_time(): void {
		update_option(
			Settings::OPTION,
			array( 'weekly_hours' => array_fill_keys( range( 1, 7 ), array( '09:00', '18:00' ) ) )
		);
		$service = wp_insert_post(
			array(
				'post_type'   => 'ishot_service',
				'post_status' => 'publish',
				'post_title'  => 'Portrait session',
				'meta_input'  => array(
					'duration_min' => 60,
					'bookable'     => 1,
				),
			)
		);
		$day = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '+10 days' )->format( 'Y-m-d' );
		$this->actAs( 'ishot_photographer' );
		$this->assertSame( 'blocked', self::block( "{$day}T09:00", "{$day}T13:00" )['code'] );
		$this->actAs( null );
		$_SERVER['REMOTE_ADDR'] = sprintf( '2001:db8::%x', wp_rand( 1, 0xfffff ) );

		$availability = new WP_REST_Request( 'GET', '/ishot/v1/availability' );
		$availability->set_query_params( array( 'service' => $service, 'date' => $day ) );
		$times = rest_get_server()->dispatch( $availability )->get_data()['times'];

		$request = new WP_REST_Request( 'POST', '/ishot/v1/bookings' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'service' => $service, 'start' => "{$day}T12:00", 'name' => 'Ada', 'email' => 'ada@example.com', 'consent' => true ) ) );
		$status = rest_get_server()->dispatch( $request )->get_status();
		wp_delete_post( $service, true );

		$this->assertSame( '13:00', $times[0] );
		$this->assertSame( 409, $status );
	}

	public function test_removing_a_block_frees_only_the_blocks_own_time(): void {
		$booking = $this->bookingAt( '2026-12-14 12:00', array( 'after' => 30 ) );
		$this->actAs( 'ishot_photographer' );
		self::block( '2026-12-14T09:00', '2026-12-14T12:00' ); // back to back with the booking
		$block = (int) $this->repo->between( '2026-12-14 09:00:00', '2026-12-14 09:30:00' )[0]['id'];

		$result = ActionHandler::processUnblock( array( 'booking' => (string) $block, '_wpnonce' => wp_create_nonce( ActionHandler::nonceAction( $block ) ) ) );

		$this->assertSame( 'unblocked', $result['code'] );
		$this->assertSame( array( '2026-12-14 12:00:00', '2026-12-14 12:30:00', '2026-12-14 13:00:00' ), $this->locks() );
		$this->assertSame( 'requested', $this->repo->find( $booking )['status'] );
	}

	public function test_unblock_cannot_be_used_to_cancel_a_client_booking(): void {
		$booking = $this->bookingAt( '2026-12-14 10:00' );
		$this->actAs( 'ishot_photographer' );

		$result = ActionHandler::processUnblock( array( 'booking' => (string) $booking, '_wpnonce' => wp_create_nonce( ActionHandler::nonceAction( $booking ) ) ) );

		$this->assertSame( 'not_found', $result['code'] );
		$this->assertSame( 'requested', $this->repo->find( $booking )['status'] );
	}

	public function test_booking_actions_cannot_be_applied_to_a_block(): void {
		$block = $this->bookingAt( '2026-12-01 10:00', array( 'kind' => 'block', 'status' => BookingStatus::Confirmed ) ); // already started

		$this->actAs( 'ishot_photographer' );
		$rest = new WP_REST_Request( 'POST', "/ishot/v1/staff/bookings/{$block}/actions" );
		$rest->set_header( 'content-type', 'application/json' );
		$rest->set_body( wp_json_encode( array( 'action' => 'complete', 'version' => 1 ) ) );
		$this->assertSame( 422, rest_get_server()->dispatch( $rest )->get_status() );

		$this->actAs( 'ishot_manager' );
		$this->assertSame( 'invalid', ActionHandler::process( self::post( $block, 'cancel', 1 ) )['code'] );
		$this->assertSame( 'invalid', ActionHandler::process( self::post( $block, 'reschedule', 1, array( 'start' => '2026-12-20T10:00' ) ) )['code'] );

		$this->assertSame( 'confirmed', $this->repo->find( $block )['status'] );
		$this->assertSame( array( '2026-12-01 10:00:00', '2026-12-01 10:30:00' ), $this->locks( '2026-12-01' ) );
	}

	// ── Status changes reach calendar, database and history ──────────────────────────────

	public function test_status_changes_update_the_calendar_the_record_and_the_history(): void {
		$id = $this->bookingAt( '2026-12-14 10:00', array( 'after' => 30 ) );
		$this->actAs( 'ishot_photographer' );
		ActionHandler::process( self::post( $id, 'confirm', 1 ) );

		$this->assertSame( 'confirmed', $this->week()['days'][0]['items'][0]['status'] );
		$this->assertNull( $this->repo->find( $id )['hold_expires_at'] );

		$this->actAs( 'ishot_manager' );
		ActionHandler::process( self::post( $id, 'reschedule', 2, array( 'start' => '2026-12-15T14:00' ) ) );
		$week = $this->week();
		$this->assertSame( array(), $week['days'][0]['items'] );
		$this->assertSame( '14:00', $week['days'][1]['items'][0]['start'] );

		ActionHandler::process( self::post( $id, 'cancel', 3, array( 'reason' => 'Client travelling' ) ) );
		$this->assertSame( array(), $this->week()['days'][1]['items'] );
		$this->assertSame( array(), $this->locks( '2026-12-15' ) );

		$row = $this->repo->find( $id );
		$this->assertSame( array( 'cancelled', '4', '2026-12-15 14:00:00' ), array( $row['status'], $row['version'], $row['start_utc'] ) );

		$events = $this->repo->events( $id );
		$this->assertSame( array( 'created', 'status_changed', 'rescheduled', 'status_changed' ), array_column( $events, 'action' ) );
		$this->assertSame(
			array( null, (string) $this->users['ishot_photographer'], (string) $this->users['ishot_manager'], (string) $this->users['ishot_manager'] ),
			array_column( $events, 'actor_user_id' )
		);
		$this->assertSame( array( 'by' => 'studio', 'reason' => 'Client travelling' ), json_decode( $events[3]['payload'], true ) );

		$html = self::render( static fn() => BookingScreen::render( $id, new DateTimeImmutable( '2026-12-10' ) ) );
		$this->assertStringContainsString( 'Client travelling', $html );
		$this->assertStringContainsString( 'No actions are available for this booking.', $html );
	}

	// ── Errors, empty states, conflicting updates ────────────────────────────────────────

	public function test_two_staff_acting_on_the_same_version_the_first_change_stands(): void {
		$id = $this->bookingAt( '2026-12-14 10:00' );
		$this->actAs( 'ishot_photographer' );
		$first = ActionHandler::process( self::post( $id, 'confirm', 1 ) );
		$this->actAs( 'ishot_manager' );
		$second = ActionHandler::process( self::post( $id, 'cancel', 1 ) ); // rendered before the confirm

		$this->assertSame( 'success', $first['type'] );
		$this->assertSame( array( 'error', 'stale' ), array( $second['type'], $second['code'] ) );
		$this->assertSame( 'confirmed', $this->repo->find( $id )['status'] );
		$this->assertCount( 2, $this->repo->events( $id ) );
	}

	public function test_error_notices_render_and_unknown_codes_render_nothing(): void {
		$id = $this->bookingAt( '2026-12-14 10:00' );
		$this->actAs( 'ishot_manager' );

		$_GET = array( 'ishot_notice' => 'stale', 'ishot_type' => 'error' );
		$html = self::render( static fn() => BookingScreen::render( $id, new DateTimeImmutable( '2026-12-10' ) ) );
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'Someone else changed this booking', $html );

		$_GET = array( 'ishot_notice' => '<script>alert(1)</script>', 'ishot_type' => '"><script>' );
		$html = self::render( static fn() => BookingScreen::render( $id, new DateTimeImmutable( '2026-12-10' ) ) );
		$this->assertStringNotContainsString( 'notice-', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_empty_states_say_so(): void {
		$this->actAs( 'ishot_manager' );
		set_current_screen( 'dashboard' );

		$calendar = self::render( static fn() => CalendarScreen::render( '2026-12-14' ) );
		$this->assertSame( 7, substr_count( $calendar, 'Nothing booked' ) );

		$list = self::render( static fn() => ListScreen::render( array( 'when' => 'all' ) ) );
		$this->assertStringContainsString( 'No bookings match.', $list );

		$widget = self::render( array( DashboardWidget::class, 'render' ) );
		$this->assertStringContainsString( 'No sessions today.', $widget );
		$this->assertSame( 3, substr_count( $widget, '<strong>0</strong>' ) );
	}

	public function test_search_and_filters_from_the_list_screen(): void {
		$this->bookingAt( '2030-01-14 10:00', array( 'name' => 'Musu Kollie', 'email' => 'musu@example.com' ) );
		$this->bookingAt( '2030-01-15 10:00', array( 'name' => 'Jefferson Tarr', 'status' => BookingStatus::Confirmed ) );
		$this->bookingAt( '2020-01-15 10:00', array( 'name' => 'Old Client', 'status' => BookingStatus::Confirmed ) );
		$this->actAs( 'ishot_photographer' );
		set_current_screen( 'dashboard' );

		$by_name = self::render( static fn() => ListScreen::render( array( 'when' => 'all', 'q' => 'kollie' ) ) );
		$this->assertStringContainsString( 'Musu Kollie', $by_name );
		$this->assertStringNotContainsString( 'Jefferson Tarr', $by_name );

		$confirmed_upcoming = self::render( static fn() => ListScreen::render( array( 'when' => 'upcoming', 'status' => 'confirmed' ) ) );
		$this->assertStringContainsString( 'Jefferson Tarr', $confirmed_upcoming );
		$this->assertStringNotContainsString( 'Old Client', $confirmed_upcoming );
		$this->assertStringNotContainsString( 'Musu Kollie', $confirmed_upcoming );

		$past = self::render( static fn() => ListScreen::render( array( 'when' => 'past' ) ) );
		$this->assertStringContainsString( 'Old Client', $past );
		$this->assertStringNotContainsString( 'Musu Kollie', $past );
	}
}
