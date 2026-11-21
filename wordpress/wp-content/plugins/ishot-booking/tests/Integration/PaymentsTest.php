<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use IShot\Booking\Application\StaffActions;
use IShot\Booking\Notifications\Outbox;
use IShot\Booking\Notifications\Renderer;
use IShot\Booking\Payments\ManualGateway;
use IShot\Booking\Payments\PaymentGateway;
use IShot\Booking\Payments\Payments;
use IShot\Booking\Settings;
use WP_UnitTestCase;

/** Deposits go through the PaymentGateway seam: manual today, pluggable later. */
final class PaymentsTest extends WP_UnitTestCase {

	use BookingFixtures;

	public function set_up(): void {
		parent::set_up();
		$this->resetBookings();
	}

	public function tear_down(): void {
		remove_all_filters( 'ishot_booking_payment_gateway' );
		parent::tear_down();
	}

	private function depositEmailText( int $id ): string {
		$rows = array_filter( ( new Outbox() )->forBooking( $id ), static fn( array $r ) => 'deposit_requested' === $r['template'] );
		return ( new Renderer() )->render( reset( $rows ), new DateTimeImmutable( '2026-12-01 08:00:00' ) )->text;
	}

	public static function fakeGateway(): PaymentGateway {
		return new class() implements PaymentGateway {
			public function id(): string {
				return 'momo';
			}
			public function label(): string {
				return 'Mobile money';
			}
			public function instructions( array $snapshot ): string {
				return 'Dial *144# and pay booking ' . $snapshot['id'];
			}
			public function normaliseReference( string $reference ): string {
				return strtoupper( trim( $reference ) );
			}
		};
	}

	public function test_manual_is_the_default_and_uses_the_studio_instructions(): void {
		Settings::update( array( 'deposit_instructions' => 'Send Orange Money to 0880 000 000.' ) );
		$this->assertInstanceOf( ManualGateway::class, Payments::active() );

		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		( new StaffActions( $this->repo ) )->perform( $id, 'request_deposit', 1, 1, array( 'deposit_amount' => 40.0 ) );
		$this->assertStringContainsString( 'Send Orange Money to 0880 000 000.', $this->depositEmailText( $id ) );
	}

	public function test_a_plugged_in_gateway_supplies_instructions_and_records_its_payments(): void {
		add_filter( 'ishot_booking_payment_gateway', array( self::class, 'fakeGateway' ) );
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$actions = new StaffActions( $this->repo );
		$actions->perform( $id, 'request_deposit', 1, 1, array( 'deposit_amount' => 40.0 ) );

		$this->assertStringContainsString( "Dial *144# and pay booking {$id}", $this->depositEmailText( $id ) );

		$actions->perform( $id, 'deposit_received', 2, 1, array( 'reference' => ' tx-77 ' ) );
		$row = $this->repo->find( $id );
		$this->assertSame( array( 'momo', 'TX-77' ), array( $row['deposit_gateway'], $row['deposit_reference'] ) );
		$this->assertSame( 'momo', Payments::byId( 'momo' )?->id() );
	}

	public function test_a_deposit_recorded_by_a_removed_gateway_is_still_readable(): void {
		$this->assertNull( Payments::byId( 'paypal' ) );
		$this->assertInstanceOf( ManualGateway::class, Payments::byId( 'manual' ) );
		$this->assertNull( Payments::byId( null ) );
	}

	public function test_manual_references_are_trimmed_and_capped(): void {
		$this->assertSame( 'a b', ( new ManualGateway() )->normaliseReference( "  a \n b " ) );
		$this->assertSame( 100, mb_strlen( ( new ManualGateway() )->normaliseReference( str_repeat( 'x', 300 ) ) ) );
	}
}
