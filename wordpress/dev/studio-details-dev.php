<?php
/**
 * DEV ONLY. Fills empty Studio details from the prototype so the local site can be compared visually.
 *
 *   wp-env run cli wp eval-file wp-content/ishot-dev/studio-details-dev.php
 *
 * Every guard lives in IShot\Booking\Dev\StudioDevFixtures and must pass before anything is written:
 * environment type local/development, ISHOT_DEV_FIXTURES === true (set only in .wp-env.json
 * env.development.config), a local-looking home_url() host, and no overwriting of non-empty values.
 * Values written are recorded as unconfirmed and shown as "Not confirmed by the studio yet".
 * Otherwise it prints the reason and exits 1 having changed nothing.
 */

use IShot\Booking\Dev\StudioDevFixtures;

if ( ! class_exists( StudioDevFixtures::class ) ) {
	fwrite( STDERR, "Refused: the ishot-booking plugin is not active.\n" );
	exit( 1 );
}

$ishot_result = StudioDevFixtures::apply(
	wp_get_environment_type(),
	defined( 'ISHOT_DEV_FIXTURES' ) ? ISHOT_DEV_FIXTURES : null,
	home_url()
);

if ( ! $ishot_result['ok'] ) {
	fwrite( STDERR, 'Refused, nothing changed: ' . $ishot_result['reason'] . "\n" );
	exit( 1 );
}

echo 'Studio details filled (unconfirmed): ', array() === $ishot_result['written'] ? 'none, every field already had a value' : implode( ', ', $ishot_result['written'] ), PHP_EOL;
echo 'Facebook left empty (not supplied).', PHP_EOL;
