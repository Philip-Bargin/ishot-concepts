<?php
/**
 * DEV ONLY. Creates (or, with the argument `remove`, deletes) the component fixture pages at /ishot-fixtures/{component}/
 * for the Stage 2 component visual gate (tests/visual/components.mjs, plan §6 Stage 2 R-2-C).
 *
 *   wp-env run cli wp eval-file wp-content/ishot-dev/component-fixtures-dev.php
 *   wp-env run cli wp eval-file wp-content/ishot-dev/component-fixtures-dev.php remove
 *
 * Needs the imported prototype photos (npm run wp:import). Every guard lives in IShot\Booking\Dev\DevGuard
 * and is checked twice: by ishot_dev_guard_or_exit() here and again inside ComponentFixtures. An existing
 * page with a fixture slug is never touched; `remove` deletes only pages carrying the fixture marker.
 * (`remove` is positional: `wp eval-file` rejects unknown --flags and passes only positional arguments in $args.)
 * Otherwise it prints the reason and exits 1 having changed nothing.
 *
 * @package ishot-dev
 */

require_once __DIR__ . '/lib/guard.php';
ishot_dev_guard_or_exit();

require_once __DIR__ . '/lib/ComponentFixtures.php';

use IShot\Dev\ComponentFixtures;

$ishot_command = ComponentFixtures::command( array_values( array_map( 'strval', (array) ( $args ?? array() ) ) ) );
if ( '' !== $ishot_command['error'] ) {
	fwrite( STDERR, 'Refused, nothing changed: ' . $ishot_command['error'] . "
" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP-CLI script: the refusal goes to STDERR.
	exit( 1 );
}
$ishot_remove = 'remove' === $ishot_command['command'];

if ( $ishot_remove ) {
	$ishot_result = ComponentFixtures::remove();
	if ( ! $ishot_result['ok'] ) {
		fwrite( STDERR, 'Refused, nothing changed: ' . $ishot_result['reason'] . "\n" );
		exit( 1 );
	}
	echo 'Fixture pages removed: ', array() === $ishot_result['removed'] ? 'none' : implode( ', ', $ishot_result['removed'] ), PHP_EOL;
	echo 'Kept: ', array() === $ishot_result['kept'] ? 'none' : implode( '; ', $ishot_result['kept'] ), PHP_EOL;
	return;
}

$ishot_result = ComponentFixtures::run();

if ( ! $ishot_result['ok'] ) {
	fwrite( STDERR, 'Refused, nothing changed: ' . $ishot_result['reason'] . "\n" );
	exit( 1 );
}

echo 'Fixture pages created (published, placeholder): ', array() === $ishot_result['created'] ? 'none' : implode( ', ', $ishot_result['created'] ), PHP_EOL;
echo 'Left unchanged: ', array() === $ishot_result['skipped'] ? 'none' : implode( ', ', $ishot_result['skipped'] ), PHP_EOL;
