<?php
namespace IShot\Booking\Domain;

use InvalidArgumentException;

/**
 * Maps a time range onto the fixed grid of slot units used for conflict prevention.
 *
 * Each unit a range touches becomes one row in the slot-lock table, whose primary key
 * (resource, unit start) makes the database reject any second booking of that unit.
 */
final class SlotUnits {

	/**
	 * Unit start times (UTC, 'Y-m-d H:i:s') covering the range. Partially touched units count.
	 *
	 * @param TimeRange $range              Buffered booking range.
	 * @param int       $granularity_minutes Unit length; must divide 60 evenly.
	 * @return string[]
	 */
	public static function cover( TimeRange $range, int $granularity_minutes ): array {
		if ( $granularity_minutes <= 0 || 60 % $granularity_minutes !== 0 ) {
			throw new InvalidArgumentException( 'Slot granularity must divide an hour evenly (e.g. 15, 20, 30, 60).' );
		}

		$step  = $granularity_minutes * 60;
		$from  = intdiv( $range->start->getTimestamp(), $step ) * $step;
		$until = $range->end->getTimestamp();

		$units = array();
		for ( $t = $from; $t < $until; $t += $step ) {
			$units[] = gmdate( 'Y-m-d H:i:s', $t );
		}
		return $units;
	}
}
