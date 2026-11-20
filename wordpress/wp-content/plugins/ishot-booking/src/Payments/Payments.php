<?php
namespace IShot\Booking\Payments;

/** Picks the deposit gateway. Another plugin can supply one with the ishot_booking_payment_gateway filter. */
final class Payments {

	public static function active(): PaymentGateway {
		$gateway = apply_filters( 'ishot_booking_payment_gateway', null );
		return $gateway instanceof PaymentGateway ? $gateway : new ManualGateway();
	}

	/** The gateway that recorded a deposit, for display; null when it is no longer installed. */
	public static function byId( ?string $id ): ?PaymentGateway {
		if ( null === $id || '' === $id ) {
			return null;
		}
		$active = self::active();
		if ( $active->id() === $id ) {
			return $active;
		}
		return ManualGateway::ID === $id ? new ManualGateway() : null;
	}
}
