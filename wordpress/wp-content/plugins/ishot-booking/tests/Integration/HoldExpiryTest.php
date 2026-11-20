<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Application\HoldExpiry;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\Schema;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * Unpaid requests hold their slot only until hold_expires_at. After that the slot is released,
 * both by the scheduled sweep and immediately whenever availability is read or a booking is made.
 */
final class HoldExpiryTest extends TestCase {

	private BookingRepository $repo;
	private DateTimeImmutable $now;

	protected function setUp(): void {
		global $wpdb;
		Schema::install();
		foreach ( Schema::tables() as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$this->repo = new BookingRepository( $wpdb, 30 );
		$this->now  = new DateTimeImmutable( '2026-12-01 12:00', new DateTimeZone( 'UTC' ) );
	}

	private function hold( string $start, ?string $expires, BookingStatus $status = BookingStatus::Requested ): int {
		$utc   = new DateTimeZone( 'UTC' );
		$begin = new DateTimeImmutable( $start, $utc );
		return $this->repo->create(
			new NewBooking(
				session: new TimeRange( $begin, $begin->modify( '+60 minutes' ) ),
				buffer_before_min: 0,
				buffer_after_min: 0,
				status: $status,
				client_name: 'Ada Test',
				client_email: 'ada@example.com',
				service_id: 1,
				hold_expires_at: null === $expires ? null : new DateTimeImmutable( $expires, $utc )
			)
		);
	}

	public function test_lapsed_holds_expire_and_release_their_slot(): void {
		$requested = $this->hold( '2026-12-14 10:00', '2026-12-01 11:59' );
		$pending   = $this->hold( '2026-12-14 14:00', '2026-12-01 12:00' );

		$expired = ( new HoldExpiry( $this->repo ) )->run( $this->now );

		$this->assertSame( 2, $expired );
		$this->assertSame( 'expired', $this->repo->find( $requested )['status'] );
		$this->assertSame( 'expired', $this->repo->find( $pending )['status'] );
		$this->assertSame( array(), $this->repo->lockedUnits( 1, '2026-12-14 00:00:00', '2026-12-15 00:00:00' ) );
		$this->assertSame( array( 'reason' => 'hold_lapsed' ), json_decode( $this->repo->events( $requested )[1]['payload'], true ) );
	}

	public function test_live_holds_and_confirmed_bookings_are_left_alone(): void {
		$live      = $this->hold( '2026-12-14 10:00', '2026-12-01 12:01' );
		$confirmed = $this->hold( '2026-12-14 14:00', null, BookingStatus::Confirmed );

		$this->assertSame( 0, ( new HoldExpiry( $this->repo ) )->run( $this->now ) );
		$this->assertSame( 'requested', $this->repo->find( $live )['status'] );
		$this->assertSame( 'confirmed', $this->repo->find( $confirmed )['status'] );
	}

	public function test_a_confirmed_booking_whose_old_hold_time_passed_does_not_expire(): void {
		$id = $this->hold( '2026-12-14 10:00', '2026-12-01 09:00' );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1 ); // hold time left in place on purpose

		$this->assertSame( 0, ( new HoldExpiry( $this->repo ) )->run( $this->now ) );
		$this->assertSame( 'confirmed', $this->repo->find( $id )['status'] );
	}

	public function test_the_scheduled_sweep_is_registered_and_runs_expiry(): void {
		$this->assertNotFalse( wp_next_scheduled( HoldExpiry::HOOK ) );

		$id = $this->hold( '2026-12-14 10:00', gmdate( 'Y-m-d H:i:s', time() - 60 ) );
		do_action( HoldExpiry::HOOK );

		$this->assertSame( 'expired', $this->repo->find( $id )['status'] );
	}

	public function test_a_lapsed_hold_does_not_block_a_new_request_even_before_the_sweep_runs(): void {
		update_option( 'timezone_string', 'Africa/Monrovia' );
		delete_option( 'ishot_booking_settings' );
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
		$monday = ( new DateTimeImmutable( 'today', new DateTimeZone( 'Africa/Monrovia' ) ) )->modify( '+7 days' )->modify( 'next monday' )->format( 'Y-m-d' );
		$stale  = $this->hold( "{$monday} 10:00", gmdate( 'Y-m-d H:i:s', time() - 60 ) );
		$_SERVER['REMOTE_ADDR'] = sprintf( '2001:db8::%x', wp_rand( 1, 0xfffff ) );

		$availability = new WP_REST_Request( 'GET', '/ishot/v1/availability' );
		$availability->set_query_params( array( 'service' => $service, 'date' => $monday ) );
		$times = rest_get_server()->dispatch( $availability )->get_data()['times'];

		$request = new WP_REST_Request( 'POST', '/ishot/v1/bookings' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'service' => $service, 'start' => "{$monday}T10:00", 'name' => 'Bea', 'email' => 'bea@example.com', 'consent' => true ) ) );
		$response = rest_get_server()->dispatch( $request );
		wp_delete_post( $service, true );

		$this->assertContains( '10:00', $times );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'expired', $this->repo->find( $stale )['status'] );
	}
}
