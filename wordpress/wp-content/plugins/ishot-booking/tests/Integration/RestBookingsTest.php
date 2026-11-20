<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Persistence\Schema;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public booking API (ishot/v1). Plain TestCase for the same reason as BookingRepositoryTest:
 * the API's own transactions must run against real InnoDB tables.
 */
final class RestBookingsTest extends TestCase {

	private int $service;
	/** @var int[] */
	private array $posts = array();
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
				'horizon_days'        => 180,
				'granularity_minutes' => 30,
				'hold_hours'          => 48,
				'weekly_hours'        => array_fill_keys( array( 1, 2, 3, 4, 5, 6 ), array( '09:00', '18:00' ) ),
			)
		);
		$this->service = $this->makeService( 'Portrait session', 60, 30, true );

		// A fresh address per test so rate-limit counters never carry over between tests.
		$_SERVER['REMOTE_ADDR'] = sprintf( '2001:db8::%x', wp_rand( 1, 0xfffff ) );

		// A Monday 8–14 days ahead: past the 48-hour lead time, inside the horizon, studio open.
		$tz           = new DateTimeZone( 'Africa/Monrovia' );
		$this->monday = ( new DateTimeImmutable( 'today', $tz ) )->modify( '+7 days' )->modify( 'next monday' )->format( 'Y-m-d' );
	}

	protected function tearDown(): void {
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		remove_all_filters( 'ishot_booking_rate_limit' );
	}

	private function makeService( string $title, int $duration, int $buffer_after, bool $bookable ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => 'ishot_service',
				'post_status' => 'publish',
				'post_title'  => $title,
				'meta_input'  => array(
					'duration_min'      => $duration,
					'buffer_before_min' => 0,
					'buffer_after_min'  => $buffer_after,
					'price_from'        => 150,
					'deposit_amount'    => 50,
					'bookable'          => $bookable ? 1 : 0,
				),
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	private static function request( string $method, string $route, array $params = array(), array $headers = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/ishot/v1' . $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} else {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $params ) );
		}
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}
		return rest_get_server()->dispatch( $request );
	}

	private function book( string $time, array $overrides = array(), array $headers = array() ): WP_REST_Response {
		return self::request(
			'POST',
			'/bookings',
			array_merge(
				array(
					'service' => $this->service,
					'start'   => "{$this->monday}T{$time}",
					'name'    => 'Ada Test',
					'email'   => 'ada@example.com',
					'phone'   => '+231 88 000 0000',
					'consent' => true,
					'website' => '',
				),
				$overrides
			),
			$headers
		);
	}

	private static function bookingCount(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::bookings() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function test_request_creates_a_booking_and_returns_a_manage_token_stored_only_as_a_hash(): void {
		global $wpdb;
		$response = $this->book( '10:00' );

		$this->assertSame( 201, $response->get_status() );
		$body = $response->get_data();
		$this->assertSame( 'requested', $body['status'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $body['token'] );
		$this->assertSame( "{$this->monday}T10:00:00+00:00", $body['start'] );
		$this->assertSame( "{$this->monday}T11:00:00+00:00", $body['end'] );

		$row = $wpdb->get_row( 'SELECT * FROM ' . Schema::bookings(), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame( hash( 'sha256', $body['token'] ), $row['public_token_hash'] );
		$this->assertStringNotContainsString( $body['token'], wp_json_encode( $row ) );
		$this->assertNotNull( $row['hold_expires_at'] );
	}

	public function test_overlapping_request_gets_409_with_the_nearest_free_times(): void {
		$this->assertSame( 201, $this->book( '10:00' )->get_status() );

		// 10:00–11:00 + 30 min buffer holds 10:00, 10:30, 11:00. A 10:30 start would need 10:30–12:00.
		$response = $this->book( '10:30', array( 'email' => 'bea@example.com' ) );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'ishot_slot_taken', $response->get_data()['code'] );
		$this->assertSame(
			array( "{$this->monday}T11:30:00+00:00", "{$this->monday}T12:00:00+00:00", "{$this->monday}T12:30:00+00:00" ),
			$response->get_data()['data']['alternatives']
		);
		$this->assertSame( 1, self::bookingCount() );
	}

	public function test_policy_violation_is_rejected_with_422_and_a_reason(): void {
		$tomorrow = ( new DateTimeImmutable( 'tomorrow', new DateTimeZone( 'Africa/Monrovia' ) ) )->format( 'Y-m-d' );
		$response = $this->book( '10:00', array( 'start' => "{$tomorrow}T10:00" ) );

		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'ishot_too_soon', $response->get_data()['code'] );
		$this->assertSame( 0, self::bookingCount() );
	}

	public function test_missing_or_invalid_fields_are_rejected_with_400(): void {
		$this->assertSame( 400, $this->book( '10:00', array( 'email' => 'not-an-email' ) )->get_status() );
		$this->assertSame( 400, $this->book( '10:00', array( 'consent' => false ) )->get_status() );
		$this->assertSame( 400, $this->book( '10:00', array( 'name' => str_repeat( 'x', 101 ) ) )->get_status() );
		$this->assertSame( 400, $this->book( '10:00', array( 'start' => 'next tuesday' ) )->get_status() );
		$this->assertSame( 0, self::bookingCount() );
	}

	public function test_unknown_or_unbookable_service_is_rejected(): void {
		$hidden = $this->makeService( 'Archive only', 60, 0, false );

		$this->assertSame( 'ishot_unknown_service', $this->book( '10:00', array( 'service' => 999999 ) )->get_data()['code'] );
		$this->assertSame( 'ishot_unknown_service', $this->book( '10:00', array( 'service' => $hidden ) )->get_data()['code'] );
		$this->assertSame( 0, self::bookingCount() );
	}

	public function test_filled_honeypot_is_accepted_silently_but_nothing_is_saved(): void {
		$response = $this->book( '10:00', array( 'website' => 'http://spam.example' ) );

		$this->assertSame( 202, $response->get_status() );
		$this->assertArrayNotHasKey( 'token', $response->get_data() );
		$this->assertSame( 0, self::bookingCount() );
	}

	public function test_retry_with_the_same_idempotency_key_returns_the_original_booking(): void {
		$headers = array( 'Idempotency-Key' => 'c0ffee00-0000-4000-8000-000000000001' );
		$first   = $this->book( '10:00', array(), $headers );
		$second  = $this->book( '10:00', array(), $headers );

		$this->assertSame( 201, $first->get_status() );
		$this->assertSame( 200, $second->get_status() );
		$this->assertTrue( $second->get_data()['replayed'] );
		$this->assertArrayNotHasKey( 'token', $second->get_data() );
		$this->assertSame( 1, self::bookingCount() );
	}

	public function test_too_many_requests_from_one_address_get_429(): void {
		add_filter( 'ishot_booking_rate_limit', static fn() => 2 );

		$this->assertSame( 201, $this->book( '10:00' )->get_status() );
		$this->assertSame( 201, $this->book( '14:00' )->get_status() );
		$this->assertSame( 429, $this->book( '16:00' )->get_status() );
		$this->assertSame( 2, self::bookingCount() );
	}

	public function test_availability_lists_free_start_times_for_a_day(): void {
		$this->assertSame( 201, $this->book( '10:00' )->get_status() );

		$response = self::request( 'GET', '/availability', array( 'service' => $this->service, 'date' => $this->monday ) );

		$this->assertSame( 200, $response->get_status() );
		// Open 09:00–18:00, 60 min + 30 min buffer; 10:00–11:30 is held, so 09:00 and 09:30 (whose
		// buffered range reaches 10:00) are gone too. Last start is 17:00 (must end by 18:00).
		$this->assertSame(
			array( '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00' ),
			$response->get_data()['times']
		);
	}

	public function test_availability_is_empty_on_a_closed_day(): void {
		$sunday   = ( new DateTimeImmutable( $this->monday ) )->modify( '-1 day' )->format( 'Y-m-d' );
		$response = self::request( 'GET', '/availability', array( 'service' => $this->service, 'date' => $sunday ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data()['times'] );
	}

	public function test_services_lists_only_bookable_services_with_their_durations(): void {
		$this->makeService( 'Archive only', 60, 0, false );

		$services = self::request( 'GET', '/services' )->get_data();

		$this->assertSame(
			array(
				array(
					'id'             => $this->service,
					'title'          => 'Portrait session',
					'duration_min'   => 60,
					'price_from'     => 150.0,
					'deposit_amount' => 50.0,
				),
			),
			$services
		);
	}
}
