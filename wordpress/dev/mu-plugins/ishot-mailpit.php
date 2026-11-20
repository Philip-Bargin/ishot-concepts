<?php
/**
 * Development only: sends all mail from the wp-env development site to Mailpit
 * (`npm run mail:start`, inbox at http://localhost:8026). Mapped into the development
 * environment by .wp-env.json; it is never deployed and never loaded by the test site.
 *
 * @package IShot\Booking
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'phpmailer_init',
	static function ( $phpmailer ): void {
		$phpmailer->isSMTP();
		$phpmailer->Host        = 'host.docker.internal'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- PHPMailer API.
		$phpmailer->Port        = 1026; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$phpmailer->SMTPAuth    = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$phpmailer->SMTPAutoTLS = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	}
);
