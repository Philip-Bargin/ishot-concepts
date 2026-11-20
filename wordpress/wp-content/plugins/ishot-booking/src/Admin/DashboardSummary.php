<?php
namespace IShot\Booking\Admin;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Persistence\BookingRepository;

/** What the studio needs to see first: today's sessions and what is waiting on them. */
final class DashboardSummary {

	/**
	 * @return array{today: array<int, array<string, mixed>>, requests: int, awaiting_deposit: int, expiring_soon: int}
	 */
	public static function build( BookingRepository $repo, DateTimeImmutable $now ): array {
		$utc   = new DateTimeZone( 'UTC' );
		$day   = $now->setTimezone( wp_timezone() )->setTime( 0, 0 );
		$today = array_values(
			array_filter(
				$repo->between( $day->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $day->modify( '+1 day' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) ),
				static fn( array $row ) => 'booking' === $row['kind'] && ! in_array( $row['status'], array( 'cancelled', 'expired' ), true )
					&& ( new DateTimeImmutable( $row['start_utc'], $utc ) ) >= $day
			)
		);

		$now_utc = $now->setTimezone( $utc );
		return array(
			'today'            => $today,
			'requests'         => $repo->countWithStatus( BookingStatus::Requested ),
			'awaiting_deposit' => $repo->countWithStatus( BookingStatus::PendingDeposit ),
			'expiring_soon'    => $repo->countHoldsExpiringBetween( $now_utc->format( 'Y-m-d H:i:s' ), $now_utc->modify( '+24 hours' )->format( 'Y-m-d H:i:s' ) ),
		);
	}
}
