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

	/** Account requests per client address per hour (filter: ishot_booking_account_rate_limit). */
	public const RATE_LIMIT = 3;

	public const CHECK_EMAIL = 'check_email';
	public const NOT_CREATED = 'not_created';
	public const LINKED      = 'linked';

	/**
	 * Optional account at booking time. Creates a client account only for an unused address and
	 * signs nobody in: WordPress emails a set-your-password link, and using it is what proves the
	 * address (see onPasswordReset). For an address that already has an account nothing happens,
	 * and the answer is the same, so the form cannot be used to discover who has an account.
	 *
	 * @return array{status: string, user_id: int|null}
	 */
	public static function requestForBooking( string $email, string $name ): array {
		if ( ! (bool) apply_filters( 'ishot_booking_client_accounts', true ) ) {
			return array(
				'status'  => self::NOT_CREATED,
				'user_id' => null,
			);
		}
		$limiter = new \IShot\Booking\Rest\RateLimiter( 'account', (int) apply_filters( 'ishot_booking_account_rate_limit', self::RATE_LIMIT ), HOUR_IN_SECONDS );
		if ( ! $limiter->hit() ) {
			return array(
				'status'  => self::NOT_CREATED,
				'user_id' => null,
			);
		}
		$email = sanitize_email( $email );
		if ( '' === $email || false !== get_user_by( 'email', $email ) ) {
			return array(
				'status'  => self::CHECK_EMAIL,
				'user_id' => null,
			);
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => self::username( $email ),
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ), // never sent; replaced via the link
				'display_name' => sanitize_text_field( $name ),
				'first_name'   => sanitize_text_field( $name ),
				'role'         => 'ishot_client',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return array(
				'status'  => self::CHECK_EMAIL,
				'user_id' => null,
			);
		}
		wp_new_user_notification( $user_id, null, 'user' ); // the set-your-password email
		return array(
			'status'  => self::CHECK_EMAIL,
			'user_id' => $user_id,
		);
	}

	/**
	 * WordPress sends the set-your-password and lost-password emails itself, from its default
	 * wordpress@<host> sender (rejected outright on some hosts, and failing SPF/DMARC on others).
	 * For client accounts they go out from the studio's configured sender instead, like every
	 * other booking email.
	 *
	 * @param array{to: string, subject: string, message: string, headers: string|string[]} $email
	 */
	public static function useStudioSender( array $email, \WP_User $user ): array {
		$from = sanitize_email( (string) \IShot\Booking\Settings::all()['email_from_address'] );
		if ( '' === $from || ! in_array( 'ishot_client', (array) $user->roles, true ) ) {
			return $email;
		}
		$headers          = array_filter( (array) ( $email['headers'] ?? array() ) );
		$headers[]        = sprintf( 'From: %s <%s>', \IShot\Booking\Notifications\Mailer::headerText( \IShot\Booking\Settings::studioName() ), $from );
		$email['headers'] = $headers;
		return $email;
	}

	/** retrieve_password_notification_email passes the user third. */
	public static function useStudioSenderForReset( array $email, string $key, string $login, \WP_User $user ): array {
		return self::useStudioSender( $email, $user );
	}

	/** Setting a password through the emailed link proves the client owns the address. */
	public static function onPasswordReset( \WP_User $user ): void {
		if ( in_array( 'ishot_client', (array) $user->roles, true ) ) {
			self::markEmailVerified( $user->ID );
		}
	}

	/** A login that is not the email address (so usernames don't expose addresses). */
	private static function username( string $email ): string {
		$base = sanitize_user( strstr( $email, '@', true ), true );
		$base = '' !== $base ? substr( $base, 0, 40 ) : 'client';
		do {
			$login = $base . '-' . wp_rand( 1000, 9999 );
		} while ( username_exists( $login ) );
		return $login;
	}

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
