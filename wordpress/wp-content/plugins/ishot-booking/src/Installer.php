<?php
namespace IShot\Booking;

use IShot\Booking\Persistence\Schema;
use RuntimeException;

/**
 * Activation and schema upgrades. Bookings rely on InnoDB transactions and unique keys, so the
 * plugin refuses to activate on a database without InnoDB rather than run unsafely.
 */
final class Installer {

	public static function innodbAvailable(): bool {
		global $wpdb;
		$support   = $wpdb->get_var( "SELECT SUPPORT FROM information_schema.ENGINES WHERE ENGINE = 'InnoDB'" );
		$available = in_array( strtoupper( (string) $support ), array( 'YES', 'DEFAULT' ), true );
		return (bool) apply_filters( 'ishot_booking_innodb_available', $available );
	}

	/** @throws RuntimeException When the database cannot hold bookings safely. */
	public static function activate(): void {
		if ( ! self::innodbAvailable() ) {
			throw new RuntimeException( 'iShot Booking needs the InnoDB database engine, which this database does not provide. Ask your host to enable it.' );
		}
		Schema::install();
	}

	/** Activation-hook wrapper: on failure, deactivate and explain instead of half-installing. */
	public static function onActivate(): void {
		try {
			self::activate();
		} catch ( RuntimeException $e ) {
			deactivate_plugins( plugin_basename( ISHOT_BOOKING_FILE ) );
			wp_die( esc_html( $e->getMessage() ), esc_html__( 'Plugin not activated', 'ishot-booking' ), array( 'back_link' => true ) );
		}
	}

	/** Runs on every load; installs or upgrades tables when the stored schema version is behind. */
	public static function maybeUpgrade(): void {
		if ( get_option( Schema::VERSION_KEY ) !== Schema::VERSION ) {
			Schema::install();
		}
	}
}
