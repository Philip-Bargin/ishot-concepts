<?php
namespace IShot\Booking\Admin;

use DateTimeImmutable;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Settings;

/** "Studio today" on the WordPress dashboard for anyone who can see the calendar. */
final class DashboardWidget {

	public static function register(): void {
		if ( current_user_can( 'view_ishot_calendar' ) ) {
			wp_add_dashboard_widget( 'ishot_booking_today', __( 'Studio today', 'ishot-booking' ), array( self::class, 'render' ) );
		}
	}

	public static function render(): void {
		global $wpdb;
		$summary = DashboardSummary::build( new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) ), new DateTimeImmutable( 'now' ) );
		$list    = admin_url( 'admin.php?page=' . Menu::LIST_SLUG );

		echo '<div class="ishot-admin ishot-widget"><ul class="ishot-stats">';
		$stats = array(
			array( $summary['requests'], __( 'New requests', 'ishot-booking' ), add_query_arg( 'status', 'requested', $list ) ),
			array( $summary['awaiting_deposit'], __( 'Awaiting deposit', 'ishot-booking' ), add_query_arg( 'status', 'pending_deposit', $list ) ),
			array( $summary['expiring_soon'], __( 'Holds ending in 24 h', 'ishot-booking' ), $list ),
		);
		foreach ( $stats as [ $count, $label, $url ] ) {
			printf( '<li><a href="%1$s"><strong>%2$s</strong> <span>%3$s</span></a></li>', esc_url( $url ), esc_html( number_format_i18n( $count ) ), esc_html( $label ) );
		}
		echo '</ul><h3>' . esc_html__( 'Today', 'ishot-booking' ) . '</h3>';
		if ( array() === $summary['today'] ) {
			echo '<p class="ishot-muted">' . esc_html__( 'No sessions today.', 'ishot-booking' ) . '</p>';
		} else {
			echo '<ul class="ishot-today">';
			foreach ( $summary['today'] as $row ) {
				printf(
					'<li><a href="%1$s">%2$s %3$s</a> %4$s</li>',
					esc_url( View::detailUrl( (int) $row['id'] ) ),
					esc_html( View::when( $row['start_utc'], (string) get_option( 'time_format' ) ) ),
					esc_html( $row['client_name'] ),
					View::badge( $row['status'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
				);
			}
			echo '</ul>';
		}
		printf( '<p><a href="%1$s">%2$s</a></p></div>', esc_url( admin_url( 'admin.php?page=' . Menu::SLUG ) ), esc_html__( 'Open the calendar', 'ishot-booking' ) );
	}
}
