<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Application\ClientBookings;
use IShot\Booking\Application\BookingRequests;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Notifications\Dispatcher;
use IShot\Booking\Notifications\Outbox;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Settings;
use WP_Error;
use WP_UnitTestCase;

/** Delivery from the outbox: sending, retries, duplicates, ordering, links and calendar files. */
final class NotificationDeliveryTest extends WP_UnitTestCase {

	use BookingFixtures;

	/** @var array<int, array<string, mixed>> Messages as PHPMailer was about to send them. */
	private array $sent = array();

	public function set_up(): void {
		parent::set_up();
		$this->resetBookings();
		$this->sent = array();
		Settings::update(
			array(
				'email_studio_address' => 'studio@ishot.example',
				'email_from_name'      => 'iShot Concept',
				'email_from_address'   => 'bookings@ishot.example',
				'deposit_instructions' => 'Send the deposit by mobile money to 0770 000 000.',
			)
		);
		add_action( 'phpmailer_init', array( $this, 'capture' ), PHP_INT_MAX );
	}

	public function tear_down(): void {
		remove_action( 'phpmailer_init', array( $this, 'capture' ), PHP_INT_MAX );
		remove_all_filters( 'pre_wp_mail' );
		parent::tear_down();
	}

	/** @param \PHPMailer\PHPMailer\PHPMailer $mailer */
	public function capture( $mailer ): void {
		$this->sent[] = array(
			'to'          => array_column( $mailer->getToAddresses(), 0 ),
			'from'        => $mailer->From,
			'subject'     => $mailer->Subject,
			'html'        => $mailer->Body,
			'text'        => $mailer->AltBody,
			'type'        => $mailer->ContentType,
			'attachments' => $mailer->getAttachments(),
		);
	}

	private static function now( string $utc = '2026-12-01 08:00:00' ): DateTimeImmutable {
		return new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) );
	}

	/** @return array<string, array<string, mixed>> template => outbox row */
	private function rows( int $booking_id ): array {
		return array_column( ( new Outbox() )->forBooking( $booking_id ), null, 'template' );
	}

	/** @return array<string, mixed> The one captured message to $to whose subject contains $needle. */
	private function message( string $to, string $needle = '' ): array {
		$found = array_values( array_filter( $this->sent, static fn( $m ) => in_array( $to, $m['to'], true ) && ( '' === $needle || str_contains( $m['subject'], $needle ) ) ) );
		$this->assertCount( 1, $found, "one message to {$to} ({$needle})" );
		return $found[0];
	}

	private static function calendarOf( array $message ): ?string {
		foreach ( $message['attachments'] as $a ) {
			if ( str_ends_with( (string) $a[1], '.ics' ) ) {
				return (string) $a[0];
			}
		}
		return null;
	}

	private function failNextSends( string $error ): void {
		add_filter(
			'pre_wp_mail',
			static function () use ( $error ) {
				do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', $error ) );
				return false;
			}
		);
	}

	public function test_due_emails_are_sent_once_to_the_right_people(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00', array( 'name' => 'Ada Test', 'email' => 'ada@example.com' ) );

		$this->assertSame( 2, ( new Dispatcher() )->sendDue( self::now() ) );
		$this->assertSame( 0, ( new Dispatcher() )->sendDue( self::now() ), 'a second run sends nothing again' );

		$this->assertCount( 2, $this->sent );
		$client = $this->message( 'ada@example.com' );
		$studio = $this->message( 'studio@ishot.example' );
		$this->assertSame( 'bookings@ishot.example', $client['from'] );
		$this->assertStringContainsString( 'Ada Test', $studio['text'] );
		foreach ( $this->rows( $id ) as $row ) {
			$this->assertSame( 'sent', $row['status'] );
			$this->assertSame( '1', $row['attempts'] );
			$this->assertNotNull( $row['sent_at'] );
		}
	}

	public function test_every_email_has_an_html_body_and_a_plain_text_alternative(): void {
		$this->bookingAt( '2026-12-14 10:00:00', array( 'name' => '<script>alert(1)</script> Ada' ) );
		( new Dispatcher() )->sendDue( self::now() );

		foreach ( $this->sent as $m ) {
			$this->assertSame( 'text/html', $m['type'] );
			$this->assertStringContainsString( '<html', $m['html'] );
			$this->assertNotSame( '', trim( $m['text'] ) );
			$this->assertDoesNotMatchRegularExpression( '#</?(p|br|table|tr|td|a|h1|html|body)[\s/>]#i', $m['text'], 'none of the layout markup leaks into the plain text' );
			$this->assertStringNotContainsString( '<script>', $m['html'], 'client input is escaped in HTML' );
		}
	}

	public function test_the_client_link_is_a_fresh_working_token_that_is_never_stored_in_clear(): void {
		global $wpdb;
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		( new Dispatcher() )->sendDue( self::now() );

		$client = $this->message( 'ada@example.com' );
		$this->assertSame( 1, preg_match( '/#token=([0-9a-f]{64})/', $client['text'], $m ), 'manage link in the plain text' );
		$token = $m[1];
		$this->assertStringContainsString( $token, $client['html'] );

		foreach ( Schema::tables() as $table ) {
			$hits = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE CONCAT_WS('|', " . implode( ',', $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ) ) . ") LIKE '%{$token}%'" ); // phpcs:ignore
			$this->assertSame( 0, $hits, "raw token stored in {$table}" );
		}

		$bookings = new ClientBookings( $this->repo, new BookingRequests( $this->repo, Settings::policy(), 48 ), Settings::policy(), 72 );
		$found    = $bookings->find( $token, self::now() );
		$this->assertSame( $id, (int) $found['id'], 'the emailed link opens the booking' );

		$studio = $this->message( 'studio@ishot.example' );
		$this->assertStringNotContainsString( '#token=', $studio['html'], 'staff get the admin link, never the client credential' );
	}

	public function test_a_failed_send_is_retried_later_with_the_error_logged_without_personal_data(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00', array( 'email' => 'ada@example.com' ) );
		$this->failNextSends( 'SMTP Error: The following recipients failed: ada@example.com' );

		$this->assertSame( 0, ( new Dispatcher() )->sendDue( self::now() ) );
		$row = $this->rows( $id )['request_received'];
		$this->assertSame( 'pending', $row['status'] );
		$this->assertSame( '1', $row['attempts'] );
		$this->assertGreaterThan( '2026-12-01 08:00:00', $row['next_attempt_at'], 'backed off' );
		$this->assertStringContainsString( 'recipients failed', $row['last_error'] );
		$this->assertStringNotContainsString( 'ada@example.com', $row['last_error'] );

		remove_all_filters( 'pre_wp_mail' );
		$this->assertSame( 0, ( new Dispatcher() )->sendDue( self::now( '2026-12-01 08:01:00' ) ), 'not before its retry time' );
		$this->assertSame( 2, ( new Dispatcher() )->sendDue( self::now( '2026-12-01 09:00:00' ) ) );
		$this->assertSame( 'sent', $this->rows( $id )['request_received']['status'] );
		$this->assertSame( '2', $this->rows( $id )['request_received']['attempts'] );
	}

	public function test_an_email_that_keeps_failing_is_marked_failed_and_recorded_in_the_audit_log(): void {
		global $wpdb;
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->failNextSends( 'Could not connect to SMTP host.' );

		$at = self::now();
		for ( $i = 0; $i < Dispatcher::MAX_ATTEMPTS + 2; $i++ ) {
			( new Dispatcher() )->sendDue( $at );
			$at = $at->modify( '+1 day' );
		}

		$row = $this->rows( $id )['request_received'];
		$this->assertSame( 'failed', $row['status'] );
		$this->assertSame( (string) Dispatcher::MAX_ATTEMPTS, $row['attempts'] );
		$logged = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Schema::events() . " WHERE booking_id = %d AND action = 'notification_failed'", $id ), ARRAY_A ); // phpcs:ignore
		$this->assertCount( 2, $logged, 'one audit entry per failed email' );
		$this->assertStringContainsString( 'request_received', $logged[0]['payload'] );
	}

	public function test_an_email_claimed_by_another_worker_is_not_sent_twice(): void {
		global $wpdb;
		$id  = $this->bookingAt( '2026-12-14 10:00:00' );
		$row = $this->rows( $id )['request_received'];
		$wpdb->update( Schema::notifications(), array( 'status' => 'sending', 'claimed_at' => '2026-12-01 07:59:00' ), array( 'id' => $row['id'] ) );

		( new Dispatcher() )->sendDue( self::now() );
		$this->assertCount( 1, $this->sent, 'only the unclaimed studio email' );

		$this->assertSame( 0, ( new Dispatcher() )->recoverStale( self::now( '2026-12-01 08:05:00' ) ), 'a recent claim is left alone' );
		$this->assertSame( 1, ( new Dispatcher() )->recoverStale( self::now( '2026-12-01 09:00:00' ) ), 'an abandoned claim is requeued' );
		( new Dispatcher() )->sendDue( self::now( '2026-12-01 09:00:00' ) );
		$this->assertSame( 'sent', $this->rows( $id )['request_received']['status'] );
	}

	public function test_a_later_email_waits_while_an_earlier_one_to_the_same_person_is_retrying(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->failNextSends( 'temporary failure' );
		( new Dispatcher() )->sendDue( self::now() );
		remove_all_filters( 'pre_wp_mail' );

		$this->repo->transition( $id, BookingStatus::Confirmed, 1, 1 );
		( new Dispatcher() )->sendDue( self::now( '2026-12-01 08:01:00' ) );
		$this->assertSame( 'pending', $this->rows( $id )['confirmed']['status'], 'held behind the unsent request email' );

		( new Dispatcher() )->sendDue( self::now( '2026-12-01 10:00:00' ) );
		$subjects = array_column( array_filter( $this->sent, static fn( $m ) => in_array( 'ada@example.com', $m['to'], true ) ), 'subject' );
		$this->assertCount( 2, $subjects );
		$this->assertSame( 'sent', $this->rows( $id )['confirmed']['status'] );
	}

	public function test_confirmation_reschedule_and_cancellation_share_one_calendar_event(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1, 1 );
		$this->repo->reschedule( $id, new DateTimeImmutable( '2026-12-15 13:00:00', new DateTimeZone( 'UTC' ) ), 2, 1 );
		$this->repo->transition( $id, BookingStatus::Cancelled, 3, 1, array(), array( 'by' => 'studio' ) );
		( new Dispatcher() )->sendDue( self::now() );

		$client = array_values( array_filter( $this->sent, static fn( $m ) => in_array( 'ada@example.com', $m['to'], true ) ) );
		$this->assertCount( 4, $client );
		$this->assertNull( self::calendarOf( $client[0] ), 'no calendar file for an unconfirmed request' );

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$uid  = "UID:booking-{$id}@{$host}";
		[ $confirmed, $moved, $cancelled ] = array_map( array( self::class, 'calendarOf' ), array_slice( $client, 1 ) );

		$this->assertStringContainsString( 'METHOD:REQUEST', $confirmed );
		$this->assertStringContainsString( 'DTSTART:20261214T100000Z', $confirmed );
		$this->assertStringContainsString( 'SEQUENCE:2', $confirmed );
		$this->assertStringContainsString( 'METHOD:REQUEST', $moved );
		$this->assertStringContainsString( 'DTSTART:20261215T130000Z', $moved );
		$this->assertStringContainsString( 'SEQUENCE:3', $moved );
		$this->assertStringContainsString( 'METHOD:CANCEL', $cancelled );
		$this->assertStringContainsString( 'STATUS:CANCELLED', $cancelled );
		$this->assertStringContainsString( 'SEQUENCE:4', $cancelled );
		foreach ( array( $confirmed, $moved, $cancelled ) as $ics ) {
			$this->assertStringContainsString( $uid, $ics, 'the same event identity throughout' );
		}
		$type = array_values( array_filter( $client[1]['attachments'], static fn( $a ) => str_ends_with( (string) $a[1], '.ics' ) ) )[0][4];
		$this->assertStringStartsWith( 'text/calendar', $type );
		$this->assertStringContainsString( 'method=REQUEST', $type );
	}

	public function test_deposit_emails_give_instructions_and_never_claim_payment_was_received(): void {
		global $wpdb;
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		( new \IShot\Booking\Application\StaffActions( $this->repo ) )->perform( $id, 'request_deposit', 1, 1, array( 'deposit_amount' => 50.0 ) );
		( new Dispatcher() )->sendDue( self::now() );

		$deposit = $this->message( 'ada@example.com', 'deposit' );
		$this->assertStringContainsString( 'mobile money to 0770 000 000', $deposit['text'] );
		$this->assertStringContainsString( '50.00', $deposit['text'] );
		$this->assertDoesNotMatchRegularExpression( '/received|\bpaid\b/i', $deposit['text'] );
		$this->assertSame( 'awaiting', $wpdb->get_var( $wpdb->prepare( 'SELECT deposit_status FROM ' . Schema::bookings() . ' WHERE id = %d', $id ) ) ); // phpcs:ignore

		$this->sent = array();
		( new \IShot\Booking\Application\StaffActions( $this->repo ) )->perform( $id, 'deposit_received', 2, 1 );
		( new Dispatcher() )->sendDue( self::now() );
		$this->assertStringContainsString( 'Your deposit of USD 50.00 has been received', $this->message( 'ada@example.com' )['text'] );
	}

	public function test_confirming_without_a_deposit_does_not_mention_one_received(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1, 1 );
		( new Dispatcher() )->sendDue( self::now() );

		$this->assertDoesNotMatchRegularExpression( '/received|\bpaid\b/i', $this->message( 'ada@example.com', 'onfirmed' )['text'] );
	}

	public function test_a_disabled_template_is_skipped_not_sent(): void {
		Settings::update( array( 'templates' => array( 'new_request' => array( 'enabled' => false ) ) ) );
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		( new Dispatcher() )->sendDue( self::now() );

		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'skipped', $this->rows( $id )['new_request']['status'] );
	}

	public function test_studio_edited_subject_and_intro_are_used_with_placeholders(): void {
		Settings::update(
			array(
				'templates' => array(
					'request_received' => array(
						'subject' => 'Thanks {client_name}!',
						'intro'   => 'We got your {service} request for {date}.',
					),
				),
			)
		);
		$this->bookingAt( '2026-12-14 10:00:00', array( 'name' => "Ada\r\nBcc: evil@example.com" ) );
		( new Dispatcher() )->sendDue( self::now() );

		$client = $this->message( 'ada@example.com' );
		$this->assertStringStartsWith( 'Thanks Ada', $client['subject'] );
		$this->assertStringNotContainsString( "\n", $client['subject'], 'no header injection through a name' );
		$this->assertStringContainsString( 'request for Monday 14 December 2026', $client['text'] );
	}
}
