<?php
namespace IShot\Booking\Admin;

use IShot\Booking\Notifications\Rules;
use IShot\Booking\Settings;

/**
 * Validates and saves the Emails screen. Saves nothing when any field is invalid.
 * Subjects are forced onto one line (they become a mail header); opening paragraphs keep
 * their line breaks. Unknown template keys are dropped.
 */
final class EmailSettingsForm {

	public const SUBJECT_MAX      = 150;
	public const INTRO_MAX        = 2000;
	public const INSTRUCTIONS_MAX = 1000;

	/**
	 * @param array<string, mixed> $input Unslashed form input.
	 * @return array<string, string> Errors by field; empty when saved.
	 */
	public static function save( array $input ): array {
		$errors = array();
		$values = array();

		foreach ( array( 'email_from_address', 'email_reply_to', 'email_studio_address' ) as $field ) {
			$raw = trim( (string) ( $input[ $field ] ?? '' ) );
			if ( '' !== $raw && ! is_email( $raw ) ) {
				$errors[ $field ] = __( 'Enter a valid email address, or leave it empty.', 'ishot-booking' );
				continue;
			}
			$values[ $field ] = '' === $raw ? '' : sanitize_email( $raw );
		}

		$values['email_from_name'] = mb_substr( sanitize_text_field( (string) ( $input['email_from_name'] ?? '' ) ), 0, 100 );

		$currency = strtoupper( trim( (string) ( $input['currency'] ?? '' ) ) );
		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$errors['currency'] = __( 'Use a three-letter currency code such as USD or LRD.', 'ishot-booking' );
		}
		$values['currency'] = $currency;

		$instructions = sanitize_textarea_field( (string) ( $input['deposit_instructions'] ?? '' ) );
		if ( mb_strlen( $instructions ) > self::INSTRUCTIONS_MAX ) {
			/* translators: %d: character limit */
			$errors['deposit_instructions'] = sprintf( __( 'Keep this under %d characters.', 'ishot-booking' ), self::INSTRUCTIONS_MAX );
		}
		$values['deposit_instructions'] = $instructions;

		$url = trim( (string) ( $input['manage_url'] ?? '' ) );
		if ( '' !== $url && ( false === filter_var( $url, FILTER_VALIDATE_URL ) || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) ) {
			$errors['manage_url'] = __( 'Enter a full web address starting with https://, or leave it empty.', 'ishot-booking' );
		}
		$values['manage_url'] = '' === $url ? '' : esc_url_raw( $url );

		$templates = array();
		$input_tpl = is_array( $input['templates'] ?? null ) ? $input['templates'] : array();
		foreach ( array_keys( Rules::TEMPLATES ) as $key ) {
			$row     = is_array( $input_tpl[ $key ] ?? null ) ? $input_tpl[ $key ] : array();
			$subject = trim( (string) preg_replace( '/\s+/', ' ', sanitize_text_field( (string) ( $row['subject'] ?? '' ) ) ) );
			$intro   = sanitize_textarea_field( (string) ( $row['intro'] ?? '' ) );
			if ( mb_strlen( $subject ) > self::SUBJECT_MAX ) {
				/* translators: %d: character limit */
				$errors[ "template_{$key}_subject" ] = sprintf( __( 'Keep the subject under %d characters.', 'ishot-booking' ), self::SUBJECT_MAX );
			}
			if ( mb_strlen( $intro ) > self::INTRO_MAX ) {
				/* translators: %d: character limit */
				$errors[ "template_{$key}_intro" ] = sprintf( __( 'Keep this under %d characters.', 'ishot-booking' ), self::INTRO_MAX );
			}
			$templates[ $key ] = array(
				'enabled' => ! empty( $row['enabled'] ),
				'subject' => $subject,
				'intro'   => $intro,
			);
		}
		$values['templates'] = $templates;

		if ( array() === $errors ) {
			Settings::update( $values );
		}
		return $errors;
	}
}
