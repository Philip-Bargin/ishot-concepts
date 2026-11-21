<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Roles;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Staff routes (ishot/v1/staff/...): who may do what, and what each action changes.
 * Staff act outside client policy (lead time, opening hours) but never outside slot exclusivity.
 */
final class RestStaffTest extends TestCase {

	private BookingRepository $repo;
	/** @var array<string, int> role => user id */
	private array $users = array();

	protected function setUp(): void {
		global $wpdb;
		Schema::install();
		Roles::install();
		foreach ( Schema::tables() as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		update_option( 'timezone_string', 'Africa/Monrovia' );
		$this->repo = new BookingRepository( $wpdb, 30 );

		foreach ( array( 'ishot_manager', 'ishot_photographer', 'ishot_client' ) as $role ) {
			$this->users[ $role ] = wp_insert_user(
				array(
					'user_login' => uniqid( $role ),
					'user_pass'  => wp_generate_password(),
					'user_email' => uniqid( $role ) . '@example.com',
					'role'       => $role,
				)
			);
		}
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_set_current_user( 0 );
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
	}

	private function as( ?string $role ): void {
		wp_set_current_user( null === $role ? 0 : $this->users[ $role ] );
	}

	private function booking( string $start = '2026-12-14 10:00', int $minutes = 60, int $after = 30 ): int {
		$utc   = new DateTimeZone( 'UTC' );
		$begin = new DateTimeImmutable( $start, $utc );
		return $this->repo->create(
			new NewBooking(
				session: new TimeRange( $begin, $begin->modify( "+{$minutes} minutes" ) ),
				buffer_before_min: 0,
				buffer_after_min: $after,
				status: BookingStatus::Requested,
				client_name: 'Ada Test',
				client_email: 'ada@example.com',
				service_id: 1
			)
		);
	}

	private static function call( string $method, string $route, array $params = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/ishot/v1/staff' . $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $params ) );
		}
		return rest_get_server()->dispatch( $request );
	}

	private function act( int $id, string $action, int $version = 1, array $extra = array() ): WP_REST_Response {
		return self::call( 'POST', "/bookings/{$id}/actions", array_merge( array( 'action' => $action, 'version' => $version ), $extra ) );
	}

	public function test_signed_out_visitors_get_401_and_clients_get_403(): void {
		$this->booking();

		$this->assertSame( 401, self::call( 'GET', '/bookings', array( 'from' => '2026-12-14', 'to' => '2026-12-15' ) )->get_status() );
		$this->as( 'ishot_client' );
		$this->assertSame( 403, self::call( 'GET', '/bookings', array( 'from' => '2026-12-14', 'to' => '2026-12-15' ) )->get_status() );
	}

	public function test_photographer_sees_the_calendar_for_a_date_range(): void {
		$id = $this->booking();
		$this->booking( '2026-12-20 10:00' );
		$this->as( 'ishot_photographer' );

		$response = self::call( 'GET', '/bookings', array( 'from' => '2026-12-14', 'to' => '2026-12-15' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$booking = $response->get_data()[0];
		$this->assertSame( $id, $booking['id'] );
		$this->assertSame( 'requested', $booking['status'] );
		$this->assertSame( '2026-12-14T10:00:00+00:00', $booking['start'] );
		$this->assertSame( 'Ada Test', $booking['client']['name'] );
		$this->assertSame( 1, $booking['version'] );
	}

	public function test_photographer_can_confirm_a_request(): void {
		$id = $this->booking();
		$this->as( 'ishot_photographer' );

		$response = $this->act( $id, 'confirm' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'confirmed', $response->get_data()['status'] );
		$this->assertSame( 2, $response->get_data()['version'] );
	}

	public function test_photographer_cannot_cancel_or_reschedule(): void {
		$id = $this->booking();
		$this->as( 'ishot_photographer' );

		$this->assertSame( 403, $this->act( $id, 'cancel' )->get_status() );
		$this->assertSame( 403, $this->act( $id, 'reschedule', 1, array( 'start' => '2026-12-14T14:00' ) )->get_status() );
		$this->assertSame( 'requested', $this->repo->find( $id )['status'] );
	}

	public function test_manager_takes_a_booking_through_the_manual_deposit_flow(): void {
		$id = $this->booking();
		$this->as( 'ishot_manager' );

		$asked = $this->act( $id, 'request_deposit', 1, array( 'deposit_amount' => 50 ) );
		$this->assertSame( 'pending_deposit', $asked->get_data()['status'] );
		$this->assertSame( 'awaiting', $asked->get_data()['deposit']['status'] );
		$this->assertSame( 50.0, $asked->get_data()['deposit']['amount'] );

		$paid = $this->act( $id, 'deposit_received', 2, array( 'reference' => 'OM-8812' ) );
		$this->assertSame( 'confirmed', $paid->get_data()['status'] );
		$this->assertSame( 'received', $paid->get_data()['deposit']['status'] );
		$this->assertSame( 'manual', $paid->get_data()['deposit']['gateway'] );
		$this->assertSame( 'OM-8812', $paid->get_data()['deposit']['reference'] );
	}

	public function test_a_stale_version_gets_409_and_changes_nothing(): void {
		$id = $this->booking();
		$this->as( 'ishot_manager' );
		$this->act( $id, 'confirm' );

		$response = $this->act( $id, 'cancel', 1 );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'ishot_stale', $response->get_data()['code'] );
		$this->assertSame( 'confirmed', $this->repo->find( $id )['status'] );
	}

	public function test_an_impossible_transition_gets_422(): void {
		$id = $this->booking();
		$this->as( 'ishot_manager' );

		$response = $this->act( $id, 'complete' );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'ishot_invalid_transition', $response->get_data()['code'] );
	}

	public function test_manager_reschedules_and_a_clash_leaves_the_booking_where_it_was(): void {
		$id = $this->booking();
		$this->booking( '2026-12-14 15:00' );
		$this->as( 'ishot_manager' );

		$moved = $this->act( $id, 'reschedule', 1, array( 'start' => '2026-12-14T12:00' ) );
		$this->assertSame( 200, $moved->get_status() );
		$this->assertSame( '2026-12-14T12:00:00+00:00', $moved->get_data()['start'] );
		$this->assertSame( '2026-12-14T13:00:00+00:00', $moved->get_data()['end'] );

		$clash = $this->act( $id, 'reschedule', 2, array( 'start' => '2026-12-14T14:30' ) );
		$this->assertSame( 409, $clash->get_status() );
		$this->assertSame( 'ishot_slot_taken', $clash->get_data()['code'] );
		$this->assertSame( '2026-12-14 12:00:00', $this->repo->find( $id )['start_utc'] );
	}

	public function test_unknown_booking_or_action_is_rejected(): void {
		$id = $this->booking();
		$this->as( 'ishot_manager' );

		$this->assertSame( 404, $this->act( 999999, 'confirm' )->get_status() );
		$this->assertSame( 400, $this->act( $id, 'delete_everything' )->get_status() );
	}

	public function test_detail_includes_the_audit_timeline_with_who_did_what(): void {
		$id = $this->booking();
		$this->as( 'ishot_photographer' );
		$this->act( $id, 'confirm' );

		$detail = self::call( 'GET', "/bookings/{$id}" )->get_data();

		$this->assertSame( array( 'created', 'status_changed' ), array_column( $detail['events'], 'action' ) );
		$this->assertSame( $this->users['ishot_photographer'], $detail['events'][1]['actor'] );
		$this->assertSame( 'confirmed', $detail['events'][1]['to'] );
	}

	public function test_blocked_time_keeps_bookings_out_until_it_is_removed(): void {
		$this->as( 'ishot_photographer' );

		$block = self::call( 'POST', '/blocks', array( 'start' => '2026-12-14T09:00', 'end' => '2026-12-14T12:00', 'reason' => 'Studio maintenance' ) );
		$this->assertSame( 201, $block->get_status() );
		$this->assertSame( 'block', $block->get_data()['kind'] );

		$overlap = self::call( 'POST', '/blocks', array( 'start' => '2026-12-14T11:30', 'end' => '2026-12-14T13:00' ) );
		$this->assertSame( 409, $overlap->get_status() );

		$this->assertSame( 200, self::call( 'DELETE', '/blocks/' . $block->get_data()['id'] )->get_status() );
		$this->assertSame( array(), $this->repo->lockedUnits( 1, '2026-12-14 00:00:00', '2026-12-15 00:00:00' ) );
	}

	public function test_block_delete_only_removes_blocks_not_client_bookings(): void {
		$id = $this->booking();
		$this->as( 'ishot_photographer' );

		$this->assertSame( 404, self::call( 'DELETE', "/blocks/{$id}" )->get_status() );
		$this->assertSame( 'requested', $this->repo->find( $id )['status'] );
	}
}
