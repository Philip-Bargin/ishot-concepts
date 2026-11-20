<?php
namespace IShot\Booking\Privacy;

use IShot\Booking\Admin\View;
use IShot\Booking\Contact\Enquiries;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Settings;
use wpdb;

/**
 * Hooks bookings into WordPress's Tools → Export / Erase Personal Data.
 *
 * Bookings match by the client email they were made with, or by the WordPress account that
 * owns them. Erasing keeps upcoming live bookings (the studio still has to run the session) and
 * reports them as retained; everything else is anonymised (see Anonymiser).
 */
final class PersonalData {

	public const ID = 'ishot-booking';

	/** One request handles at most this many bookings; WordPress calls again for the next page. */
	private const PAGE = 100;

	private readonly wpdb $db;

	public function __construct( ?wpdb $db = null ) {
		global $wpdb;
		$this->db = $db ?? $wpdb;
	}

	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'addExporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'addEraser' ) );
		add_action( 'admin_init', array( self::class, 'policyText' ) );
	}

	/** @param array<string, array<string, mixed>> $exporters */
	public static function addExporter( array $exporters ): array {
		$exporters[ self::ID ] = array(
			'exporter_friendly_name' => __( 'iShot studio bookings', 'ishot-booking' ),
			'callback'               => array( new self(), 'export' ),
		);
		return $exporters;
	}

	/** @param array<string, array<string, mixed>> $erasers */
	public static function addEraser( array $erasers ): array {
		$erasers[ self::ID ] = array(
			'eraser_friendly_name' => __( 'iShot studio bookings', 'ishot-booking' ),
			'callback'             => array( new self(), 'erase' ),
		);
		return $erasers;
	}

	/** Suggested wording for the site's privacy policy page (Settings → Privacy → Policy guide). */
	public static function policyText(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$months = Settings::int( 'retention_months' );
		$keep   = 0 === $months
			? __( 'We keep booking records until you ask us to erase them.', 'ishot-booking' )
			/* translators: %d: number of months */
			: sprintf( __( 'We anonymise your name, contact details and notes %d months after your session. We keep the date, service and payment amount for our accounts.', 'ishot-booking' ), $months );
		wp_add_privacy_policy_content(
			__( 'iShot Booking', 'ishot-booking' ),
			'<p>' . esc_html__( 'When you request a session we store your name, email address, phone number, the location and any notes you give us. We use them to arrange and run the shoot and to email you about it. Deposits are paid offline; we record the amount and, when you give one, a payment reference.', 'ishot-booking' ) . '</p><p>' . esc_html( $keep ) . '</p>'
		);
	}

	/** @return array{data: array<int, array<string, mixed>>, done: bool} */
	public function export( string $email, int $page = 1 ): array {
		$rows  = $this->bookingsFor( $email, max( 1, $page ) );
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'group_id'    => self::ID,
				'group_label' => __( 'Studio bookings', 'ishot-booking' ),
				'item_id'     => self::ID . '-' . $row['id'],
				'data'        => $this->describe( $row ),
			);
		}
		if ( 1 === max( 1, $page ) ) {
			foreach ( ( new Enquiries( $this->db ) )->forEmail( sanitize_email( $email ) ) as $e ) {
				$items[] = array(
					'group_id'    => 'ishot-enquiries',
					'group_label' => __( 'Studio enquiries', 'ishot-booking' ),
					'item_id'     => 'ishot-enquiry-' . $e['id'],
					'data'        => self::fields(
						array(
							__( 'Name', 'ishot-booking' )  => (string) $e['name'],
							__( 'Email', 'ishot-booking' ) => (string) $e['email'],
							__( 'Phone', 'ishot-booking' ) => (string) $e['phone'],
							__( 'Enquiry', 'ishot-booking' ) => (string) $e['enquiry_type'],
							__( 'Message', 'ishot-booking' ) => (string) $e['message'],
							__( 'Sent', 'ishot-booking' )  => View::when( $e['created_at'] ),
						)
					),
				);
			}
		}
		return array(
			'data' => $items,
			'done' => count( $rows ) < self::PAGE,
		);
	}

	/**
	 * Anonymising removes rows from the match, so every call looks at the first page of
	 * still-identifiable bookings rather than paging by offset.
	 *
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase( string $email, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress eraser callback signature; every call restarts from the first identifiable booking.
		$anonymiser = new Anonymiser( $this->db );
		$now        = gmdate( 'Y-m-d H:i:s' );
		$actor      = get_current_user_id();
		$removed    = false;
		$retained   = false;
		$messages   = array();

		// Enquiries hold nothing the studio must keep: delete them outright.
		$removed = ( new Enquiries( $this->db ) )->deleteForEmail( sanitize_email( $email ) ) > 0;

		$rows = $this->bookingsFor( $email, 1, true );
		foreach ( $rows as $row ) {
			if ( self::isUpcoming( $row, $now ) ) {
				$retained   = true;
				$messages[] = sprintf(
					/* translators: 1: booking number, 2: session date and time */
					__( 'Booking #%1$d on %2$s was kept because the session has not happened yet. Cancel it first, or erase again after the session.', 'ishot-booking' ),
					(int) $row['id'],
					View::when( $row['start_utc'] )
				);
				continue;
			}
			$removed = $anonymiser->anonymise( (int) $row['id'], 'erasure_request', $actor > 0 ? $actor : null ) || $removed;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			// A full page that was all retained would otherwise be fetched forever.
			'done'           => count( $rows ) < self::PAGE || ! $removed,
		);
	}

	/** A live booking whose session is still ahead: erasing it would leave the studio blind. */
	private static function isUpcoming( array $row, string $now_utc ): bool {
		return BookingStatus::from( $row['status'] )->holdsSlot() && $row['end_utc'] > $now_utc;
	}

	/** @return array<int, array<string, mixed>> */
	private function bookingsFor( string $email, int $page, bool $only_identifiable = false ): array {
		$email = sanitize_email( $email );
		if ( '' === $email ) {
			return array();
		}
		$user    = get_user_by( 'email', $email );
		$user_id = false === $user ? 0 : (int) $user->ID;
		$where   = $only_identifiable ? ' AND anonymised_at IS NULL' : '';
		return $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM ' . Schema::bookings() . " WHERE kind = 'booking' AND ( client_email = %s OR ( %d > 0 AND client_user_id = %d ) ){$where} ORDER BY id LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
				$email,
				$user_id,
				$user_id,
				self::PAGE,
				( $page - 1 ) * self::PAGE
			),
			ARRAY_A
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<int, array{name: string, value: string}>
	 */
	private function describe( array $row ): array {
		$service = null === $row['service_id'] ? '' : html_entity_decode( get_the_title( (int) $row['service_id'] ), ENT_QUOTES, 'UTF-8' );
		$deposit = null === $row['deposit_amount'] ? '' : Settings::all()['currency'] . ' ' . number_format( (float) $row['deposit_amount'], 2 ) . ' (' . $row['deposit_status'] . ')';
		$fields  = array(
			__( 'Booking number', 'ishot-booking' )     => (string) $row['id'],
			__( 'Service', 'ishot-booking' )            => $service,
			__( 'Session', 'ishot-booking' )            => View::when( $row['start_utc'] ),
			__( 'Status', 'ishot-booking' )             => View::statusLabel( (string) $row['status'] ),
			__( 'Name', 'ishot-booking' )               => (string) $row['client_name'],
			__( 'Email', 'ishot-booking' )              => (string) $row['client_email'],
			__( 'Phone', 'ishot-booking' )              => (string) $row['client_phone'],
			__( 'Location', 'ishot-booking' )           => (string) $row['location'],
			__( 'Notes', 'ishot-booking' )              => (string) $row['notes'],
			__( 'Deposit', 'ishot-booking' )            => $deposit,
			__( 'Payment reference', 'ishot-booking' )  => (string) $row['deposit_reference'],
			__( 'Requested', 'ishot-booking' )          => View::when( $row['created_at'] ),
			__( 'Emails sent to you', 'ishot-booking' ) => $this->emailsSent( (int) $row['id'] ),
		);
		return self::fields( $fields );
	}

	/**
	 * @param array<string, string> $fields Label => value; empty values are left out.
	 * @return array<int, array{name: string, value: string}>
	 */
	private static function fields( array $fields ): array {
		$out = array();
		foreach ( $fields as $name => $value ) {
			if ( '' !== $value ) {
				$out[] = array(
					'name'  => $name,
					'value' => $value,
				);
			}
		}
		return $out;
	}

	private function emailsSent( int $booking_id ): string {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT template, sent_at FROM ' . Schema::notifications() . " WHERE booking_id = %d AND recipient = 'client' AND status = 'sent' ORDER BY id", $booking_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		return implode( '; ', array_map( static fn( array $r ): string => $r['template'] . ' (' . View::when( $r['sent_at'] ) . ')', $rows ) );
	}
}
