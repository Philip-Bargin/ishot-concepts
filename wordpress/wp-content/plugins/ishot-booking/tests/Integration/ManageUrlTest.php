<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Frontend\ManageUrl;
use IShot\Booking\Persistence\ManageLinks;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Settings;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The manage-booking link convention: /manage-booking/#token=<token>. The token stays in the URL
 * fragment (never sent to a server or in Referer) and reaches the API in a header, never a path.
 */
final class ManageUrlTest extends TestCase {

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
		$this->service          = wp_insert_post(
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
		$_SERVER['REMOTE_ADDR'] = sprintf( '2001:db8::%x', wp_rand( 1, 0xfffff ) );
		$this->monday           = ( new DateTimeImmutable( 'today', new DateTimeZone( 'Africa/Monrovia' ) ) )->modify( '+7 days' )->modify( 'next monday' )->format( 'Y-m-d' );
	}

	protected function tearDown(): void {
		wp_delete_post( $this->service, true );
	}

	private static function call( string $method, string $route, ?string $token = null, array $body = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/ishot/v1' . $route );
		if ( null !== $token ) {
			$request->set_header( 'X-Ishot-Manage-Token', $token );
		}
		if ( array() !== $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_get_server()->dispatch( $request );
	}

	private function book( string $time ): string {
		$response = self::call(
			'POST',
			'/bookings',
			null,
			array(
				'service' => $this->service,
				'start'   => "{$this->monday}T{$time}",
				'name'    => 'Ada Test',
				'email'   => 'ada@example.com',
				'consent' => true,
			)
		);
		return $response->get_data()['token'];
	}

	public function test_the_link_carries_the_token_only_in_the_fragment(): void {
		$token = str_repeat( 'ab', 32 );
		$url   = ManageUrl::for( $token );

		$this->assertSame( Settings::manageUrl() . '#token=' . $token, $url );
		$this->assertStringNotContainsString( $token, (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$this->assertNull( wp_parse_url( $url, PHP_URL_QUERY ) );
	}

	public function test_the_header_route_shows_and_changes_a_booking(): void {
		$token = $this->book( '10:00' );

		$show = self::call( 'GET', '/manage', $token );
		$this->assertSame( 200, $show->get_status() );
		$this->assertSame( 'requested', $show->get_data()['status'] );
		$this->assertSame( 'no-store', $show->get_headers()['Cache-Control'] );

		$cancel = self::call( 'PATCH', '/manage', $token, array( 'action' => 'cancel' ) );
		$this->assertSame( 'cancelled', $cancel->get_data()['status'] );
	}

	public function test_a_missing_malformed_or_unknown_token_is_a_plain_404(): void {
		$this->assertSame( 404, self::call( 'GET', '/manage' )->get_status() );
		$this->assertSame( 404, self::call( 'GET', '/manage', 'not-a-token' )->get_status() );
		$this->assertSame( 404, self::call( 'GET', '/manage', str_repeat( 'c', 64 ) )->get_status() );
	}

	public function test_the_standard_route_ignores_a_token_put_in_the_query_string(): void {
		$token   = $this->book( '10:00' );
		$request = new WP_REST_Request( 'GET', '/ishot/v1/manage' );
		$request->set_query_params( array( 'token' => $token ) );
		$this->assertSame( 404, rest_get_server()->dispatch( $request )->get_status(), 'never accept the token from a URL' );
	}

	public function test_links_from_emails_already_sent_keep_working(): void {
		$token  = $this->book( '10:00' );
		global $wpdb;
		$id     = (int) $wpdb->get_var( 'SELECT id FROM ' . Schema::bookings() . ' ORDER BY id DESC LIMIT 1' ); // phpcs:ignore
		$issued = ( new ManageLinks() )->issue( $id );

		$this->assertSame( 200, self::call( 'GET', '/manage', $issued['token'] )->get_status(), 'emailed per-message token' );
		$this->assertSame( 200, self::call( 'GET', '/manage', $token )->get_status(), 'token returned at booking time' );
	}

	public function test_the_old_path_route_still_answers_but_is_marked_deprecated(): void {
		$token    = $this->book( '10:00' );
		$response = self::call( 'GET', "/bookings/{$token}" );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'true', $response->get_headers()['Deprecation'] );
		$this->assertStringContainsString( 'ishot/v1/manage', $response->get_headers()['Link'] );
	}

	public function test_legacy_link_shapes_redirect_to_the_fragment_form(): void {
		$token = str_repeat( 'd', 64 );
		$path  = (string) wp_parse_url( Settings::manageUrl(), PHP_URL_PATH );

		$this->assertSame( ManageUrl::for( $token ), ManageUrl::legacyTarget( "/manage/{$token}" ) );
		$this->assertSame( ManageUrl::for( $token ), ManageUrl::legacyTarget( "/manage/{$token}/" ) );
		$this->assertSame( ManageUrl::for( $token ), ManageUrl::legacyTarget( "{$path}?token={$token}" ) );
		$this->assertNull( ManageUrl::legacyTarget( '/manage/not-a-token' ) );
		$this->assertNull( ManageUrl::legacyTarget( "{$path}?token=short" ) );
		$this->assertNull( ManageUrl::legacyTarget( '/portfolio/' ) );
	}

	public function test_the_manage_page_is_recognised_for_its_privacy_headers(): void {
		$path = (string) wp_parse_url( Settings::manageUrl(), PHP_URL_PATH );
		$this->assertTrue( ManageUrl::isManagePath( $path ) );
		$this->assertTrue( ManageUrl::isManagePath( rtrim( $path, '/' ) ) );
		$this->assertFalse( ManageUrl::isManagePath( '/manage-bookings-archive/' ) );
		$this->assertSame( 'no-referrer', ManageUrl::HEADERS['Referrer-Policy'] );
	}

	public function test_the_manage_page_blocks_third_party_scripts_and_exfiltration(): void {
		$csp = ManageUrl::headers()['Content-Security-Policy'];
		foreach ( array( "script-src 'self'", "connect-src 'self'", "img-src 'self'", "frame-ancestors 'none'", "form-action 'self'", "base-uri 'self'" ) as $directive ) {
			$this->assertStringContainsString( $directive, $csp );
		}
		$this->assertDoesNotMatchRegularExpression( '#https?:|\*#', $csp, 'no third-party origins or wildcards' );

		add_filter( 'ishot_booking_manage_csp', static fn( array $d ) => array( 'img-src' => "'self' data: https://cdn.example" ) + $d );
		$this->assertStringContainsString( 'img-src \'self\' data: https://cdn.example', ManageUrl::headers()['Content-Security-Policy'], 'site owner can extend deliberately' );
		remove_all_filters( 'ishot_booking_manage_csp' );
	}
}
