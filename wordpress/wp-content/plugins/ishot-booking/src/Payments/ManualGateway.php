<?php
namespace IShot\Booking\Payments;

use IShot\Booking\Settings;

/**
 * Offline deposits (bank transfer, mobile money, cash): the client follows the studio's written
 * instructions and staff press "Mark deposit received", optionally noting the payment reference.
 */
final class ManualGateway implements PaymentGateway {

	public const ID = 'manual';

	public function id(): string {
		return self::ID;
	}

	public function label(): string {
		return __( 'Manual (offline) payment', 'ishot-booking' );
	}

	public function instructions( array $snapshot ): string {
		$text = trim( (string) Settings::all()['deposit_instructions'] );
		return '' !== $text ? $text : __( 'Reply to this email and we will send you the payment details.', 'ishot-booking' );
	}

	public function normaliseReference( string $reference ): string {
		return mb_substr( trim( (string) preg_replace( '/\s+/', ' ', $reference ) ), 0, 100 );
	}
}
