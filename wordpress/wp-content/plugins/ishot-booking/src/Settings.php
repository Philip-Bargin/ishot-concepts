<?php
namespace IShot\Booking;

use IShot\Booking\Domain\BookingPolicy;
use IShot\Booking\Domain\OpeningHours;

/**
 * Studio booking settings, stored in one option and merged over safe defaults.
 * The studio timezone is the site's own (Settings → General); it must be a named zone.
 */
final class Settings {

	public const OPTION = 'ishot_booking_settings';

	/** Defaults until the owner confirms real values (docs/content-inventory.md). */
	public const DEFAULTS = array(
		'lead_time_hours'     => 48,
		'horizon_days'        => 180,
		'granularity_minutes' => 30,
		'hold_hours'          => 48,
		'cancel_cutoff_hours' => 72,
		'weekly_hours'        => array(
			1 => array( '09:00', '18:00' ),
			2 => array( '09:00', '18:00' ),
			3 => array( '09:00', '18:00' ),
			4 => array( '09:00', '18:00' ),
			5 => array( '09:00', '18:00' ),
			6 => array( '09:00', '18:00' ),
		),
		'exceptions'          => array(),
		// Email. Empty values fall back at send time: studio name → site title,
		// studio address → admin email, from address → WordPress's default sender.
		'email_from_name'      => '',
		'email_from_address'   => '',
		'email_reply_to'       => '',
		'email_studio_address' => '',
		'currency'             => 'USD',
		'deposit_instructions' => '',
		'manage_url'           => '',
		// Per-template overrides: template => {enabled?: bool, subject?: string, intro?: string}.
		'templates'            => array(),
	);

	/** Where emails come from and go to, with the fallbacks applied. */
	public static function studioName(): string {
		$name = trim( (string) self::all()['email_from_name'] );
		return '' !== $name ? $name : wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
	}

	public static function studioAddress(): string {
		$address = sanitize_email( (string) self::all()['email_studio_address'] );
		return '' !== $address ? $address : (string) get_option( 'admin_email' );
	}

	/** The page that reads the manage-booking token from the URL fragment (never sent to servers). */
	public static function manageUrl(): string {
		$url = (string) self::all()['manage_url'];
		return '' !== $url ? $url : home_url( '/manage-booking/' );
	}

	/** @return array<string, mixed> */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : array() );
	}

	/** Merges validated values into the stored settings (callers validate; see Admin\SettingsForm). */
	public static function update( array $values ): void {
		$stored = get_option( self::OPTION, array() );
		update_option( self::OPTION, array_merge( is_array( $stored ) ? $stored : array(), $values ), false );
	}

	public static function int( string $key ): int {
		return (int) self::all()[ $key ];
	}

	public static function policy(): BookingPolicy {
		$s = self::all();
		return new BookingPolicy(
			(int) $s['lead_time_hours'],
			(int) $s['horizon_days'],
			(int) $s['granularity_minutes'],
			new OpeningHours( $s['weekly_hours'], wp_timezone(), $s['exceptions'] )
		);
	}
}
