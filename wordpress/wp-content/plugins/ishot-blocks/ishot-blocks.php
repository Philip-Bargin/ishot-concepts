<?php
/**
 * Plugin Name:       iShot Blocks
 * Description:       Reusable content blocks for the iShot Concept site. Blocks live here so page content survives a theme change; booking logic stays in iShot Booking.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Requires Plugins:  ishot-booking
 * Author:            iShot Concept
 * License:           GPL-2.0-or-later
 * Text Domain:       ishot-blocks
 *
 * @package IShot\Blocks
 */

defined( 'ABSPATH' ) || exit;

define( 'ISHOT_BLOCKS_FILE', __FILE__ );
define( 'ISHOT_BLOCKS_VERSION', '0.1.0' );

$ishot_blocks_autoload = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $ishot_blocks_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'iShot Blocks: run "composer install" in the plugin folder.', 'ishot-blocks' ) . '</p></div>';
		}
	);
	return;
}
require_once $ishot_blocks_autoload;

IShot\Blocks\Plugin::boot( __FILE__ );
