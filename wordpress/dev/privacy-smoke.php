<?php
// wp-env run cli wp eval-file wp-content/ishot-dev/privacy-smoke.php
do_action( 'plugins_loaded' );
global $wpdb;
echo 'schema ', get_option( 'ishot_booking_db_version' ), PHP_EOL;
echo 'columns ', implode( ',', array_intersect( $wpdb->get_col( 'SHOW COLUMNS FROM ' . $wpdb->prefix . 'ishot_bookings' ), array( 'deposit_gateway', 'deposit_reference', 'anonymised_at' ) ) ), PHP_EOL;
$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$email     = (string) $wpdb->get_var( 'SELECT client_email FROM ' . $wpdb->prefix . "ishot_bookings WHERE kind = 'booking' LIMIT 1" );
$out       = call_user_func( $exporters['ishot-booking']['callback'], $email, 1 );
echo 'export items for seeded client: ', count( $out['data'] ), PHP_EOL;
echo 'retention scheduled: ', as_has_scheduled_action( 'ishot_booking_retention' ) ? 'yes' : 'no', PHP_EOL;
