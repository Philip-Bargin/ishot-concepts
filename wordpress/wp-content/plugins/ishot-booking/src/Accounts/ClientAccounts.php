<?php
namespace IShot\Booking\Accounts;

/**
 * Client identities. A booking belongs to an account only when it was made while signed in, or
 * when the account has proved it owns the booking's email address. Typing an address into a
 * booking form proves nothing, so unverified addresses never unlock bookings.
 *
 * Verification is stored as the verified address itself (user meta), so changing the account's
 * email withdraws access until the new address is verified.
 */
final class ClientAccounts {

	public const VERIFIED_META = 'ishot_verified_email';

	public static function markEmailVerified( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( false !== $user ) {
			update_user_meta( $user_id, self::VERIFIED_META, strtolower( $user->user_email ) );
		}
	}

	/** The account's email when it is verified, else null. */
	public static function verifiedEmail( int $user_id ): ?string {
		$user = get_userdata( $user_id );
		if ( false === $user ) {
			return null;
		}
		$verified = (string) get_user_meta( $user_id, self::VERIFIED_META, true );
		return '' !== $verified && strtolower( $user->user_email ) === $verified ? $verified : null;
	}
}
