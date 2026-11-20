<?php
namespace IShot\Booking\Application;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Catalog\Service;
use IShot\Booking\Domain\Availability;
use IShot\Booking\Domain\BookingPolicy;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\SlotConflict;

/**
 * Client-facing booking use cases: what is free, and asking for a time.
 * Policy is checked here; exclusivity is guaranteed by the repository's slot locks.
 */
final class BookingRequests {

	/** How many days past the requested date to search for alternatives. */
	private const ALTERNATIVE_SEARCH_DAYS = 14;

	private readonly Availability $availability;
	private readonly HoldExpiry $expiry;
	private bool $swept = false;

	public function __construct(
		private readonly BookingRepository $repo,
		private readonly BookingPolicy $policy,
		private readonly int $hold_hours
	) {
		$this->availability = new Availability( $policy );
		$this->expiry       = new HoldExpiry( $repo );
	}

	/** Releases lapsed holds once per request, so they never block a time even if cron is late. */
	public function releaseLapsedHolds( DateTimeImmutable $now ): void {
		if ( ! $this->swept ) {
			$this->expiry->run( $now );
			$this->swept = true;
		}
	}

	private function timezone(): DateTimeZone {
		return $this->policy->hours->timezone;
	}

	/**
	 * @param array{name: string, email: string, phone: string, location: string, notes: string} $client
	 */
	public function submit( Service $service, DateTimeImmutable $local_start, array $client, ?string $idempotency_key, DateTimeImmutable $now ): RequestOutcome {
		$this->releaseLapsedHolds( $now );
		$session   = new TimeRange( $local_start, $local_start->add( new DateInterval( "PT{$service->duration_min}M" ) ) );
		$violation = $this->policy->check( $session, $now );
		if ( null !== $violation ) {
			return RequestOutcome::rejected( $violation );
		}

		$token = bin2hex( random_bytes( 32 ) );
		$hash  = hash( 'sha256', $token );

		try {
			$id = $this->repo->create(
				new NewBooking(
					session: $session,
					buffer_before_min: $service->buffer_before_min,
					buffer_after_min: $service->buffer_after_min,
					status: BookingStatus::Requested,
					client_name: $client['name'],
					client_email: $client['email'],
					idempotency_key: $idempotency_key,
					service_id: $service->id,
					client_phone: $client['phone'],
					location: $client['location'],
					notes: $client['notes'],
					hold_expires_at: $now->add( new DateInterval( "PT{$this->hold_hours}H" ) ),
					public_token_hash: $hash
				)
			);
		} catch ( SlotConflict $e ) {
			return RequestOutcome::conflict( $this->alternatives( $service, $local_start, $now ) );
		}

		// A repeated idempotency key returns the earlier booking, which carries the earlier token.
		$stored = $this->repo->find( $id );
		if ( null === $stored || ! hash_equals( (string) $stored['public_token_hash'], $hash ) ) {
			return RequestOutcome::replayed( $id );
		}
		return RequestOutcome::created( $id, $token );
	}

	/** @return string[] Free local start times 'H:i' on a studio date. */
	public function startTimes( Service $service, string $local_date, DateTimeImmutable $now ): array {
		$this->releaseLapsedHolds( $now );
		return $this->availability->startTimes(
			$local_date,
			$service->duration_min,
			$service->buffer_before_min,
			$service->buffer_after_min,
			$this->lockedAround( $local_date ),
			$now
		);
	}

	/**
	 * Up to $limit free starts nearest the requested one, searching that day and the following days.
	 *
	 * @return DateTimeImmutable[] In chronological order.
	 */
	public function alternatives( Service $service, DateTimeImmutable $requested, DateTimeImmutable $now, int $limit = 3 ): array {
		$requested = $requested->setTimezone( $this->timezone() );
		$found     = array();
		$day       = $requested->setTime( 0, 0 );
		for ( $i = 0; $i <= self::ALTERNATIVE_SEARCH_DAYS && count( $found ) < $limit; $i++, $day = $day->modify( '+1 day' ) ) {
			$date = $day->format( 'Y-m-d' );
			foreach ( $this->startTimes( $service, $date, $now ) as $time ) {
				$found[] = new DateTimeImmutable( "{$date} {$time}", $this->timezone() );
			}
		}

		$distance = static fn( DateTimeImmutable $t ) => abs( $t->getTimestamp() - $requested->getTimestamp() );
		usort( $found, static fn( $a, $b ) => $distance( $a ) <=> $distance( $b ) );
		$nearest = array_slice( $found, 0, $limit );
		usort( $nearest, static fn( $a, $b ) => $a <=> $b );
		return $nearest;
	}

	/** @return string[] Locked units that could touch a session on this local date (buffers may spill over). */
	private function lockedAround( string $local_date ): array {
		$utc  = new DateTimeZone( 'UTC' );
		$day  = new DateTimeImmutable( "{$local_date} 00:00", $this->timezone() );
		$from = $day->modify( '-1 day' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		$to   = $day->modify( '+2 days' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		return $this->repo->lockedUnits( 1, $from, $to );
	}
}
