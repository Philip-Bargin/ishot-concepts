<?php
namespace IShot\Booking\Domain;

use DateInterval;
use DateTimeImmutable;

/**
 * Studio booking rules, enforced on the server for every request.
 */
final class BookingPolicy {

	public function __construct(
		public readonly int $lead_time_hours,
		public readonly int $horizon_days,
		public readonly int $granularity_minutes,
		public readonly OpeningHours $hours
	) {}

	/** First rule the session breaks, or null when it may be booked. */
	public function check( TimeRange $session, DateTimeImmutable $now ): ?PolicyViolation {
		if ( $session->start < $now->add( new DateInterval( "PT{$this->lead_time_hours}H" ) ) ) {
			return PolicyViolation::TooSoon;
		}
		if ( $session->start > $now->add( new DateInterval( "P{$this->horizon_days}D" ) ) ) {
			return PolicyViolation::TooFar;
		}
		if ( $session->start->getTimestamp() % ( $this->granularity_minutes * 60 ) !== 0 ) {
			return PolicyViolation::Misaligned;
		}
		if ( ! $this->hours->contains( $session ) ) {
			return PolicyViolation::OutsideHours;
		}
		return null;
	}
}
