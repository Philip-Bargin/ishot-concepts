<?php
namespace IShot\Booking\Contact;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Notifications\Mailer;
use IShot\Booking\Notifications\Message;
use IShot\Booking\Notifications\Queue;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Settings;
use wpdb;

/**
 * Contact-form enquiries. Each one is stored first and emailed to the studio afterwards, so a mail
 * outage delays an enquiry but never loses it: failed sends retry with back-off, and an enquiry
 * that still fails after MAX_ATTEMPTS stays in the database marked "failed" for staff to read.
 *
 * Nothing is ever emailed to the address typed into the form (that would make the form a spam
 * relay); the studio replies via the Reply-To header.
 */
final class Enquiries {

	public const SEND_HOOK    = 'ishot_booking_send_enquiries';
	public const MAX_ATTEMPTS = 5;

	/** Minutes to wait after the 1st, 2nd, 3rd and 4th failed attempt. */
	private const BACKOFF = array( 5, 15, 60, 240 );

	/** A send claimed longer ago than this was abandoned (process died) and is retried. */
	private const STALE_MINUTES = 10;

	private const DATETIME = 'Y-m-d H:i:s';

	private readonly wpdb $db;

	public function __construct( ?wpdb $db = null, private readonly Mailer $mailer = new Mailer() ) {
		global $wpdb;
		$this->db = $db ?? $wpdb;
	}

	/**
	 * @param array{name: string, email: string, phone: string, enquiry_type: string, message: string} $enquiry
	 */
	public function store( array $enquiry ): int {
		$now = gmdate( self::DATETIME );
		$this->db->insert(
			Schema::enquiries(),
			$enquiry + array(
				'consent_at'      => $now,
				'status'          => 'pending',
				'next_attempt_at' => $now,
				'created_at'      => $now,
			)
		);
		$id = (int) $this->db->insert_id;
		self::kick();
		return $id;
	}

	/** Asks Action Scheduler to deliver soon; Queue's five-minute tick is the safety net. */
	public static function kick(): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::SEND_HOOK, array(), Queue::GROUP, true );
		}
	}

	public static function run(): void {
		( new self() )->sendDue( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
	}

	/** @return int How many enquiries were delivered. */
	public function sendDue( DateTimeImmutable $now, int $limit = 20 ): int {
		$table   = Schema::enquiries();
		$now_sql = $now->setTimezone( new DateTimeZone( 'UTC' ) )->format( self::DATETIME );
		$stale   = $now->setTimezone( new DateTimeZone( 'UTC' ) )->modify( '-' . self::STALE_MINUTES . ' minutes' )->format( self::DATETIME );
		$this->db->query( $this->db->prepare( "UPDATE {$table} SET status = 'pending' WHERE status = 'sending' AND claimed_at < %s", $stale ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$ids  = $this->db->get_col( $this->db->prepare( "SELECT id FROM {$table} WHERE status = 'pending' AND next_attempt_at <= %s ORDER BY id LIMIT %d", $now_sql, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sent = 0;
		foreach ( $ids as $id ) {
			// Claim first: two workers can never send the same enquiry.
			$claimed = $this->db->query( $this->db->prepare( "UPDATE {$table} SET status = 'sending', claimed_at = %s WHERE id = %d AND status = 'pending'", $now_sql, (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 1 !== $claimed ) {
				continue;
			}
			$row   = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$error = $this->mailer->send( self::message( $row ) );
			if ( null === $error ) {
				$this->db->update( $table, array( 'status' => 'sent', 'sent_at' => $now_sql, 'last_error' => '' ), array( 'id' => (int) $id ) );
				++$sent;
				continue;
			}
			$attempts = (int) $row['attempts'] + 1;
			$this->db->update(
				$table,
				array(
					'status'          => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending',
					'attempts'        => $attempts,
					'last_error'      => mb_substr( $error, 0, 255 ),
					'next_attempt_at' => $now->setTimezone( new DateTimeZone( 'UTC' ) )->modify( '+' . self::BACKOFF[ min( $attempts, count( self::BACKOFF ) ) - 1 ] . ' minutes' )->format( self::DATETIME ),
				),
				array( 'id' => (int) $id )
			);
		}
		return $sent;
	}

	/** @param array<string, mixed> $row */
	private static function message( array $row ): Message {
		$type    = '' !== (string) $row['enquiry_type'] ? (string) $row['enquiry_type'] : __( 'General question', 'ishot-booking' );
		/* translators: %s: enquiry type */
		$subject = Mailer::headerText( sprintf( __( 'New enquiry: %s', 'ishot-booking' ), $type ) );
		$fields  = array(
			__( 'Name', 'ishot-booking' )     => (string) $row['name'],
			__( 'Email', 'ishot-booking' )    => (string) $row['email'],
			__( 'Phone', 'ishot-booking' )    => (string) $row['phone'],
			__( 'Enquiry', 'ishot-booking' )  => $type,
			__( 'Received', 'ishot-booking' ) => (string) $row['created_at'] . ' UTC',
		);
		$text = '';
		$html = '';
		foreach ( $fields as $label => $value ) {
			if ( '' !== $value ) {
				$text .= "{$label}: {$value}\n";
				$html .= '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
			}
		}
		$text .= "\n" . $row['message'] . "\n\n" . __( 'Reply to this email to answer the client directly.', 'ishot-booking' );
		$html .= '<p>' . nl2br( esc_html( (string) $row['message'] ) ) . '</p><p>' . esc_html__( 'Reply to this email to answer the client directly.', 'ishot-booking' ) . '</p>';

		return new Message( Settings::studioAddress(), $subject, $html, $text, array(), null, sanitize_email( (string) $row['email'] ) );
	}

	public function countFailed(): int {
		return (int) $this->db->get_var( 'SELECT COUNT(*) FROM ' . Schema::enquiries() . " WHERE status = 'failed'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** @return array<int, array<string, mixed>> */
	public function forEmail( string $email ): array {
		return $this->db->get_results( $this->db->prepare( 'SELECT * FROM ' . Schema::enquiries() . ' WHERE email = %s ORDER BY id', $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** @return array<int, array<string, mixed>> Newest first, for the admin list. */
	public function recent( int $limit = 100 ): array {
		return $this->db->get_results( $this->db->prepare( 'SELECT * FROM ' . Schema::enquiries() . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function deleteForEmail( string $email ): int {
		return (int) $this->db->delete( Schema::enquiries(), array( 'email' => $email ) );
	}

	public function deleteOlderThan( string $cutoff_utc ): int {
		return (int) $this->db->query( $this->db->prepare( 'DELETE FROM ' . Schema::enquiries() . ' WHERE created_at < %s', $cutoff_utc ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
