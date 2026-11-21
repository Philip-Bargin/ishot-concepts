<?php
namespace IShot\Booking\Domain;

use DateInterval;
use DateTimeImmutable;

/**
 * Free start times for a service on one studio day. Advisory only: the slot-lock insert
 * at booking time is the final authority.
 */
final class Availability {

	public function __construct( private readonly BookingPolicy $policy ) {}

	/**
	 * @param string   $local_date   Studio-local date 'Y-m-d'.
	 * @param int      $duration_min Session length.
	 * @param int      $buffer_before Minutes blocked before the session.
	 * @param int      $buffer_after  Minutes blocked after the session.
	 * @param string[] $locked_units Unit starts already locked (UTC 'Y-m-d H:i:s').
	 * @return string[] Local start times 'H:i'.
	 */
	public function startTimes( string $local_date, int $duration_min, int $buffer_before, int $buffer_after, array $locked_units, DateTimeImmutable $now ): array {
		$hours  = $this->policy->hours;
		$window = $hours->windowFor( $local_date );
		if ( null === $window ) {
			return array();
		}

		$locked = array_flip( $locked_units );
		$step   = new DateInterval( "PT{$this->policy->granularity_minutes}M" );
		$open   = new DateTimeImmutable( "{$local_date} {$window[0]}", $hours->timezone );
		$close  = new DateTimeImmutable( "{$local_date} {$window[1]}", $hours->timezone );

		$starts = array();
		for ( $start = $open; $start < $close; $start = $start->add( $step ) ) {
			$end = $start->add( new DateInterval( "PT{$duration_min}M" ) );
			if ( $end > $close ) {
				break;
			}
			$session = new TimeRange( $start, $end );
			if ( null !== $this->policy->check( $session, $now ) ) {
				continue;
			}
			$blocked = new TimeRange(
				$start->sub( new DateInterval( "PT{$buffer_before}M" ) ),
				$end->add( new DateInterval( "PT{$buffer_after}M" ) )
			);
			foreach ( SlotUnits::cover( $blocked, $this->policy->granularity_minutes ) as $unit ) {
				if ( isset( $locked[ $unit ] ) ) {
					continue 2;
				}
			}
			$starts[] = $start->format( 'H:i' );
		}
		return $starts;
	}
}
