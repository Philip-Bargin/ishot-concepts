<?php
namespace IShot\Booking\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Half-open time interval [start, end), always held in UTC.
 */
final class TimeRange {

	public readonly DateTimeImmutable $start;
	public readonly DateTimeImmutable $end;

	public function __construct( DateTimeImmutable $start, DateTimeImmutable $end ) {
		$utc = new DateTimeZone( 'UTC' );
		$this->start = $start->setTimezone( $utc );
		$this->end   = $end->setTimezone( $utc );

		if ( $this->end <= $this->start ) {
			throw new InvalidArgumentException( 'A time range must end after it starts.' );
		}
	}
}
