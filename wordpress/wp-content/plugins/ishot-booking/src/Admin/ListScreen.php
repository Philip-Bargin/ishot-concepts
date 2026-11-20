<?php
namespace IShot\Booking\Admin;

use DateTimeImmutable;

/** All bookings, or one booking's detail when ?booking= is present. */
final class ListScreen {

	/** Menu callback. */
	public static function page(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		if ( isset( $_GET['booking'] ) ) {
			BookingScreen::render( absint( $_GET['booking'] ), new DateTimeImmutable( 'now' ) );
			return;
		}
		$when = sanitize_key( wp_unslash( $_GET['when'] ?? 'upcoming' ) );
		self::render(
			array(
				'status' => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
				'when'   => in_array( $when, array( 'upcoming', 'past', 'all' ), true ) ? $when : 'upcoming',
				'q'      => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
				'paged'  => absint( $_GET['paged'] ?? 1 ),
			)
		);
		// phpcs:enable
	}

	/** @param array{status?: string, when?: string, q?: string, paged?: int} $filters */
	public static function render( array $filters ): void {
		$table = new BookingsTable( $filters );
		$table->prepare_items();

		echo '<div class="wrap ishot-admin"><h1 class="wp-heading-inline">' . esc_html__( 'All bookings', 'ishot-booking' ) . '</h1><hr class="wp-header-end">';
		ActionHandler::notice();
		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( Menu::LIST_SLUG ) . '">';
		echo '<input type="hidden" name="when" value="' . esc_attr( $filters['when'] ?? 'upcoming' ) . '">';
		$table->search_box( __( 'Search clients', 'ishot-booking' ), 'ishot-search' );
		$table->display();
		echo '</form></div>';
	}
}
