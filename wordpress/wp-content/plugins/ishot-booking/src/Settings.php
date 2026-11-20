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
	);

	/** @return array<string, mixed> */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : array() );
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
