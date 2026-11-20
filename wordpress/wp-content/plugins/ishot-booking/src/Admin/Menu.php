<?php
namespace IShot\Booking\Admin;

/** The Bookings menu in wp-admin, and its styles. */
final class Menu {

	public const SLUG       = 'ishot-bookings';
	public const LIST_SLUG  = 'ishot-bookings-list';
	public const HOURS_SLUG = 'ishot-hours';
	public const SET_SLUG   = 'ishot-settings';
	public const EMAIL_SLUG = 'ishot-emails';

	public static function register(): void {
		add_menu_page(
			__( 'Bookings', 'ishot-booking' ),
			__( 'Bookings', 'ishot-booking' ),
			'view_ishot_calendar',
			self::SLUG,
			array( CalendarScreen::class, 'page' ),
			'dashicons-calendar-alt',
			26
		);
		add_submenu_page( self::SLUG, __( 'Calendar', 'ishot-booking' ), __( 'Calendar', 'ishot-booking' ), 'view_ishot_calendar', self::SLUG, array( CalendarScreen::class, 'page' ) );
		add_submenu_page( self::SLUG, __( 'All bookings', 'ishot-booking' ), __( 'All bookings', 'ishot-booking' ), 'view_ishot_calendar', self::LIST_SLUG, array( ListScreen::class, 'page' ) );
		add_submenu_page( self::SLUG, __( 'Opening hours', 'ishot-booking' ), __( 'Opening hours', 'ishot-booking' ), 'manage_ishot_availability', self::HOURS_SLUG, array( SettingsScreen::class, 'hoursPage' ) );
		add_submenu_page( self::SLUG, __( 'Booking settings', 'ishot-booking' ), __( 'Settings', 'ishot-booking' ), 'manage_ishot_settings', self::SET_SLUG, array( SettingsScreen::class, 'policyPage' ) );
		add_submenu_page( self::SLUG, __( 'Emails and calendar', 'ishot-booking' ), __( 'Emails', 'ishot-booking' ), 'manage_ishot_settings', self::EMAIL_SLUG, array( EmailSettingsScreen::class, 'page' ) );
	}

	/** Loads the token and admin styles on the plugin's own screens only. */
	public static function enqueue( string $hook ): void {
		if ( ! str_contains( $hook, self::SLUG ) && ! str_contains( $hook, 'ishot-' ) && 'index.php' !== $hook ) {
			return;
		}
		$base = plugins_url( 'assets/css/', ISHOT_BOOKING_FILE );
		wp_enqueue_style( 'ishot-booking-tokens', $base . 'tokens.css', array(), ISHOT_BOOKING_VERSION );
		wp_enqueue_style( 'ishot-booking-admin', $base . 'admin.css', array( 'ishot-booking-tokens' ), ISHOT_BOOKING_VERSION );
	}
}
