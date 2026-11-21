<?php
namespace IShot\Booking\Admin;

use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Settings;

/**
 * CSV export of the bookings list.
 *
 * - Client contact details are personal data: only manage_ishot_bookings (managers, admins) may
 *   export, the request needs a nonce, and every export is logged (who, when, how many rows).
 * - The file holds only what bookkeeping needs. Free-text notes, token hashes, idempotency keys
 *   and audit details are left out.
 * - Spreadsheet formula injection: any cell starting with = + - @ tab or carriage return is
 *   prefixed with an apostrophe, so Excel, Sheets and LibreOffice show it as text, never run it.
 */
final class CsvExport {

	public const CAP        = 'manage_ishot_bookings';
	public const POST       = 'ishot_export_bookings';
	public const NONCE      = 'ishot_export_bookings';
	public const LOG_OPTION = 'ishot_booking_export_log';
	private const LOG_KEEP  = 50;
	private const BATCH     = 500;

	public static function allowed(): bool {
		return current_user_can( self::CAP );
	}

	public static function cell( string $value ): string {
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
	}

	/**
	 * @param array{status?: string, when?: string, q?: string} $filters
	 * @return array<int, string[]> Header row, then one row per booking.
	 */
	public static function rows( array $filters ): array {
		global $wpdb;
		$repo  = new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
		$out   = array(
			array(
				__( 'Booking', 'ishot-booking' ),
				__( 'Status', 'ishot-booking' ),
				__( 'Service', 'ishot-booking' ),
				__( 'Start', 'ishot-booking' ),
				__( 'End', 'ishot-booking' ),
				__( 'Client', 'ishot-booking' ),
				__( 'Email', 'ishot-booking' ),
				__( 'Phone', 'ishot-booking' ),
				__( 'Location', 'ishot-booking' ),
				__( 'Deposit', 'ishot-booking' ),
				__( 'Deposit status', 'ishot-booking' ),
				__( 'Payment reference', 'ishot-booking' ),
				__( 'Requested', 'ishot-booking' ),
			),
		);
		$query = array(
			'status' => (string) ( $filters['status'] ?? '' ),
			'when'   => (string) ( $filters['when'] ?? 'all' ),
			'q'      => (string) ( $filters['q'] ?? '' ),
			'now'    => gmdate( 'Y-m-d H:i:s' ),
		);
		for ( $page = 1; ; $page++ ) {
			$batch = $repo->search( $query, self::BATCH, $page )['rows'];
			foreach ( $batch as $row ) {
				$out[] = array_map(
					static fn( $v ) => self::cell( (string) $v ),
					array(
						$row['id'],
						View::statusLabel( (string) $row['status'] ),
						View::serviceTitle( $row['service_id'] ),
						View::when( $row['start_utc'], 'Y-m-d H:i' ),
						View::when( $row['end_utc'], 'Y-m-d H:i' ),
						$row['client_name'],
						$row['client_email'],
						$row['client_phone'],
						$row['location'],
						null === $row['deposit_amount'] ? '' : number_format( (float) $row['deposit_amount'], 2, '.', '' ),
						$row['deposit_status'],
						$row['deposit_reference'] ?? '',
						View::when( $row['created_at'], 'Y-m-d H:i' ),
					)
				);
			}
			if ( count( $batch ) < self::BATCH ) {
				return $out;
			}
		}
	}

	/** Records the export (newest first, last 50 kept). */
	public static function log( array $rows, array $filters ): void {
		$log = get_option( self::LOG_OPTION, array() );
		array_unshift(
			$log,
			array(
				'user'    => get_current_user_id(),
				'at'      => gmdate( 'Y-m-d H:i:s' ),
				'rows'    => max( 0, count( $rows ) - 1 ),
				'filters' => $filters,
			)
		);
		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_KEEP ), false );
	}

	/** admin-post handler: streams the file. */
	public static function handle(): void {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'You do not have permission to export bookings.', 'ishot-booking' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified above.
		$when    = sanitize_key( wp_unslash( $_GET['when'] ?? 'all' ) );
		$filters = array(
			'status' => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			'when'   => in_array( $when, array( 'upcoming', 'past', 'all' ), true ) ? $when : 'all',
			'q'      => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
		);
		// phpcs:enable
		$rows = self::rows( $filters );
		self::log( $rows, $filters );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bookings-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		$out = fopen( 'php://output', 'w' );
		// Streams to php://output, not the filesystem, so WP_Filesystem does not apply.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so Excel reads names correctly.
		foreach ( $rows as $row ) {
			fputcsv( $out, $row, ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream.
		exit;
	}

	public static function url( array $filters ): string {
		return wp_nonce_url( add_query_arg( array_filter( $filters ) + array( 'action' => self::POST ), admin_url( 'admin-post.php' ) ), self::NONCE );
	}
}
