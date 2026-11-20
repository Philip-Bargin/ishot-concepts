<?php
/**
 * Plugin Name:       iShot Booking
 * Description:       Booking management for the iShot Concept studio: availability, conflict-safe bookings, deposits and notifications.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            iShot Concept
 * License:           GPL-2.0-or-later
 * Text Domain:       ishot-booking
 *
 * @package IShot\Booking
 */

defined( 'ABSPATH' ) || exit;

define( 'ISHOT_BOOKING_FILE', __FILE__ );
define( 'ISHOT_BOOKING_VERSION', '0.1.0' );

$ishot_booking_autoload = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $ishot_booking_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'iShot Booking: run "composer install" in the plugin folder.', 'ishot-booking' ) . '</p></div>';
		}
	);
	return;
}
require_once $ishot_booking_autoload;

IShot\Booking\Plugin::boot( __FILE__ );
