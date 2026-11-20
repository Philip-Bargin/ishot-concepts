<?php
/**
 * Child process for ConcurrencyTest. Boots the WordPress test site with its own MySQL
 * connection, waits for a shared start instant, then tries to book one slot.
 *
 * Usage: php claim-slot.php <mode> <start-at-unix-float> <slot-start-utc>
 *   mode = reset  → (re)create tables and empty them, print "ready"
 *   mode = claim  → print "ok:<id>" or "conflict"
 *   mode = count  → print the number of live bookings and lock rows: "<bookings>:<locks>"
 */

[ , $mode, $start_at, $slot ] = array_pad( $argv, 4, '' );

define( 'WP_USE_THEMES', false );
require '/var/www/html/wp-load.php';
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php'; // works whether or not the plugin is activated

use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Persistence\SlotConflict;

global $wpdb;

if ( 'reset' === $mode ) {
	Schema::install();
	foreach ( Schema::tables() as $table ) {
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	echo 'ready';
	exit;
}

if ( 'count' === $mode ) {
	echo (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::bookings() ) . ':' . (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::locks() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	exit;
}

$utc   = new DateTimeZone( 'UTC' );
$start = new DateTimeImmutable( $slot, $utc );
$draft = new NewBooking(
	session: new TimeRange( $start, $start->modify( '+60 minutes' ) ),
	buffer_before_min: 0,
	buffer_after_min: 30,
	status: BookingStatus::Requested,
	client_name: 'Racer ' . getmypid(),
	client_email: 'racer' . getmypid() . '@example.com',
	service_id: 1
);
$repo = new BookingRepository( $wpdb, 30 );

// Barrier: every child is already booted and connected; release them together.
while ( microtime( true ) < (float) $start_at ) {
	usleep( 200 );
}

try {
	echo 'ok:' . $repo->create( $draft );
} catch ( SlotConflict $e ) {
	echo 'conflict';
}
