<?php
namespace IShot\Booking\Domain;

/**
 * Booking lifecycle. Values are what the database stores.
 *
 * requested → pending_deposit → confirmed → completed
 * Side exits: cancelled (any live state), expired (unpaid hold lapsed), no_show (after confirmed).
 */
enum BookingStatus: string {
	case Requested      = 'requested';
	case PendingDeposit = 'pending_deposit';
	case Confirmed      = 'confirmed';
	case Completed      = 'completed';
	case Cancelled      = 'cancelled';
	case Expired        = 'expired';
	case NoShow         = 'no_show';

	private const TRANSITIONS = array(
		'requested'       => array( 'pending_deposit', 'confirmed', 'cancelled', 'expired' ),
		'pending_deposit' => array( 'confirmed', 'cancelled', 'expired' ),
		'confirmed'       => array( 'completed', 'cancelled', 'no_show' ),
	);

	public function canTransitionTo( self $next ): bool {
		return in_array( $next->value, self::TRANSITIONS[ $this->value ] ?? array(), true );
	}

	/** Live bookings keep their slot locks; every other status releases them. */
	public function holdsSlot(): bool {
		return in_array( $this, array( self::Requested, self::PendingDeposit, self::Confirmed ), true );
	}
}
