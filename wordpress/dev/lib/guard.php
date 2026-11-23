<?php
/**
 * DEV ONLY. The guard every wordpress/dev script calls first (exception E-5).
 *
 * It fails closed: when the ishot-booking plugin (and so IShot\Booking\Dev\DevGuard) is missing, or any
 * DevGuard rule blocks the run (environment type local/development, ISHOT_DEV_FIXTURES === true set only in
 * .wp-env.json env.development.config, a local-looking home_url() host), the script exits 1 before it
 * reads or writes anything. Read-only probes are guarded too, so no dev script runs outside a local dev site.
 */

use IShot\Booking\Dev\DevGuard;

if ( ! function_exists( 'ishot_dev_guard_or_exit' ) ) {
	/**
	 * Exit 1 with the reason unless this is a local development site that opted in.
	 */
	function ishot_dev_guard_or_exit(): void {
		if ( ! class_exists( DevGuard::class ) ) {
			fwrite( STDERR, "Refused: the ishot-booking plugin is not active.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP-CLI script: the refusal goes to STDERR.
			exit( 1 );
		}

		$reason = DevGuard::blocked(
			wp_get_environment_type(),
			defined( 'ISHOT_DEV_FIXTURES' ) ? constant( 'ISHOT_DEV_FIXTURES' ) : null,
			home_url()
		);

		if ( '' !== $reason ) {
			fwrite( STDERR, 'Refused, nothing changed: ' . $reason . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP-CLI script: the refusal goes to STDERR.
			exit( 1 );
		}
	}
}
