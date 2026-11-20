<?php
namespace IShot\Booking\Application;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Catalog\Service;
use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Domain\BookingPolicy;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\SlotConflict;
use IShot\Booking\Persistence\StaleBooking;

/**
 * What a client can do through their manage-booking link. The link's token is the credential;
 * changes are allowed only while the booking holds a slot and before the cancellation cut-off.
 */
final class ClientBookings {

	/** The link keeps working this long after the session ends, then behaves as unknown. */
	public const LINK_DAYS_AFTER_SESSION = 30;

	public function __construct(
		private readonly BookingRepository $repo,
		private readonly BookingRequests $requests,
		private readonly BookingPolicy $policy,
		private readonly int $cutoff_hours
	) {}

	private static function utc( string $value ): DateTimeImmutable {
		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}

	/** @return array<string, mixed>|null The booking for a token, or null when unknown or long past. */
	public function find( string $token, DateTimeImmutable $now ): ?array {
		$this->requests->releaseLapsedHolds( $now );
		$row = $this->repo->findByTokenHash( hash( 'sha256', $token ) );
		if ( null === $row || 'booking' !== $row['kind'] ) {
			return null;
		}
		$link_ends = self::utc( $row['end_utc'] )->add( new DateInterval( 'P' . self::LINK_DAYS_AFTER_SESSION . 'D' ) );
		return $now > $link_ends ? null : $row;
	}

	/** @param array<string, mixed> $row */
	public function changeDeadline( array $row ): DateTimeImmutable {
		return self::utc( $row['start_utc'] )->sub( new DateInterval( "PT{$this->cutoff_hours}H" ) );
	}

	/** @param array<string, mixed> $row */
	public function canChange( array $row, DateTimeImmutable $now ): bool {
		return BookingStatus::from( $row['status'] )->holdsSlot() && $now < $this->changeDeadline( $row );
	}

	/**
	 * @param array<string, mixed> $row
	 * @throws ChangeRefused
	 * @throws StaleBooking
	 */
	public function cancel( array $row, DateTimeImmutable $now ): void {
		$this->assertChangeable( $row, $now );
		$this->repo->transition( (int) $row['id'], BookingStatus::Cancelled, (int) $row['version'], null, array(), array( 'by' => 'client' ) );
	}

	/**
	 * Moves the booking to a new studio-local start, under the same policy as a new request.
	 *
	 * @param array<string, mixed> $row
	 * @throws ChangeRefused
	 * @throws StaleBooking
	 */
	public function reschedule( array $row, DateTimeImmutable $local_start, DateTimeImmutable $now ): RequestOutcome {
		$this->assertChangeable( $row, $now );

		$duration  = self::utc( $row['end_utc'] )->getTimestamp() - self::utc( $row['start_utc'] )->getTimestamp();
		$session   = new TimeRange( $local_start, $local_start->modify( "+{$duration} seconds" ) );
		$violation = $this->policy->check( $session, $now );
		if ( null !== $violation ) {
			return RequestOutcome::rejected( $violation );
		}

		try {
			$this->repo->reschedule( (int) $row['id'], $local_start, (int) $row['version'] );
		} catch ( SlotConflict $e ) {
			return RequestOutcome::conflict( $this->requests->alternatives( $this->serviceFor( $row ), $local_start, $now ) );
		}
		return RequestOutcome::changed( (int) $row['id'] );
	}

	/** @param array<string, mixed> $row */
	private function assertChangeable( array $row, DateTimeImmutable $now ): void {
		if ( ! BookingStatus::from( $row['status'] )->holdsSlot() ) {
			throw new ChangeRefused( ChangeRefused::NOT_CHANGEABLE, __( 'This booking can no longer be changed.', 'ishot-booking' ) );
		}
		if ( $now >= $this->changeDeadline( $row ) ) {
			throw new ChangeRefused(
				ChangeRefused::PAST_CUTOFF,
				sprintf(
					/* translators: %d: hours before the session */
					__( 'Changes close %d hours before your session. Please contact the studio.', 'ishot-booking' ),
					$this->cutoff_hours
				)
			);
		}
	}

	/**
	 * The service as booked: its timings come from the booking itself, so alternatives stay right
	 * even if the service was since edited or withdrawn.
	 *
	 * @param array<string, mixed> $row
	 */
	private function serviceFor( array $row ): Service {
		$ts      = static fn( string $col ) => self::utc( $row[ $col ] )->getTimestamp();
		$catalog = null === $row['service_id'] ? null : ServiceCatalog::bookable( (int) $row['service_id'] );
		return new Service(
			(int) $row['service_id'],
			$catalog?->title ?? '',
			intdiv( $ts( 'end_utc' ) - $ts( 'start_utc' ), 60 ),
			intdiv( $ts( 'start_utc' ) - $ts( 'block_start_utc' ), 60 ),
			intdiv( $ts( 'block_end_utc' ) - $ts( 'end_utc' ), 60 ),
			$catalog?->price_from ?? 0.0,
			$catalog?->deposit_amount ?? 0.0,
			true
		);
	}
}
