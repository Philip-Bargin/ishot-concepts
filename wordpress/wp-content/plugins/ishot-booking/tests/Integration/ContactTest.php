<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Contact\Enquiries;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Privacy\PersonalData;
use IShot\Booking\Privacy\Retention;
use IShot\Booking\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/** POST /ishot/v1/contact: validated, consented, spam-resistant enquiries that are never lost. */
final class ContactTest extends WP_UnitTestCase {

	use BookingFixtures;

	/** @var array<int, array<string, mixed>> */
	private array $sent = array();

	public function set_up(): void {
		parent::set_up();
		$this->resetBookings();
		$this->sent = array();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 250 );
		Settings::update( array( 'email_studio_address' => 'studio@example.com', 'email_from_address' => 'bookings@example.com' ) );
		add_action( 'phpmailer_init', array( $this, 'capture' ), PHP_INT_MAX );
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		remove_action( 'phpmailer_init', array( $this, 'capture' ), PHP_INT_MAX );
		remove_all_filters( 'pre_wp_mail' );
		parent::tear_down();
	}

	/** @param \PHPMailer\PHPMailer\PHPMailer $mailer */
	public function capture( $mailer ): void {
		$this->sent[] = array(
			'to'       => array_column( $mailer->getToAddresses(), 0 ),
			'reply_to' => array_keys( $mailer->getReplyToAddresses() ),
			'bcc'      => $mailer->getBccAddresses(),
			'subject'  => $mailer->Subject,
			'body'     => $mailer->Body,
		);
	}

	private static function valid( array $override = array() ): array {
		return $override + array(
			'name'    => 'Ama Mensah',
			'email'   => 'ama@example.com',
			'phone'   => '0770 000 000',
			'enquiry' => 'Weddings, renewals & engagements',
			'message' => 'We are getting married in March and would love to talk about coverage.',
			'consent' => true,
		);
	}

	private function post( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/ishot/v1/contact' );
		$request->set_body_params( $body );
		return rest_do_request( $request );
	}

	/** @return array<int, array<string, mixed>> */
	private static function rows(): array {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . Schema::enquiries() . ' ORDER BY id', ARRAY_A ); // phpcs:ignore
	}

	private static function now( string $utc = 'now' ): DateTimeImmutable {
		return new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) );
	}

	public function test_a_valid_enquiry_is_stored_then_delivered_to_the_studio(): void {
		$response = $this->post( self::valid() );

		$this->assertSame( 202, $response->get_status() );
		$this->assertCount( 1, self::rows() );
		$this->assertSame( 'pending', self::rows()[0]['status'] );
		$this->assertNotNull( self::rows()[0]['consent_at'] );

		$this->assertSame( 1, ( new Enquiries() )->sendDue( self::now( '+1 minute' ) ) );
		$this->assertSame( 'sent', self::rows()[0]['status'] );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( array( 'studio@example.com' ), $this->sent[0]['to'] );
		$this->assertSame( array( 'ama@example.com' ), $this->sent[0]['reply_to'] );
		$this->assertStringContainsString( 'getting married in March', $this->sent[0]['body'] );
		$this->assertSame( 0, ( new Enquiries() )->sendDue( self::now( '+2 minutes' ) ), 'never sent twice' );
	}

	/** @return array<string, array{array<string, mixed>}> */
	public static function invalid(): array {
		return array(
			'no consent'     => array( array( 'consent' => false ) ),
			'bad email'      => array( array( 'email' => 'not-an-email' ) ),
			'short message'  => array( array( 'message' => 'hi' ) ),
			'long message'   => array( array( 'message' => str_repeat( 'a', 2001 ) ) ),
			'no name'        => array( array( 'name' => '' ) ),
		);
	}

	/** @dataProvider invalid */
	public function test_invalid_input_is_rejected_and_nothing_is_stored( array $override ): void {
		$this->assertSame( 400, $this->post( self::valid( $override ) )->get_status() );
		$this->assertSame( array(), self::rows() );
	}

	public function test_a_filled_honeypot_looks_accepted_but_stores_and_sends_nothing(): void {
		$this->assertSame( 202, $this->post( self::valid( array( 'website' => 'http://spam.example' ) ) )->get_status() );
		$this->assertSame( array(), self::rows() );
	}

	public function test_each_address_is_rate_limited(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertSame( 202, $this->post( self::valid() )->get_status() );
		}
		$this->assertSame( 429, $this->post( self::valid() )->get_status() );
		$this->assertCount( 5, self::rows() );
	}

	public function test_header_injection_through_the_form_is_neutralised(): void {
		$this->post( self::valid( array( 'enquiry' => "Weddings\r\nBcc: victim@example.com", 'name' => "Ama\nBcc: victim@example.com" ) ) );
		( new Enquiries() )->sendDue( self::now( '+1 minute' ) );

		$this->assertSame( array(), $this->sent[0]['bcc'] );
		$this->assertStringNotContainsString( "\n", $this->sent[0]['subject'] );
	}

	public function test_a_failed_send_is_retried_with_backoff_and_finally_marked_failed(): void {
		$this->post( self::valid() );
		add_filter(
			'pre_wp_mail',
			static function () {
				do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'SMTP down' ) );
				return false;
			}
		);
		$t = self::now( '+1 minute' );
		( new Enquiries() )->sendDue( $t );
		$row = self::rows()[0];
		$this->assertSame( array( 'pending', '1', 'SMTP down' ), array( $row['status'], $row['attempts'], $row['last_error'] ) );
		$this->assertGreaterThan( $t->format( 'Y-m-d H:i:s' ), $row['next_attempt_at'] );
		$this->assertSame( 0, ( new Enquiries() )->sendDue( $t ), 'not retried before its back-off' );

		for ( $i = 0; $i < Enquiries::MAX_ATTEMPTS; $i++ ) {
			$t = $t->modify( '+1 day' );
			( new Enquiries() )->sendDue( $t );
		}
		$this->assertSame( 'failed', self::rows()[0]['status'] );
		$this->assertSame( 1, ( new Enquiries() )->countFailed() );
	}

	public function test_enquiries_are_included_in_personal_data_export_and_erasure(): void {
		$this->post( self::valid() );
		$export = ( new PersonalData() )->export( 'ama@example.com' );
		$this->assertContains( 'ishot-enquiries', array_column( $export['data'], 'group_id' ) );

		$erase = ( new PersonalData() )->erase( 'ama@example.com' );
		$this->assertTrue( $erase['items_removed'] );
		$this->assertSame( array(), self::rows() );
	}

	public function test_staff_with_booking_management_rights_can_read_enquiries_escaped_and_others_cannot(): void {
		// The API strips tags on the way in; store a raw value to prove the screen escapes on the way out.
		$this->post( self::valid( array( 'name' => '<script>alert(1)</script>Ama' ) ) );
		$this->assertSame( 'Ama', self::rows()[0]['name'], 'input is sanitised' );
		( new Enquiries() )->store( array( 'name' => '<script>alert(1)</script>Kofi', 'email' => 'kofi@example.com', 'phone' => '', 'enquiry_type' => '', 'message' => 'Raw row from another path.' ) );
		$this->makeStaff();
		$render = static function (): string {
			ob_start();
			\IShot\Booking\Admin\EnquiriesScreen::page();
			return (string) ob_get_clean();
		};

		$this->actAs( 'ishot_manager' );
		$html = $render();
		$this->assertStringContainsString( 'ama@example.com', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>alert', $html );

		$this->actAs( 'ishot_photographer' );
		$this->assertStringNotContainsString( 'ama@example.com', $render() );
		$this->removeStaff();
	}

	public function test_retention_deletes_old_enquiries(): void {
		global $wpdb;
		$this->post( self::valid() );
		$wpdb->update( Schema::enquiries(), array( 'created_at' => '2020-01-01 00:00:00' ), array( 'id' => self::rows()[0]['id'] ) );
		$this->post( self::valid( array( 'email' => 'recent@example.com' ) ) );

		( new Retention() )->purge( self::now() );

		$this->assertSame( array( 'recent@example.com' ), array_column( self::rows(), 'email' ) );
	}
}
