<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Accounts\ClientAccounts;
use IShot\Booking\Persistence\Schema;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Optional account creation in POST /bookings. Plain TestCase (like RestBookingsTest): the booking
 * API commits its own transactions, so this test cleans up after itself.
 */
final class BookingAccountsTest extends TestCase {

	private int $service;
	private string $monday;
	/** @var int[] */
	private array $users = array();
	/** @var array<int, array<string, mixed>> */
	private array $mails = array();

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
				'lead_time_hours' => 48,
				'weekly_hours'    => array_fill_keys( array( 1, 2, 3, 4, 5, 6 ), array( '09:00', '18:00' ) ),
			)
		);
		$this->service = wp_insert_post(
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
		$this->mails            = array();
		add_filter( 'pre_wp_mail', array( $this, 'captureMail' ), 10, 2 );
		wp_set_current_user( 0 );
		do_action( 'rest_api_init' );
	}

	protected function tearDown(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		remove_filter( 'pre_wp_mail', array( $this, 'captureMail' ), 10 );
		wp_set_current_user( 0 );
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		foreach ( get_users( array( 'search' => '*@accounts.example.com', 'search_columns' => array( 'user_email' ) ) ) as $user ) {
			wp_delete_user( $user->ID );
		}
		wp_delete_post( $this->service, true );
	}

	/** @param array<string, mixed> $atts */
	public function captureMail( $short_circuit, array $atts ): bool {
		$this->mails[] = $atts;
		return true;
	}

	private function book( string $email, string $time = '10:00', array $extra = array() ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/ishot/v1/bookings' );
		$request->set_body_params(
			$extra + array(
				'service' => $this->service,
				'start'   => "{$this->monday}T{$time}",
				'name'    => 'Ada Client',
				'email'   => $email,
				'consent' => true,
			)
		);
		return rest_do_request( $request );
	}

	private static function bookingUser( WP_REST_Response $response ): ?int {
		global $wpdb;
		$id = $wpdb->get_var( 'SELECT client_user_id FROM ' . Schema::bookings() . ' ORDER BY id DESC LIMIT 1' ); // phpcs:ignore
		return null === $id ? null : (int) $id;
	}

	public function test_guests_can_still_book_without_an_account(): void {
		$response = $this->book( 'guest@accounts.example.com' );
		$this->assertSame( 201, $response->get_status() );
		$this->assertNull( self::bookingUser( $response ) );
		$this->assertFalse( get_user_by( 'email', 'guest@accounts.example.com' ) );
		$this->assertArrayNotHasKey( 'account', $response->get_data() );
	}

	public function test_a_new_email_gets_a_client_account_that_is_verified_only_by_setting_a_password(): void {
		$response = $this->book( 'new@accounts.example.com', '10:00', array( 'create_account' => true ) );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'check_email', $response->get_data()['account'] );
		$user = get_user_by( 'email', 'new@accounts.example.com' );
		$this->assertNotFalse( $user );
		$this->assertSame( array( 'ishot_client' ), $user->roles );
		$this->assertSame( $user->ID, self::bookingUser( $response ) );
		$this->assertSame( 0, get_current_user_id(), 'booking never signs anyone in' );
		$this->assertNotSame( 'new@accounts.example.com', $user->user_login, 'the email is not the username' );

		$set_password = array_filter( $this->mails, static fn( array $m ) => 'new@accounts.example.com' === $m['to'] && str_contains( (string) $m['message'], 'action=rp' ) );
		$this->assertCount( 1, $set_password, 'a set-your-password link is emailed' );

		$this->assertNull( ClientAccounts::verifiedEmail( $user->ID ), 'not verified until the link is used' );
		do_action( 'after_password_reset', $user, 'a-new-password' );
		$this->assertSame( 'new@accounts.example.com', ClientAccounts::verifiedEmail( $user->ID ) );
	}

	public function test_the_set_password_email_comes_from_the_studio_sender_not_the_wordpress_default(): void {
		\IShot\Booking\Settings::update( array( 'email_from_address' => 'bookings@studio.example', 'email_from_name' => 'iShot Concept' ) );
		$this->book( 'sender@accounts.example.com', '10:00', array( 'create_account' => true ) );

		$mail    = array_values( array_filter( $this->mails, static fn( array $m ) => 'sender@accounts.example.com' === $m['to'] ) )[0];
		$headers = implode( "\n", (array) $mail['headers'] );
		$this->assertStringContainsString( 'From: iShot Concept <bookings@studio.example>', $headers );
	}

	public function test_an_existing_account_is_neither_revealed_nor_attached(): void {
		$existing      = wp_insert_user( array( 'user_login' => uniqid( 'existing' ), 'user_email' => 'taken@accounts.example.com', 'user_pass' => wp_generate_password(), 'role' => 'ishot_client' ) );
		$this->users[] = $existing;

		$taken = $this->book( 'taken@accounts.example.com', '10:00', array( 'create_account' => true ) );
		$fresh = $this->book( 'fresh@accounts.example.com', '12:00', array( 'create_account' => true ) );

		$this->assertSame( $fresh->get_data()['account'], $taken->get_data()['account'], 'same answer either way' );
		$this->assertSame( array_keys( $fresh->get_data() ), array_keys( $taken->get_data() ) );
		global $wpdb;
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SELECT client_user_id FROM ' . Schema::bookings() . ' WHERE client_email = %s', 'taken@accounts.example.com' ) ) ); // phpcs:ignore
		$this->assertCount( 1, get_users( array( 'search' => 'taken@accounts.example.com', 'search_columns' => array( 'user_email' ) ) ) );
		$this->assertSame( array(), array_filter( $this->mails, static fn( array $m ) => 'taken@accounts.example.com' === $m['to'] && str_contains( (string) $m['message'], 'action=rp' ) ) );
	}

	public function test_a_signed_in_client_has_the_booking_linked_to_their_account(): void {
		$me            = wp_insert_user( array( 'user_login' => uniqid( 'me' ), 'user_email' => 'me@accounts.example.com', 'user_pass' => wp_generate_password(), 'role' => 'ishot_client' ) );
		$this->users[] = $me;
		wp_set_current_user( $me );

		$response = $this->book( 'me@accounts.example.com', '10:00', array( 'create_account' => true ) );
		$this->assertSame( 'linked', $response->get_data()['account'] );
		$this->assertSame( $me, self::bookingUser( $response ) );
	}

	public function test_signed_in_staff_bookings_are_not_linked_to_the_staff_account(): void {
		$staff         = wp_insert_user( array( 'user_login' => uniqid( 'mgr' ), 'user_email' => 'mgr@accounts.example.com', 'user_pass' => wp_generate_password(), 'role' => 'ishot_manager' ) );
		$this->users[] = $staff;
		wp_set_current_user( $staff );

		$response = $this->book( 'someone@accounts.example.com' );
		$this->assertNull( self::bookingUser( $response ) );
	}

	public function test_account_creation_is_rate_limited_per_address_without_blocking_the_booking(): void {
		$statuses = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$response   = $this->book( "burst{$i}@accounts.example.com", sprintf( '%02d:00', 10 + $i ), array( 'create_account' => true ) );
			$statuses[] = array( $response->get_status(), $response->get_data()['account'] );
		}
		$this->assertSame( array( 201, 'not_created' ), $statuses[3] );
		$this->assertFalse( get_user_by( 'email', 'burst3@accounts.example.com' ) );
		foreach ( array( 0, 1, 2 ) as $i ) {
			$this->assertNotFalse( get_user_by( 'email', "burst{$i}@accounts.example.com" ), "account {$i} within the limit" );
		}
	}

	public function test_no_account_is_created_for_bots_or_failed_bookings(): void {
		$this->book( 'bot@accounts.example.com', '10:00', array( 'create_account' => true, 'website' => 'http://spam' ) );
		$this->book( 'first@accounts.example.com', '14:00' );
		$clash = $this->book( 'clash@accounts.example.com', '14:00', array( 'create_account' => true ) );

		$this->assertSame( 409, $clash->get_status() );
		$this->assertFalse( get_user_by( 'email', 'bot@accounts.example.com' ) );
		$this->assertFalse( get_user_by( 'email', 'clash@accounts.example.com' ) );
	}
}
