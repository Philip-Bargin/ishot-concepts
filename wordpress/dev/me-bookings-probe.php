<?php
// Dev only: what /ishot/v1/me/bookings returns for the client with the email in $args[0].
// wp eval-file wp-content/ishot-dev/me-bookings-probe.php <email>
require_once __DIR__ . '/lib/guard.php';
ishot_dev_guard_or_exit();
$user = get_user_by( 'email', $args[0] );
wp_set_current_user( $user->ID );
$response = rest_do_request( new WP_REST_Request( 'GET', '/ishot/v1/me/bookings' ) );
printf( "verified=%s status=%d bookings=%s\n", \IShot\Booking\Accounts\ClientAccounts::verifiedEmail( $user->ID ) ?? 'no', $response->get_status(), wp_json_encode( array_map( static fn( $b ) => $b['start'], $response->get_data() ) ) );
