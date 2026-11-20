<?php
namespace IShot\Booking;

use IShot\Booking\Admin\ActionHandler;
use IShot\Booking\Admin\DashboardWidget;
use IShot\Booking\Admin\Menu;
use IShot\Booking\Admin\SettingsScreen;
use IShot\Booking\Application\HoldExpiry;
use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Rest\ClientController;
use IShot\Booking\Rest\PublicController;
use IShot\Booking\Rest\StaffController;

/** Wires the plugin into WordPress. */
final class Plugin {

	public static function boot( string $plugin_file ): void {
		register_activation_hook( $plugin_file, array( Installer::class, 'onActivate' ) );
		add_action( 'plugins_loaded', array( Installer::class, 'maybeUpgrade' ) );
		register_deactivation_hook( $plugin_file, array( HoldExpiry::class, 'unschedule' ) );
		add_action( 'init', array( ServiceCatalog::class, 'register' ) );

		add_filter( 'cron_schedules', array( HoldExpiry::class, 'addInterval' ) ); // phpcs:ignore WordPress.WP.CronInterval -- five minutes is intended.
		add_action( 'init', array( HoldExpiry::class, 'ensureScheduled' ) );
		add_action( HoldExpiry::HOOK, array( HoldExpiry::class, 'sweep' ) );
		add_action(
			'rest_api_init',
			static function () {
				( new PublicController() )->register();
				( new StaffController() )->register();
				( new ClientController() )->register();
			}
		);

		if ( is_admin() ) {
			add_action( 'admin_menu', array( Menu::class, 'register' ) );
			add_action( 'admin_enqueue_scripts', array( Menu::class, 'enqueue' ) );
			add_action( 'wp_dashboard_setup', array( DashboardWidget::class, 'register' ) );
			add_action( 'admin_post_' . ActionHandler::POST_ACTION, array( ActionHandler::class, 'handleAction' ) );
			add_action( 'admin_post_' . ActionHandler::POST_BLOCK, array( ActionHandler::class, 'handleBlock' ) );
			add_action( 'admin_post_' . ActionHandler::POST_UNBLOCK, array( ActionHandler::class, 'handleUnblock' ) );
			add_action( 'admin_post_' . SettingsScreen::POST_HOURS, array( SettingsScreen::class, 'handleHours' ) );
			add_action( 'admin_post_' . SettingsScreen::POST_POLICY, array( SettingsScreen::class, 'handlePolicy' ) );
		}
	}
}
