<?php
/**
 * DEV ONLY. Creates title-only page shells for the prototype's page routes (about, services,
 * portfolio, creative-direction, journal, contact, book, account) and one journal post shell, so
 * the local site's routes render through the theme templates until Stage 3 builds the real pages.
 *
 *   wp-env run cli wp eval-file wp-content/ishot-dev/page-shells-dev.php
 *
 * Every guard lives in IShot\Booking\Dev\DevGuard (shared with studio-details-dev.php) and must pass
 * before anything is written: environment type local/development, ISHOT_DEV_FIXTURES === true (set
 * only in .wp-env.json env.development.config) and a local-looking home_url() host. An existing page
 * or post with the same slug is never touched. Shells are marked placeholder, so the "Sample content."
 * notice shows. The wp-env default permalink structure becomes /journal/%postname%/ (Task 3.1 sets it
 * for real); any other structure is left alone.
 * Otherwise it prints the reason and exits 1 having changed nothing.
 */

require_once __DIR__ . '/lib/guard.php';
ishot_dev_guard_or_exit();

use IShot\Booking\Dev\PageShellFixtures;

if ( ! class_exists( PageShellFixtures::class ) ) {
	fwrite( STDERR, "Refused: the ishot-booking plugin is not active.\n" );
	exit( 1 );
}

$ishot_result = PageShellFixtures::run();

if ( ! $ishot_result['ok'] ) {
	fwrite( STDERR, 'Refused, nothing changed: ' . $ishot_result['reason'] . "\n" );
	exit( 1 );
}

echo 'Shells created (published, placeholder): ', array() === $ishot_result['created'] ? 'none' : implode( ', ', $ishot_result['created'] ), PHP_EOL;
echo 'Left unchanged (already exist): ', array() === $ishot_result['skipped'] ? 'none' : implode( ', ', $ishot_result['skipped'] ), PHP_EOL;
echo 'Permalinks: ', $ishot_result['permalinks'], PHP_EOL;
