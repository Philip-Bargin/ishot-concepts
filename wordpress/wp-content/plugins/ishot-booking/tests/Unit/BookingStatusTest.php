<?php
namespace IShot\Booking\Tests\Unit;

use IShot\Booking\Domain\BookingStatus as S;
use PHPUnit\Framework\TestCase;

/**
 * Lifecycle: requested → pending_deposit → confirmed → completed, with cancelled, expired
 * and no_show as side exits. Terminal states never move again.
 */
final class BookingStatusTest extends TestCase {

	/** @return array<string, array{S, S}> */
	public static function allowed(): array {
		return array(
			'studio asks for deposit'       => array( S::Requested, S::PendingDeposit ),
			'studio confirms without deposit' => array( S::Requested, S::Confirmed ),
			'request cancelled'             => array( S::Requested, S::Cancelled ),
			'request hold lapses'           => array( S::Requested, S::Expired ),
			'deposit received'              => array( S::PendingDeposit, S::Confirmed ),
			'deposit hold lapses'           => array( S::PendingDeposit, S::Expired ),
			'cancelled before deposit'      => array( S::PendingDeposit, S::Cancelled ),
			'session done'                  => array( S::Confirmed, S::Completed ),
			'confirmed then cancelled'      => array( S::Confirmed, S::Cancelled ),
			'client did not attend'         => array( S::Confirmed, S::NoShow ),
		);
	}

	/** @dataProvider allowed */
	public function test_allowed_transitions( S $from, S $to ): void {
		$this->assertTrue( $from->canTransitionTo( $to ) );
	}

	/** @return array<string, array{S, S}> */
	public static function forbidden(): array {
		return array(
			'skip straight to completed'    => array( S::Requested, S::Completed ),
			'completed is terminal'         => array( S::Completed, S::Cancelled ),
			'cancelled is terminal'         => array( S::Cancelled, S::Confirmed ),
			'expired cannot revive'         => array( S::Expired, S::Confirmed ),
			'no-show is terminal'           => array( S::NoShow, S::Completed ),
			'confirmed cannot expire'       => array( S::Confirmed, S::Expired ),
			'no going back to requested'    => array( S::PendingDeposit, S::Requested ),
			'same status is not a change'   => array( S::Confirmed, S::Confirmed ),
		);
	}

	/** @dataProvider forbidden */
	public function test_forbidden_transitions( S $from, S $to ): void {
		$this->assertFalse( $from->canTransitionTo( $to ) );
	}

	public function test_only_live_bookings_hold_their_slot(): void {
		$holding = array_values( array_filter( S::cases(), static fn( S $s ) => $s->holdsSlot() ) );

		$this->assertSame( array( S::Requested, S::PendingDeposit, S::Confirmed ), $holding );
	}

	public function test_status_round_trips_through_its_stored_value(): void {
		$this->assertSame( S::PendingDeposit, S::from( 'pending_deposit' ) );
		$this->assertSame( 'no_show', S::NoShow->value );
	}
}
