<?php
namespace IShot\Booking\Admin;

use DateTimeImmutable;
use IShot\Booking\Application\StaffActions;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\SlotConflict;
use IShot\Booking\Persistence\StaleBooking;
use IShot\Booking\Settings;
use InvalidArgumentException;

/**
 * Bulk status changes from the All bookings list.
 *
 * Only actions that need no extra input are offered (deposit received needs a payment reference,
 * a reschedule needs a time). Every booking is checked with the same rule the detail screen uses
 * (BookingScreen::availableActions: lifecycle, "after the session started", capability), so a
 * bulk run can never do what a single click could not. Ineligible bookings are skipped and
 * reported; each change goes through StaffActions and is audited with bulk: true.
 */
final class BulkActions {

	public const MAX     = 100;
	public const ACTIONS = array( 'confirm', 'complete', 'no_show', 'cancel' );

	/** @return array<string, string> Bulk actions the current user may take, action => label. */
	public static function available(): array {
		$labels = array(
			'confirm'  => __( 'Confirm', 'ishot-booking' ),
			'complete' => __( 'Mark completed', 'ishot-booking' ),
			'no_show'  => __( 'Mark no-show', 'ishot-booking' ),
			'cancel'   => __( 'Cancel', 'ishot-booking' ),
		);
		return array_filter( $labels, static fn( string $action ) => current_user_can( StaffActions::ACTIONS[ $action ] ), ARRAY_FILTER_USE_KEY );
	}

	/**
	 * @param array<int|string> $ids
	 * @return array{done: int, skipped: array<int, string>, error: string|null} skipped: id => reason
	 */
	public static function apply( array $ids, string $action, DateTimeImmutable $now ): array {
		$result = array(
			'done'    => 0,
			'skipped' => array(),
			'error'   => null,
		);
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return array( 'error' => 'invalid' ) + $result;
		}
		if ( ! current_user_can( StaffActions::ACTIONS[ $action ] ) ) {
			return array( 'error' => 'forbidden' ) + $result;
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( count( $ids ) > self::MAX ) {
			return array( 'error' => 'too_many' ) + $result;
		}

		global $wpdb;
		$repo    = new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
		$actions = new StaffActions( $repo );
		foreach ( $ids as $id ) {
			$row = $repo->find( $id );
			if ( null === $row || 'booking' !== $row['kind'] ) {
				$result['skipped'][ $id ] = 'not_found';
				continue;
			}
			if ( ! in_array( $action, BookingScreen::availableActions( $row, $now ), true ) ) {
				$result['skipped'][ $id ] = 'not_allowed';
				continue;
			}
			try {
				$actions->perform( $id, $action, (int) $row['version'], get_current_user_id(), array( 'bulk' => true ) );
				++$result['done'];
			} catch ( StaleBooking | SlotConflict | InvalidArgumentException $e ) {
				$result['skipped'][ $id ] = 'changed'; // someone acted on it meanwhile
			}
		}
		return $result;
	}
}
