<?php
namespace IShot\Booking\Admin;

use DateTimeImmutable;

/** All bookings (with bulk actions and CSV export), or one booking's detail when ?booking= is present. */
final class ListScreen {

	/** Menu callback. */
	public static function page(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- read-only filters.
		if ( isset( $_GET['booking'] ) && ! is_array( $_GET['booking'] ) ) {
			BookingScreen::render( absint( $_GET['booking'] ), new DateTimeImmutable( 'now' ) );
			return;
		}
		$when = sanitize_key( wp_unslash( $_REQUEST['when'] ?? 'upcoming' ) );
		self::render(
			array(
				'status' => sanitize_key( wp_unslash( $_REQUEST['status'] ?? '' ) ),
				'when'   => in_array( $when, array( 'upcoming', 'past', 'all' ), true ) ? $when : 'upcoming',
				'q'      => sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) ),
				'paged'  => absint( $_REQUEST['paged'] ?? 1 ),
			)
		);
		// phpcs:enable
	}

	/** @param array{status?: string, when?: string, q?: string, paged?: int} $filters */
	public static function render( array $filters ): void {
		$table = new BookingsTable( $filters );
		$table->prepare_items();

		echo '<div class="wrap ishot-admin"><h1 class="wp-heading-inline">' . esc_html__( 'All bookings', 'ishot-booking' ) . '</h1>';
		if ( CsvExport::allowed() ) {
			$export = CsvExport::url(
				array(
					'when'   => $filters['when'] ?? 'upcoming',
					'status' => $filters['status'] ?? '',
					's'      => $filters['q'] ?? '',
				)
			);
			printf( ' <a class="page-title-action" href="%s">%s</a>', esc_url( $export ), esc_html__( 'Export CSV', 'ishot-booking' ) );
		}
		echo '<hr class="wp-header-end">';
		ActionHandler::notice();
		self::bulkNotice();
		$table->views();
		// POST, so bulk changes never travel in a URL; search and filters are read from $_REQUEST.
		echo '<form method="post" action="' . esc_url( add_query_arg( 'page', Menu::LIST_SLUG, admin_url( 'admin.php' ) ) ) . '"><input type="hidden" name="page" value="' . esc_attr( Menu::LIST_SLUG ) . '">';
		echo '<input type="hidden" name="when" value="' . esc_attr( $filters['when'] ?? 'upcoming' ) . '">';
		$table->search_box( __( 'Search clients', 'ishot-booking' ), 'ishot-search' );
		$table->display();
		echo '</form></div>';
	}

	/**
	 * Applies a bulk action from the list form. Needs the list table's nonce (bulk-bookings);
	 * BulkActions checks capability and eligibility per booking.
	 *
	 * @param array<string, mixed> $request
	 * @return array{done: int, skipped: array<int, string>, error: string|null}
	 */
	public static function processBulk( array $request, DateTimeImmutable $now ): array {
		if ( ! wp_verify_nonce( (string) ( $request['_wpnonce'] ?? '' ), 'bulk-bookings' ) ) {
			return array(
				'done'    => 0,
				'skipped' => array(),
				'error'   => 'nonce',
			);
		}
		$action = (string) ( $request['action'] ?? '-1' );
		if ( '-1' === $action ) {
			$action = (string) ( $request['action2'] ?? '-1' ); // the bottom dropdown
		}
		return BulkActions::apply( (array) ( $request['booking'] ?? array() ), sanitize_key( $action ), $now );
	}

	/** admin_init: handles a submitted bulk action before any output, then redirects (no resubmit on refresh). */
	public static function handleBulk(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- processBulk() verifies the nonce.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'post' !== $method || Menu::LIST_SLUG !== $page || empty( $_POST['booking'] ) ) {
			return;
		}
		$top    = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '-1';
		$bottom = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '-1';
		if ( in_array( $top, array( '-1', '1' ), true ) && in_array( $bottom, array( '-1', '1' ), true ) ) {
			return; // a search or filter submit, not a bulk action
		}
		$result = self::processBulk( wp_unslash( $_POST ), new DateTimeImmutable( 'now' ) );
		// phpcs:enable
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => Menu::LIST_SLUG,
					'ishot_bulk'    => $result['error'] ?? 'ok',
					'ishot_done'    => $result['done'],
					'ishot_skipped' => count( $result['skipped'] ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function bulkNotice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! isset( $_GET['ishot_bulk'] ) ) {
			return;
		}
		$code    = sanitize_key( wp_unslash( $_GET['ishot_bulk'] ) );
		$done    = absint( $_GET['ishot_done'] ?? 0 );
		$skipped = absint( $_GET['ishot_skipped'] ?? 0 );
		// phpcs:enable
		$errors = array(
			'nonce'     => __( 'That form had expired. Please try again.', 'ishot-booking' ),
			'forbidden' => __( 'You do not have permission for that action.', 'ishot-booking' ),
			'invalid'   => __( 'That action cannot be applied in bulk.', 'ishot-booking' ),
			'too_many'  => __( 'Select at most 100 bookings at a time.', 'ishot-booking' ),
		);
		if ( isset( $errors[ $code ] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $errors[ $code ] ) . '</p></div>';
			return;
		}
		/* translators: %d: number of bookings */
		$text = sprintf( _n( '%d booking updated.', '%d bookings updated.', $done, 'ishot-booking' ), $done );
		if ( $skipped > 0 ) {
			/* translators: %d: number of bookings */
			$text .= ' ' . sprintf( _n( '%d skipped: the action did not apply to it (status or timing), or someone changed it first.', '%d skipped: the action did not apply to them (status or timing), or someone changed them first.', $skipped, 'ishot-booking' ), $skipped );
		}
		echo '<div class="notice notice-' . ( $skipped > 0 ? 'warning' : 'success' ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}
}
