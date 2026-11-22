<?php
/**
 * PHPUnit bootstrap.
 *
 * The unit suite needs only the Composer autoloader. The integration and theme suites boot the
 * WordPress test library that wp-env provides (WP_TESTS_DIR, default /wordpress-phpunit), load
 * ishot-booking and then ishot-blocks before WordPress finishes loading, and make the
 * ishot-concept theme the active theme.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$argv_string = implode( ' ', $GLOBALS['argv'] ?? array() );
// Exactly the unit suite: `--testsuite unit,integration` still needs WordPress.
if ( 1 === preg_match( '/--testsuite[= ]unit(\s|$)/', $argv_string ) ) {
	return;
}

$tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/wordpress-phpunit';
if ( ! file_exists( $tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found in {$tests_dir}. Run the integration and theme suites via wp-env.\n" );
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		// ishot-blocks declares ishot-booking as a required plugin: load them in that order.
		require dirname( __DIR__, 2 ) . '/ishot-booking/ishot-booking.php';
		require dirname( __DIR__ ) . '/ishot-blocks.php';
	}
);

// Run every suite with the project theme active (wp-env maps it next to the plugins).
tests_add_filter(
	'pre_option_template',
	static function () {
		return 'ishot-concept';
	}
);
tests_add_filter(
	'pre_option_stylesheet',
	static function () {
		return 'ishot-concept';
	}
);

require $tests_dir . '/includes/bootstrap.php';
