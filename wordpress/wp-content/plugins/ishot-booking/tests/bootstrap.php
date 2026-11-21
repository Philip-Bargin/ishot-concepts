<?php
/**
 * PHPUnit bootstrap.
 *
 * The unit suite needs only the Composer autoloader. The integration suite boots the
 * WordPress test library that wp-env provides (WP_TESTS_DIR, default /wordpress-phpunit)
 * and loads this plugin before WordPress finishes loading.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$argv_string = implode( ' ', $GLOBALS['argv'] ?? array() );
if ( str_contains( $argv_string, '--testsuite unit' ) || str_contains( $argv_string, '--testsuite=unit' ) ) {
	return;
}

$tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/wordpress-phpunit';
if ( ! file_exists( $tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found in {$tests_dir}. Run the integration suite via wp-env.\n" );
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/ishot-booking.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
