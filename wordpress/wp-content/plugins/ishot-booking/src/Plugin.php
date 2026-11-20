<?php
namespace IShot\Booking;

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
	}
}
