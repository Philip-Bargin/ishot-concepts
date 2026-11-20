<?php
namespace IShot\Booking;

use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Rest\PublicController;

/** Wires the plugin into WordPress. */
final class Plugin {

	public static function boot( string $plugin_file ): void {
		register_activation_hook( $plugin_file, array( Installer::class, 'onActivate' ) );
		add_action( 'plugins_loaded', array( Installer::class, 'maybeUpgrade' ) );
		add_action( 'init', array( ServiceCatalog::class, 'register' ) );
		add_action( 'rest_api_init', static fn() => ( new PublicController() )->register() );
	}
}
