<?php
namespace IShot\Booking\Domain;

use DateTimeZone;

/**
 * Studio opening hours in the studio's own timezone: a weekly pattern plus per-date exceptions.
 */
final class OpeningHours {

	/**
	 * @param array<int, array{string, string}>       $weekly     ISO weekday (1 = Mon … 7 = Sun) => [open 'H:i', close 'H:i']. Missing day = closed.
	 * @param DateTimeZone                            $timezone   Studio timezone.
	 * @param array<string, array{string, string}|null> $exceptions 'Y-m-d' => hours for that date, or null when closed.
	 */
	public function __construct(
		private readonly array $weekly,
		public readonly DateTimeZone $timezone,
		private readonly array $exceptions = array()
	) {}

	/**
	 * Opening window for a local date, or null when closed.
	 *
	 * @return array{string, string}|null
	 */
	public function windowFor( string $local_date ): ?array {
		if ( array_key_exists( $local_date, $this->exceptions ) ) {
			return $this->exceptions[ $local_date ];
		}
		$weekday = (int) date_create_immutable( $local_date, $this->timezone )->format( 'N' );
		return $this->weekly[ $weekday ] ?? null;
	}

	/** True when the whole range falls inside one day's opening window, in studio time. */
	public function contains( TimeRange $range ): bool {
		$start = $range->start->setTimezone( $this->timezone );
		$end   = $range->end->setTimezone( $this->timezone );
		$date  = $start->format( 'Y-m-d' );

		if ( $end->format( 'Y-m-d' ) !== $date ) {
			return false;
		}
		$window = $this->windowFor( $date );
		if ( null === $window ) {
			return false;
		}
		return $start->format( 'H:i' ) >= $window[0] && $end->format( 'H:i' ) <= $window[1];
	}
}
