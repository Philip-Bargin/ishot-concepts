<?php
namespace IShot\Booking\Application;

use DateTimeImmutable;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\SlotConflict;
use IShot\Booking\Persistence\StaleBooking;
use InvalidArgumentException;

/**
 * What studio staff can do to a booking, and the capability each action needs.
 * Staff are not bound by client policy (lead time, opening hours), only by slot exclusivity.
 */
final class StaffActions {

	/** Action => required capability. */
	public const ACTIONS = array(
		'confirm'          => 'edit_ishot_bookings',
		'complete'         => 'edit_ishot_bookings',
		'no_show'          => 'edit_ishot_bookings',
		'request_deposit'  => 'manage_ishot_bookings',
		'deposit_received' => 'manage_ishot_bookings',
		'cancel'           => 'manage_ishot_bookings',
		'reschedule'       => 'manage_ishot_bookings',
	);

	public function __construct( private readonly BookingRepository $repo ) {}

	/**
	 * @param array{start?: DateTimeImmutable, deposit_amount?: float|null, note?: string, reason?: string} $args
	 * @throws InvalidArgumentException A transition the lifecycle forbids, or a missing argument.
	 * @throws StaleBooking             Someone else changed the booking first.
	 * @throws SlotConflict             Reschedule target is taken.
	 */
	public function perform( int $id, string $action, int $version, int $actor, array $args = array() ): void {
		$note    = array_filter( array( 'note' => $args['note'] ?? '' ) );
		$reason  = array_filter( array( 'reason' => $args['reason'] ?? '' ) );
		$confirm = array( 'hold_expires_at' => null );

		match ( $action ) {
			'confirm'          => $this->repo->transition( $id, BookingStatus::Confirmed, $version, $actor, $confirm, $note ),
			'complete'         => $this->repo->transition( $id, BookingStatus::Completed, $version, $actor, array(), $note ),
			'no_show'          => $this->repo->transition( $id, BookingStatus::NoShow, $version, $actor, array(), $note ),
			'request_deposit'  => $this->repo->transition(
				$id,
				BookingStatus::PendingDeposit,
				$version,
				$actor,
				array_filter(
					array(
						'deposit_status' => 'awaiting',
						'deposit_amount' => isset( $args['deposit_amount'] ) ? sprintf( '%.2f', $args['deposit_amount'] ) : null,
					)
				),
				$note
			),
			'deposit_received' => $this->repo->transition( $id, BookingStatus::Confirmed, $version, $actor, $confirm + array( 'deposit_status' => 'received' ), $note ),
			'cancel'           => $this->repo->transition( $id, BookingStatus::Cancelled, $version, $actor, array(), array( 'by' => 'studio' ) + $reason ),
			'reschedule'       => $this->repo->reschedule(
				$id,
				$args['start'] ?? throw new InvalidArgumentException( 'A new start time is required.' ),
				$version,
				$actor
			),
			default            => throw new InvalidArgumentException( "Unknown action {$action}." ),
		};
	}
}
