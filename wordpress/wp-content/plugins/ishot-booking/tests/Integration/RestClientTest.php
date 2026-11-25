<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\Schema;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The client's manage-booking link: /bookings/{token}. The token is the only credential, so
 * an unknown token is a 404 (never a 403 that would confirm a booking exists).
 */
final class RestClientTest extends TestCase {

	private BookingRepository $repo;
	private int $service;
	private string $monday;

	protected function setUp(): void {
		global $wpdb;
		Schema::install();
		foreach ( Schema::tables() as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		update_option( 'timezone_string', 'Africa/Monrovia' );
		update_option(
			'ishot_booking_settings',
			array(
				'lead_time_hours'     => 48,
				'cancel_cutoff_hours' => 72,
				'weekly_hours'        => array_fill_keys( array( 1, 2, 3, 4, 5, 6 ), array( '09:00', '18:00' ) ),
			)
		);
		$this->repo    = new BookingRepository( $wpdb, 30 );
		$this->service = wp_insert_post(
			array(
				'post_type'   => 'ishot_service',
				'post_status' => 'publish',
				'post_title'  => 'Portrait session',
				'meta_input'  => array(
					'duration_min'     => 60,
					'buffer_after_min' => 30,
					'deposit_amount'   => 50,
					'bookable'         => 1,
				),
			)
		);
		$_SERVER['REMOTE_ADDR'] = sprintf( '2001:db8::%x', wp_rand( 1, 0xfffff ) );
		$this->monday           = ( new DateTimeImmutable( 'today', new DateTimeZone( 'Africa/Monrovia' ) ) )->modify( '+7 days' )->modify( 'next monday' )->format( 'Y-m-d' );
	}

	protected function tearDown(): void {
		wp_delete_post( $this->service, true );
		remove_all_filters( 'ishot_booking_rate_limit' );
	}

	private static function call( string $method, string $route, array $body = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/ishot/v1' . $route );
		if ( array() !== $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_get_server()->dispatch( $request );
	}

	/** Books through the public API and returns the manage token. */
	private function book( string $time, string $email = 'ada@example.com' ): string {
		$response = self::call(
			'POST',
			'/bookings',
			array(
				'service' => $this->service,
				'start'   => "{$this->monday}T{$time}",
				'name'    => 'Ada Test',
				'email'   => $email,
				'consent' => true,
			)
		);
		$this->assertSame( 201, $response->get_status() );
		return $response->get_data()['token'];
	}

	/** Inserts a booking at an arbitrary UTC time, bypassing policy, and returns its token. */
	private function bookAt( DateTimeImmutable $start ): string {
		$token = bin2hex( random_bytes( 32 ) );
		$this->repo->create(
			new NewBooking(
				session: new TimeRange( $start, $start->modify( '+60 minutes' ) ),
				buffer_before_min: 0,
				buffer_after_min: 0,
				status: BookingStatus::Confirmed,
				client_name: 'Ada Test',
				client_email: 'ada@example.com',
				service_id: $this->service,
				public_token_hash: hash( 'sha256', $token )
			)
		);
		return $token;
	}

	private static function hourFromNow( int $hours ): DateTimeImmutable {
		return ( new DateTimeImmutable( '@' . ( ( intdiv( time(), 3600 ) + $hours ) * 3600 ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	public function test_the_token_shows_the_booking_without_contact_details(): void {
		$token = $this->book( '10:00' );

		$response = self::call( 'GET', "/bookings/{$token}" );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'requested', $data['status'] );
		$this->assertSame( "{$this->monday}T10:00:00+00:00", $data['start'] );
		$this->assertSame( 'Portrait session', $data['service']['title'] );
		$this->assertTrue( $data['can_cancel'] );
		$this->assertTrue( $data['can_reschedule'] );
		$this->assertSame( "{$this->monday}T10:00:00+00:00", ( new DateTimeImmutable( $data['change_deadline'] ) )->modify( '+72 hours' )->format( DATE_ATOM ) );
		$this->assertStringNotContainsString( 'ada@example.com', wp_json_encode( $data ) );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
	}

	public function test_unknown_and_malformed_tokens_are_404(): void {
		$this->book( '10:00' );

		$this->assertSame( 404, self::call( 'GET', '/bookings/' . str_repeat( 'a', 64 ) )->get_status() );
		$this->assertSame( 404, self::call( 'GET', '/bookings/not-a-token' )->get_status() );
	}

	public function test_client_cancels_before_the_cut_off_and_the_slot_is_freed(): void {
		$token = $this->book( '10:00' );

		$response = self::call( 'PATCH', "/bookings/{$token}", array( 'action' => 'cancel' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'cancelled', $response->get_data()['status'] );
		$this->assertFalse( $response->get_data()['can_cancel'] );
		$this->assertSame( array(), $this->repo->lockedUnits( 1, "{$this->monday} 00:00:00", "{$this->monday} 23:59:59" ) );
		$events = $this->repo->events( 1 );
		$this->assertSame( array( 'by' => 'client' ), json_decode( end( $events )['payload'], true ) );
	}

	public function test_inside_the_cut_off_the_client_must_contact_the_studio(): void {
		$token = $this->bookAt( self::hourFromNow( 48 ) );

		$view   = self::call( 'GET', "/bookings/{$token}" )->get_data();
		$cancel = self::call( 'PATCH', "/bookings/{$token}", array( 'action' => 'cancel' ) );

		$this->assertFalse( $view['can_cancel'] );
		$this->assertFalse( $view['can_reschedule'] );
		$this->assertSame( 422, $cancel->get_status() );
		$this->assertSame( 'ishot_past_cutoff', $cancel->get_data()['code'] );
		$this->assertSame( 'confirmed', $this->repo->find( 1 )['status'] );
	}

	public function test_client_reschedules_to_a_free_time(): void {
		$token = $this->book( '10:00' );

		$response = self::call( 'PATCH', "/bookings/{$token}", array( 'action' => 'reschedule', 'start' => "{$this->monday}T15:00" ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( "{$this->monday}T15:00:00+00:00", $response->get_data()['start'] );
		$this->assertSame( "{$this->monday}T16:00:00+00:00", $response->get_data()['end'] );
		$this->assertSame( '2', $this->repo->find( 1 )['version'] );
	}

	public function test_a_reschedule_that_keeps_losing_deadlocks_gets_a_clean_503_and_keeps_the_booking(): void {
		$token = $this->book( '10:00' );

		DeadlockingWpdb::swapIn();
		try {
			$response = self::call( 'PATCH', "/bookings/{$token}", array( 'action' => 'reschedule', 'start' => "{$this->monday}T15:00" ) );
		} finally {
			DeadlockingWpdb::restore();
		}

		$this->assertSame( 503, $response->get_status() );
		$this->assertSame( 'ishot_busy', $response->get_data()['code'] );
		$this->assertSame( "{$this->monday} 10:00:00", $this->repo->find( 1 )['start_utc'] );
	}

	public function test_rescheduling_onto_a_taken_time_offers_alternatives_and_keeps_the_booking(): void {
		$token = $this->book( '10:00' );
		$this->book( '14:00', 'bea@example.com' ); // holds 14:00–15:30

		$response = self::call( 'PATCH', "/bookings/{$token}", array( 'action' => 'reschedule', 'start' => "{$this->monday}T14:30" ) );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'ishot_slot_taken', $response->get_data()['code'] );
		$this->assertNotEmpty( $response->get_data()['data']['alternatives'] );
		$this->assertSame( "{$this->monday} 10:00:00", $this->repo->find( 1 )['start_utc'] );
	}

	public function test_rescheduling_must_follow_studio_policy(): void {
		$token  = $this->book( '10:00' );
		$sunday = ( new DateTimeImmutable( $this->monday ) )->modify( '-1 day' )->format( 'Y-m-d' );

		$response = self::call( 'PATCH', "/bookings/{$token}", array( 'action' => 'reschedule', 'start' => "{$sunday}T10:00" ) );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'ishot_outside_hours', $response->get_data()['code'] );
	}

	public function test_a_cancelled_booking_cannot_be_changed_again(): void {
		$token = $this->book( '10:00' );
		self::call( 'PATCH', "/bookings/{$token}", array( 'action' => 'cancel' ) );

		$again = self::call( 'PATCH', "/bookings/{$token}", array( 'action' => 'reschedule', 'start' => "{$this->monday}T15:00" ) );

		$this->assertSame( 422, $again->get_status() );
		$this->assertSame( 'ishot_not_changeable', $again->get_data()['code'] );
	}

	public function test_the_link_stops_working_thirty_days_after_the_session(): void {
		$recent = $this->bookAt( self::hourFromNow( -24 * 29 ) );
		$old    = $this->bookAt( self::hourFromNow( -24 * 31 ) );

		$this->assertSame( 200, self::call( 'GET', "/bookings/{$recent}" )->get_status() );
		$this->assertSame( 404, self::call( 'GET', "/bookings/{$old}" )->get_status() );
	}

	public function test_guessing_tokens_is_rate_limited(): void {
		add_filter( 'ishot_booking_manage_rate_limit', static fn() => 2 );

		self::call( 'GET', '/bookings/' . str_repeat( 'b', 64 ) );
		self::call( 'GET', '/bookings/' . str_repeat( 'c', 64 ) );

		$this->assertSame( 429, self::call( 'GET', '/bookings/' . str_repeat( 'd', 64 ) )->get_status() );
		remove_all_filters( 'ishot_booking_manage_rate_limit' );
	}
}
