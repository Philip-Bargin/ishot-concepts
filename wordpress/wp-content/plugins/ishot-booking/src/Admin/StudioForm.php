<?php
namespace IShot\Booking\Admin;

use IShot\Booking\Settings;
use IShot\Booking\Studio;

/**
 * Validates and saves the Studio details form. Saves nothing when any field is invalid.
 *
 * A value that was filled in for comparison stays flagged "not confirmed" until the owner changes
 * it or ticks "confirm", so saving the form without looking never approves a value.
 */
final class StudioForm {

	public const PHONE_MAX   = 30;
	public const ADDRESS_MAX = 300;

	/**
	 * @param array<string, mixed> $input Unslashed form input.
	 * @return array<string, string> Errors by settings key; empty when saved.
	 */
	public static function save( array $input ): array {
		$errors = array();
		$values = array();
		$text   = static fn( string $key ): string => trim( (string) ( $input[ $key ] ?? '' ) );

		foreach ( array( 'studio_phone', 'studio_whatsapp' ) as $key ) {
			$raw = $text( $key );
			if ( '' !== $raw && ( strlen( $raw ) > self::PHONE_MAX || ! preg_match( '/^\+?[0-9 ()\-]+$/', $raw ) || strlen( (string) preg_replace( '/\D/', '', $raw ) ) < 7 ) ) {
				/* translators: %d: character limit */
				$errors[ $key ] = sprintf( __( 'Use digits, spaces, +, - and brackets only (7 to %d characters), or leave it empty.', 'ishot-booking' ), self::PHONE_MAX );
			} elseif ( 'studio_whatsapp' === $key && '' !== $raw && null === Studio::e164Digits( $raw ) ) {
				$errors[ $key ] = __( 'Start with the country code, for example +231, so the WhatsApp link works.', 'ishot-booking' );
			}
			$values[ $key ] = $raw;
		}

		$domains = array(
			'studio_instagram_url' => 'instagram.com',
			'studio_facebook_url'  => 'facebook.com',
		);
		foreach ( $domains as $key => $domain ) {
			$raw   = $text( $key );
			$clean = '' === $raw ? '' : Studio::socialUrl( $raw, $domain );
			if ( null === $clean ) {
				/* translators: %s: domain such as instagram.com */
				$errors[ $key ] = sprintf( __( 'Enter the full address starting with https:// on %s, or leave it empty.', 'ishot-booking' ), $domain );
				$clean          = '';
			}
			$values[ $key ] = $clean;
		}

		$email = $text( 'studio_public_email' );
		if ( '' !== $email && ! is_email( $email ) ) {
			$errors['studio_public_email'] = __( 'Enter a valid email address, or leave it empty.', 'ishot-booking' );
		}
		$values['studio_public_email'] = '' === $email ? '' : sanitize_email( $email );

		$address = trim( str_replace( "\r\n", "\n", sanitize_textarea_field( (string) ( $input['studio_address'] ?? '' ) ) ) );
		if ( mb_strlen( $address ) > self::ADDRESS_MAX ) {
			/* translators: %d: character limit */
			$errors['studio_address'] = sprintf( __( 'Keep the address under %d characters.', 'ishot-booking' ), self::ADDRESS_MAX );
		}
		$values['studio_address'] = $address;

		if ( array() !== $errors ) {
			return $errors;
		}

		$stored  = Settings::all();
		$flagged = is_array( $stored['studio_unconfirmed'] ) ? $stored['studio_unconfirmed'] : array();
		$keep    = array();
		foreach ( $flagged as $name ) {
			$key = Studio::FIELDS[ $name ] ?? null;
			// Compare in normalised form, so a stored value that is merely not in canonical form is "unchanged".
			if ( null !== $key && self::normalise( $key, (string) $stored[ $key ] ) === $values[ $key ] && empty( $input[ 'confirm_' . $key ] ) ) {
				$keep[] = $name;
			}
		}
		$values['studio_unconfirmed'] = $keep;
		Settings::update( $values );
		return array();
	}

	/** The canonical form a value is saved in (what a valid submission of it would be stored as). */
	private static function normalise( string $key, string $raw ): string {
		$raw = trim( $raw );
		switch ( $key ) {
			case 'studio_instagram_url':
				return Studio::socialUrl( $raw, 'instagram.com' ) ?? $raw;
			case 'studio_facebook_url':
				return Studio::socialUrl( $raw, 'facebook.com' ) ?? $raw;
			case 'studio_public_email':
				return sanitize_email( $raw );
			case 'studio_address':
				return trim( str_replace( "\r\n", "\n", sanitize_textarea_field( $raw ) ) );
			default:
				return $raw;
		}
	}
}
