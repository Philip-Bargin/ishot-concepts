<?php
namespace IShot\Booking\Payments;

/**
 * How clients pay a deposit. Only ManualGateway exists today (Stripe does not serve Liberia);
 * a PayPal or mobile-money gateway can implement this later without a schema change: a booking
 * already records which gateway took its deposit and that gateway's payment reference.
 */
interface PaymentGateway {

	/** Stable id stored in ishot_bookings.deposit_gateway (max 20 chars). */
	public function id(): string;

	/** Name staff see. */
	public function label(): string;

	/**
	 * What the client is told to do to pay, printed in the deposit emails.
	 *
	 * @param array<string, mixed> $snapshot The booking as the email describes it (Outbox::snapshot()).
	 */
	public function instructions( array $snapshot ): string;

	/** Tidies a staff-entered payment reference for storage; '' when there is none. */
	public function normaliseReference( string $reference ): string;
}
