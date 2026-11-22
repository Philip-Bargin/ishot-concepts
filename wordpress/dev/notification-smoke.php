<?php
/**
 * Development smoke run for booking notifications. Drives every email in the lifecycle through
 * the real application services on the wp-env development site, then delivers the queue.
 * With `npm run mail:start` running, every message lands in Mailpit (http://localhost:8026).
 *
 * Usage (repository root; .wp-env.json maps wordpress/dev to wp-content/ishot-dev):
 *   npm run mail:start
 *   npx wp-env run cli wp eval-file wp-content/ishot-dev/notification-smoke.php
 *
 * Creates bookings for *.smoke@example.com clients; it never deletes anything.
 *
 * @package IShot\Booking
 */

require_once __DIR__ . '/lib/guard.php';
ishot_dev_guard_or_exit();

use IShot\Booking\Application\BookingRequests;
use IShot\Booking\Application\ClientBookings;
use IShot\Booking\Application\HoldExpiry;
use IShot\Booking\Application\StaffActions;
use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Notifications\Dispatcher;
use IShot\Booking\Notifications\Outbox;
use IShot\Booking\Notifications\Reminders;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Settings;

defined( 'ABSPATH' ) || exit;

global $wpdb;
$utc = new DateTimeZone( 'UTC' );
$now = new DateTimeImmutable( 'now', $utc );

Settings::update(
	array(
		'email_from_name'      => 'iShot Concept',
		'email_from_address'   => 'bookings@ishotconcept.example',
		'email_reply_to'       => 'hello@ishotconcept.example',
		'email_studio_address' => 'studio@ishotconcept.example',
		'currency'             => 'USD',
		'deposit_instructions' => "Pay by mobile money to 0770 000 000 (iShot Concept).\nUse your booking reference as the payment note.",
	)
);

$service = ServiceCatalog::allBookable()[0] ?? null;
if ( null === $service ) {
	WP_CLI::error( 'No bookable service on this site. Create one (Services → Add) and tick "bookable".' );
}
$policy   = Settings::policy();
$repo     = new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
$requests = new BookingRequests( $repo, $policy, Settings::int( 'hold_hours' ) );
$clients  = new ClientBookings( $repo, $requests, $policy, Settings::int( 'cancel_cutoff_hours' ) );
$staff    = new StaffActions( $repo );
$admin    = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 1 );

/** Books the first free start at least $days ahead, as a client would. */
$book = static function ( int $days, string $name ) use ( $requests, $service, $now ): array {
	$day = $now->setTimezone( wp_timezone() )->modify( "+{$days} days" );
	for ( $i = 0; $i < 30; $i++, $day = $day->modify( '+1 day' ) ) {
		$times = $requests->startTimes( $service, $day->format( 'Y-m-d' ), $now );
		if ( array() === $times ) {
			continue;
		}
		$start   = new DateTimeImmutable( $day->format( 'Y-m-d' ) . ' ' . $times[0], wp_timezone() );
		$slug    = strtolower( strtok( $name, ' ' ) );
		$outcome = $requests->submit(
			$service,
			$start,
			array(
				'name'     => $name,
				'email'    => "{$slug}.smoke@example.com",
				'phone'    => '+231 770 000 000',
				'location' => 'Studio, Monrovia',
				'notes'    => '',
			),
			null,
			$now
		);
		if ( null !== $outcome->booking_id ) {
			return array( $outcome->booking_id, $start );
		}
	}
	WP_CLI::error( "No free time found for {$name}." );
};
$version = static fn( int $id ) => (int) $repo->find( $id )['version'];

// 1. Full journey: request → deposit → paid/confirmed → moved by the client → cancelled by the client.
[ $ada, $ada_start ] = $book( 10, 'Ada Lifecycle' );
$staff->perform( $ada, 'request_deposit', $version( $ada ), $admin, array( 'deposit_amount' => 50.0 ) );
$staff->perform( $ada, 'deposit_received', $version( $ada ), $admin );
$free = $requests->startTimes( $service, $ada_start->modify( '+1 day' )->format( 'Y-m-d' ), $now );
$move = new DateTimeImmutable( $ada_start->modify( '+1 day' )->format( 'Y-m-d' ) . ' ' . ( $free[0] ?? '10:00' ), wp_timezone() );
$outcome = $clients->reschedule( $repo->find( $ada ), $move, $now );
if ( null === $outcome->booking_id ) {
	WP_CLI::error( 'Client reschedule did not go through.' );
}
$clients->cancel( $repo->find( $ada ), $now );

// 2. Confirmed directly by staff, then completed: confirmation + thank-you.
[ $ben ] = $book( 12, 'Ben Complete' );
$staff->perform( $ben, 'confirm', $version( $ben ), $admin );
$staff->perform( $ben, 'complete', $version( $ben ), $admin );

// 3. A request nobody answered: the studio warning, then the lapse on both sides.
[ $cy ] = $book( 14, 'Cy Lapsed' );
$cy_hold = new DateTimeImmutable( (string) $repo->find( $cy )['hold_expires_at'], $utc );
( new Reminders() )->queueDue( $cy_hold->modify( '-6 hours' ) );
( new HoldExpiry( $repo ) )->run( $cy_hold->modify( '+1 minute' ) );

// 4. A deposit still unpaid near the end of its hold: the deposit reminder.
[ $dee ] = $book( 16, 'Dee Deposit' );
$staff->perform( $dee, 'request_deposit', $version( $dee ), $admin, array( 'deposit_amount' => 75.0 ) );
$dee_hold = new DateTimeImmutable( (string) $repo->find( $dee )['hold_expires_at'], $utc );
$wpdb->update( Schema::bookings(), array( 'updated_at' => gmdate( 'Y-m-d H:i:s', $dee_hold->getTimestamp() - DAY_IN_SECONDS ) ), array( 'id' => $dee ) );
( new Reminders() )->queueDue( $dee_hold->modify( '-6 hours' ) );

// 5. A confirmed session the next day: the 24-hour reminder.
[ $eve, $eve_start ] = $book( 18, 'Eve Reminder' );
$staff->perform( $eve, 'confirm', $version( $eve ), $admin );
( new Reminders() )->queueDue( $eve_start->setTimezone( $utc )->modify( '-6 hours' ) );

// Deliver everything now.
$sent = 0;
do {
	$batch = ( new Dispatcher() )->sendDue( new DateTimeImmutable( 'now', $utc ) );
	$sent += $batch;
} while ( $batch > 0 );

$outbox = new Outbox();
foreach ( array( 'ada' => $ada, 'ben' => $ben, 'cy' => $cy, 'dee' => $dee, 'eve' => $eve ) as $who => $id ) {
	foreach ( $outbox->forBooking( $id ) as $row ) {
		WP_CLI::log( sprintf( '#%d %-4s %-20s %-7s %s %s', $id, $who, $row['template'], $row['status'], $row['calendar_method'] ?? '-', $row['last_error'] ) );
	}
}
WP_CLI::success( "Sent {$sent} emails." );
