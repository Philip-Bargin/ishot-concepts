<?php
namespace IShot\Booking\Rest;

/**
 * Fixed-window request counter per client address, kept in transients (or the object cache).
 * Uses REMOTE_ADDR only; behind a trusted proxy, supply the real address through the
 * ishot_booking_client_ip filter rather than trusting X-Forwarded-For from anyone.
 */
final class RateLimiter {

	public function __construct(
		private readonly string $bucket,
		private readonly int $limit,
		private readonly int $window_seconds
	) {}

	/** Counts this request; false once the address has used up its allowance for the window. */
	public function hit(): bool {
		$ip  = (string) apply_filters( 'ishot_booking_client_ip', $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- hashed, never output.
		$key = 'ishot_rl_' . $this->bucket . '_' . md5( $ip );

		$count = (int) get_transient( $key );
		if ( $count >= $this->limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $this->window_seconds );
		return true;
	}
}
