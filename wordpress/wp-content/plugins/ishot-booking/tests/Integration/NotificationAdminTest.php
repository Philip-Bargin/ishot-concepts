<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Admin\BookingScreen;
use IShot\Booking\Admin\EmailSettingsForm;
use IShot\Booking\Admin\EmailSettingsScreen;
use IShot\Booking\Admin\Menu;
use IShot\Booking\Calendar\CalendarFeed;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Notifications\Dispatcher;
use IShot\Booking\Settings;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_Error;
use WP_REST_Request;

/** Studio email settings, the private calendar feed, the notification log, and what each role may touch. */
final class NotificationAdminTest extends TestCase {

	use BookingFixtures;

	protected function setUp(): void {
		$this->resetBookings();
		$this->makeStaff();
		delete_option( CalendarFeed::OPTION );
	}

	protected function tearDown(): void {
		remove_all_filters( 'wp_die_handler' );
		remove_all_filters( 'pre_wp_mail' );
		$_POST = $_REQUEST = array();
		$this->removeStaff();
	}

	private static function valid( array $overrides = array() ): array {
		return array_merge(
			array(
				'email_from_name'      => 'iShot Concept',
				'email_from_address'   => 'bookings@ishot.example',
				'email_reply_to'       => 'hello@ishot.example',
				'email_studio_address' => 'studio@ishot.example',
				'currency'             => 'LRD',
				'deposit_instructions' => "Pay by mobile money.\nUse your booking reference.",
				'manage_url'           => 'https://ishot.example/manage-booking/',
				'templates'            => array(
					'confirmed' => array(
						'enabled' => '1',
						'subject' => 'Confirmed for {date}',
						'intro'   => 'See you {date}.',
					),
				),
			),
			$overrides
		);
	}

	public function test_valid_email_settings_are_saved(): void {
		$this->assertSame( array(), EmailSettingsForm::save( self::valid() ) );

		$s = Settings::all();
		$this->assertSame( 'bookings@ishot.example', $s['email_from_address'] );
		$this->assertSame( 'LRD', $s['currency'] );
		$this->assertSame( "Pay by mobile money.\nUse your booking reference.", $s['deposit_instructions'] );
		$this->assertSame( 'Confirmed for {date}', $s['templates']['confirmed']['subject'] );
		$this->assertTrue( $s['templates']['confirmed']['enabled'] );
		$this->assertFalse( $s['templates']['thank_you']['enabled'], 'an unticked box switches the email off' );
		$this->assertSame( 'studio@ishot.example', Settings::studioAddress() );
	}

	public function test_an_invalid_address_saves_nothing(): void {
		$errors = EmailSettingsForm::save( self::valid( array( 'email_studio_address' => 'not-an-address' ) ) );

		$this->assertArrayHasKey( 'email_studio_address', $errors );
		$this->assertSame( '', Settings::all()['email_from_address'], 'nothing saved' );
	}

	public function test_subjects_are_single_line_and_bounded_and_unknown_templates_are_ignored(): void {
		$long = str_repeat( 'x', 151 );
		$this->assertArrayHasKey(
			'template_confirmed_subject',
			EmailSettingsForm::save( self::valid( array( 'templates' => array( 'confirmed' => array( 'subject' => $long ) ) ) ) )
		);

		$this->assertSame(
			array(),
			EmailSettingsForm::save(
				self::valid(
					array(
						'templates' => array(
							'confirmed' => array( 'enabled' => '1', 'subject' => "Confirmed\r\nBcc: x@example.com" ),
							'made_up'   => array( 'subject' => 'nope' ),
						),
					)
				)
			)
		);
		$templates = Settings::all()['templates'];
		$this->assertStringNotContainsString( "\n", $templates['confirmed']['subject'] );
		$this->assertArrayNotHasKey( 'made_up', $templates );
	}

	public function test_currency_and_manage_url_are_validated(): void {
		$this->assertArrayHasKey( 'currency', EmailSettingsForm::save( self::valid( array( 'currency' => 'dollars' ) ) ) );
		$this->assertArrayHasKey( 'manage_url', EmailSettingsForm::save( self::valid( array( 'manage_url' => 'javascript:alert(1)' ) ) ) );
	}

	public function test_only_managers_can_save_email_settings(): void {
		add_filter(
			'wp_die_handler',
			static fn() => static function ( $message ) {
				throw new RuntimeException( is_string( $message ) ? $message : 'wp_die' );
			}
		);
		$this->actAs( 'ishot_photographer' );
		$_POST = $_REQUEST = self::valid() + array( '_wpnonce' => wp_create_nonce( EmailSettingsScreen::POST_SAVE ) );

		try {
			EmailSettingsScreen::handleSave();
			$this->fail( 'Expected the handler to stop.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'not allow', $e->getMessage() );
		}
		$this->assertSame( '', Settings::all()['email_from_address'] );

		$this->expectException( RuntimeException::class );
		EmailSettingsScreen::handleFeedKey();
	}

	public function test_the_emails_screen_is_registered_for_managers_only(): void {
		global $submenu;
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		foreach ( array( 'ishot_manager' => true, 'ishot_photographer' => false ) as $role => $expected ) {
			unset( $submenu[ Menu::SLUG ] );
			$this->actAs( $role );
			Menu::register();
			$slugs = array_column( $submenu[ Menu::SLUG ] ?? array(), 2 );
			$this->assertSame( $expected, in_array( Menu::EMAIL_SLUG, $slugs, true ), $role );
		}
	}

	private static function feed( ?string $key ): \WP_REST_Response|WP_Error {
		$request = new WP_REST_Request( 'GET', '/ishot/v1/calendar.ics' );
		if ( null !== $key ) {
			$request->set_query_params( array( 'key' => $key ) );
		}
		return rest_get_server()->dispatch( $request );
	}

	public function test_the_calendar_feed_needs_the_current_secret_key(): void {
		$this->assertSame( 404, self::feed( null )->get_status() );
		$this->assertSame( 404, self::feed( str_repeat( 'a', 64 ) )->get_status(), 'no key configured yet' );

		$old = CalendarFeed::regenerateKey();
		$this->assertNotSame( $old, get_option( CalendarFeed::OPTION ), 'only a hash is stored' );
		$this->assertSame( 200, self::feed( $old )->get_status() );

		$new = CalendarFeed::regenerateKey();
		$this->assertSame( 404, self::feed( $old )->get_status(), 'regenerating revokes the old URL' );
		$this->assertSame( 200, self::feed( $new )->get_status() );
	}

	public function test_the_feed_lists_live_bookings_and_blocks_without_client_contact_details(): void {
		$requested = $this->bookingAt( gmdate( 'Y-m-d 10:00:00', strtotime( '+3 days' ) ), array( 'name' => 'Ada Test', 'email' => 'ada@example.com' ) );
		$confirmed = $this->bookingAt( gmdate( 'Y-m-d 10:00:00', strtotime( '+4 days' ) ), array( 'name' => 'Bea Test', 'email' => 'bea@example.com' ) );
		$this->repo->transition( $confirmed, BookingStatus::Confirmed, 1, 1 );
		$cancelled = $this->bookingAt( gmdate( 'Y-m-d 10:00:00', strtotime( '+5 days' ) ), array( 'name' => 'Cy Gone' ) );
		$this->repo->transition( $cancelled, BookingStatus::Cancelled, 1, 1 );
		$this->bookingAt( gmdate( 'Y-m-d 10:00:00', strtotime( '+6 days' ) ), array( 'kind' => 'block', 'status' => BookingStatus::Confirmed ) );

		$response = self::feed( CalendarFeed::regenerateKey() );
		$ics      = (string) $response->get_data();
		$this->assertStringStartsWith( 'text/calendar', $response->get_headers()['Content-Type'] );
		$this->assertStringContainsString( 'METHOD:PUBLISH', $ics );
		$this->assertSame( 3, substr_count( $ics, 'BEGIN:VEVENT' ), 'requested, confirmed and the block; not the cancelled one' );
		$this->assertStringContainsString( 'Ada Test', $ics );
		$this->assertStringContainsString( 'STATUS:TENTATIVE', $ics );
		$this->assertStringContainsString( 'STATUS:CONFIRMED', $ics );
		$this->assertStringNotContainsString( 'Cy Gone', $ics );
		$this->assertStringNotContainsString( '@example.com', $ics, 'no client email addresses in a shared calendar' );
		$this->assertStringContainsString( "UID:booking-{$requested}@", $ics );
	}

	public function test_the_booking_screen_shows_each_email_and_any_delivery_failure(): void {
		Settings::update( array( 'email_from_address' => 'bookings@ishot.example' ) );
		$id = $this->bookingAt( '2026-12-14 10:00:00', array( 'email' => 'ada@example.com' ) );
		add_filter(
			'pre_wp_mail',
			static function () {
				do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'SMTP connect() failed for ada@example.com' ) );
				return false;
			}
		);
		( new Dispatcher() )->sendDue( new DateTimeImmutable( '2026-12-01 08:00:00', new DateTimeZone( 'UTC' ) ) );
		$this->actAs( 'ishot_manager' );

		ob_start();
		BookingScreen::render( $id, new DateTimeImmutable( '2026-12-01 08:00:00', new DateTimeZone( 'UTC' ) ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Emails', $html );
		$this->assertStringContainsString( 'Request received (client)', $html );
		$this->assertStringContainsString( 'SMTP connect() failed', $html );
		$this->assertStringContainsString( 'Retrying', $html );
		$this->assertStringNotContainsString( 'failed for ada@example.com', $html, 'the stored error is redacted' );
	}

	public function test_client_and_staff_api_responses_never_include_secrets(): void {
		global $wpdb;
		$id    = $this->bookingAt( '2026-12-14 10:00:00' );
		$token = bin2hex( random_bytes( 32 ) );
		$hash  = hash( 'sha256', $token );
		$wpdb->update( $wpdb->prefix . 'ishot_bookings', array( 'public_token_hash' => $hash, 'idempotency_key' => 'idem-123' ), array( 'id' => $id ) );

		$client = rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/ishot/v1/bookings/{$token}" ) );
		$this->actAs( 'ishot_manager' );
		$staff = rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/ishot/v1/staff/bookings/{$id}" ) );

		foreach ( array( 'client' => $client, 'staff' => $staff ) as $who => $response ) {
			$this->assertSame( 200, $response->get_status(), $who );
			$json = wp_json_encode( $response->get_data() );
			$this->assertStringNotContainsString( $hash, $json, "{$who}: token hash" );
			$this->assertStringNotContainsString( $token, $json, "{$who}: token" );
			$this->assertStringNotContainsString( 'idem-123', $json, "{$who}: idempotency key" );
			$this->assertStringNotContainsString( 'snapshot', $json, "{$who}: outbox internals" );
		}
	}
}
