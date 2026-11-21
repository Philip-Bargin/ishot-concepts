<?php
namespace IShot\Booking\Admin;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\OpeningHours;
use IShot\Booking\Persistence\BookingRepository;

/** One studio week (Monday to Sunday, studio time) of bookings and blocks for the calendar screen. */
final class CalendarWeek {

	/** Statuses not shown on the calendar: they no longer occupy time. */
	private const HIDDEN = array( 'cancelled', 'expired' );

	/**
	 * @return array{start: string, prev: string, next: string, days: array<int, array{date: string, hours: array{string, string}|null, items: array<int, array<string, mixed>>}>}
	 */
	public static function build( BookingRepository $repo, DateTimeImmutable $any_day, OpeningHours $hours ): array {
		$tz     = $hours->timezone;
		$monday = $any_day->setTimezone( $tz )->setTime( 0, 0 )->modify( 'monday this week' );
		$utc    = new DateTimeZone( 'UTC' );
		$rows   = $repo->between(
			$monday->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			$monday->modify( '+7 days' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' )
		);

		$days = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$date          = $monday->modify( "+{$i} days" )->format( 'Y-m-d' );
			$days[ $date ] = array(
				'date'  => $date,
				'hours' => $hours->windowFor( $date ),
				'items' => array(),
			);
		}

		foreach ( $rows as $row ) {
			if ( in_array( $row['status'], self::HIDDEN, true ) ) {
				continue;
			}
			$start = ( new DateTimeImmutable( $row['start_utc'], $utc ) )->setTimezone( $tz );
			$date  = $start->format( 'Y-m-d' );
			if ( ! isset( $days[ $date ] ) ) {
				continue; // starts in the neighbouring week; only its buffer reaches into this one
			}
			$days[ $date ]['items'][] = array(
				'id'      => (int) $row['id'],
				'kind'    => $row['kind'],
				'status'  => $row['status'],
				'start'   => $start->format( 'H:i' ),
				'end'     => ( new DateTimeImmutable( $row['end_utc'], $utc ) )->setTimezone( $tz )->format( 'H:i' ),
				'title'   => 'block' === $row['kind'] ? ( '' !== (string) $row['notes'] ? (string) $row['notes'] : __( 'Blocked time', 'ishot-booking' ) ) : $row['client_name'],
				'version' => (int) $row['version'],
			);
		}

		return array(
			'start' => $monday->format( 'Y-m-d' ),
			'prev'  => $monday->modify( '-7 days' )->format( 'Y-m-d' ),
			'next'  => $monday->modify( '+7 days' )->format( 'Y-m-d' ),
			'days'  => array_values( $days ),
		);
	}
}
