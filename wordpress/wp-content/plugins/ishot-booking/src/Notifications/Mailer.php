<?php
namespace IShot\Booking\Notifications;

use IShot\Booking\Settings;
use WP_Error;

/**
 * Sends a Message through wp_mail(), so whatever transport the site uses (FluentSMTP in
 * production, Mailpit locally) applies. Adds the plain-text alternative and in-memory
 * attachments that wp_mail() itself cannot express.
 */
final class Mailer {

	/** @return string|null Null when sent; otherwise the transport's error message. */
	public function send( Message $message ): ?string {
		$error   = null;
		$failed  = static function ( WP_Error $e ) use ( &$error ): void {
			$error = $e->get_error_message();
		};
		$prepare = static function ( $phpmailer ) use ( $message ): void {
			$phpmailer->AltBody = $message->text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- PHPMailer API.
			foreach ( $message->attachments as $a ) {
				$phpmailer->addStringAttachment( $a['content'], $a['filename'], 'base64', $a['type'] );
			}
		};

		add_action( 'wp_mail_failed', $failed );
		add_action( 'phpmailer_init', $prepare );
		try {
			$sent = wp_mail( $message->to, $message->subject, $message->html, self::headers( $message->reply_to ) );
		} catch ( \Throwable $e ) {
			$sent  = false;
			$error = $e->getMessage();
		} finally {
			remove_action( 'wp_mail_failed', $failed );
			remove_action( 'phpmailer_init', $prepare );
		}
		return $sent ? null : ( $error ?? 'wp_mail() returned false.' );
	}

	/** @return string[] */
	private static function headers( ?string $reply_to = null ): array {
		$s       = Settings::all();
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from    = sanitize_email( (string) $s['email_from_address'] );
		if ( '' !== $from ) {
			$headers[] = sprintf( 'From: %s <%s>', self::headerText( Settings::studioName() ), $from );
		}
		// A message may name its own Reply-To (an enquiry is answered to the person who wrote).
		$reply = sanitize_email( (string) ( $reply_to ?? $s['email_reply_to'] ) );
		if ( '' !== $reply ) {
			$headers[] = 'Reply-To: ' . $reply;
		}
		return $headers;
	}

	/** A display name safe inside a header: no line breaks, quotes or angle brackets. */
	public static function headerText( string $value ): string {
		return trim( (string) preg_replace( '/[\r\n"<>]+/', ' ', $value ) );
	}
}
