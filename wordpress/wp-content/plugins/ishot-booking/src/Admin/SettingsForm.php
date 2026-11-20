<?php
namespace IShot\Booking\Admin;

use DateTimeImmutable;
use IShot\Booking\Settings;

/**
 * Validates and saves the Settings and Opening hours forms. Each form saves only its own keys,
 * and saves nothing when any field is invalid.
 *
 * The slot length (granularity) is deliberately not editable: existing slot locks are stored at
 * that length, and changing it while bookings exist would let new bookings overlap old ones.
 */
final class SettingsForm {

	/** Field => [min, max]. */
	public const POLICY_FIELDS = array(
		'lead_time_hours'     => array( 0, 720 ),
		'horizon_days'        => array( 1, 730 ),
		'hold_hours'          => array( 1, 336 ),
		'cancel_cutoff_hours' => array( 0, 720 ),
	);

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, string> Errors by field; empty when saved.
	 */
	public static function savePolicy( array $input ): array {
		$values = array();
		$errors = array();
		foreach ( self::POLICY_FIELDS as $field => [ $min, $max ] ) {
			$raw = trim( (string) ( $input[ $field ] ?? '' ) );
			if ( ! preg_match( '/^\d+$/', $raw ) || (int) $raw < $min || (int) $raw > $max ) {
				/* translators: 1: minimum, 2: maximum */
				$errors[ $field ] = sprintf( __( 'Enter a whole number from %1$d to %2$d.', 'ishot-booking' ), $min, $max );
				continue;
			}
			$values[ $field ] = (int) $raw;
		}
		if ( array() === $errors ) {
			Settings::update( $values );
		}
		return $errors;
	}

	/**
	 * @param array{weekly?: array<int|string, array<string, string>>, exceptions?: array<int, array<string, string>>} $input
	 * @return array<string, string> Errors keyed weekly_{1-7} or exception_{row}; empty when saved.
	 */
	public static function saveHours( array $input ): array {
		$errors = array();
		$weekly = array();
		for ( $day = 1; $day <= 7; $day++ ) {
			$row = (array) ( $input['weekly'][ $day ] ?? array( 'closed' => '1' ) );
			if ( ! empty( $row['closed'] ) ) {
				continue;
			}
			$window = self::window( $row );
			if ( is_string( $window ) ) {
				$errors[ "weekly_{$day}" ] = $window;
				continue;
			}
			$weekly[ $day ] = $window;
		}

		$exceptions = array();
		foreach ( array_values( (array) ( $input['exceptions'] ?? array() ) ) as $i => $row ) {
			$date = trim( (string) ( $row['date'] ?? '' ) );
			if ( '' === $date ) {
				continue;
			}
			$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
			if ( false === $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
				$errors[ "exception_{$i}" ] = __( 'Enter a real date.', 'ishot-booking' );
				continue;
			}
			if ( ! empty( $row['closed'] ) ) {
				$exceptions[ $date ] = null;
				continue;
			}
			$window = self::window( $row );
			if ( is_string( $window ) ) {
				$errors[ "exception_{$i}" ] = $window;
				continue;
			}
			$exceptions[ $date ] = $window;
		}
		ksort( $exceptions );

		if ( array() === $errors ) {
			Settings::update(
				array(
					'weekly_hours' => $weekly,
					'exceptions'   => $exceptions,
				)
			);
		}
		return $errors;
	}

	/**
	 * @param array<string, string> $row
	 * @return array{string, string}|string The window, or an error message.
	 */
	private static function window( array $row ): array|string {
		$open  = trim( (string) ( $row['open'] ?? '' ) );
		$close = trim( (string) ( $row['close'] ?? '' ) );
		$step  = Settings::int( 'granularity_minutes' );
		foreach ( array( $open, $close ) as $time ) {
			if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m ) ) {
				return __( 'Use 24-hour times such as 09:00, or mark the day closed.', 'ishot-booking' );
			}
			if ( 0 !== (int) $m[2] % $step ) {
				/* translators: %d: slot length in minutes */
				return sprintf( __( 'Times must fall on a %d-minute boundary.', 'ishot-booking' ), $step );
			}
		}
		if ( $close <= $open ) {
			return __( 'Closing time must be after opening time.', 'ishot-booking' );
		}
		return array( $open, $close );
	}
}
