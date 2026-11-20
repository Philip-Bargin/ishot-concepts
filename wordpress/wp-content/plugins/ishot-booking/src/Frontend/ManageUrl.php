<?php
namespace IShot\Booking\Frontend;

use IShot\Booking\Settings;

/**
 * The manage-booking link convention, for every email and the future WordPress front end:
 *
 *     https://<site>/manage-booking/#token=<64 hex>
 *
 * - The token lives only in the URL fragment. Browsers never send fragments to servers, so it
 *   does not appear in access logs, proxies, analytics or the Referer header.
 * - The page's script reads location.hash, calls GET/PATCH /wp-json/ishot/v1/manage with the
 *   token in the X-Ishot-Manage-Token header (never in a URL), then clears the hash with
 *   history.replaceState.
 * - The manage page is served with Referrer-Policy: no-referrer, no-store and noindex.
 * - Older shapes (/manage/<token> from the prototype, ?token=<token>) are redirected once to the
 *   fragment form, with the same privacy headers.
 */
final class ManageUrl {

	public const HEADER = 'X-Ishot-Manage-Token';

	/** @var array<string, string> */
	public const HEADERS = array(
		'Referrer-Policy' => 'no-referrer',
		'Cache-Control'   => 'no-store, private',
		'X-Robots-Tag'    => 'noindex, nofollow',
	);

	private const TOKEN = '[0-9a-f]{64}';

	public static function for( string $token ): string {
		return Settings::manageUrl() . '#token=' . rawurlencode( $token );
	}

	/** Where an old-style link should go, or null when the request is not one. */
	public static function legacyTarget( string $request_uri ): ?string {
		$path  = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$query = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
		if ( 1 === preg_match( '#^/manage/(' . self::TOKEN . ')/?$#', $path, $m ) ) {
			return self::for( $m[1] );
		}
		if ( self::isManagePath( $path ) && '' !== $query ) {
			parse_str( $query, $args );
			$token = is_string( $args['token'] ?? null ) ? $args['token'] : '';
			if ( 1 === preg_match( '#^' . self::TOKEN . '$#', $token ) ) {
				return self::for( $token );
			}
		}
		return null;
	}

	public static function isManagePath( string $path ): bool {
		$manage = (string) wp_parse_url( Settings::manageUrl(), PHP_URL_PATH );
		return '' !== $manage && rtrim( $path, '/' ) === rtrim( $manage, '/' );
	}

	/** template_redirect: send old-style links to the fragment form before anything renders. */
	public static function redirectLegacy(): void {
		$target = self::legacyTarget( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
		if ( null === $target ) {
			return;
		}
		self::sendPrivacyHeaders();
		wp_safe_redirect( $target, 302 );
		exit;
	}

	/** send_headers: the manage page itself never leaks its URL onward and is never cached. */
	public static function protectManagePage(): void {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		if ( self::isManagePath( $path ) ) {
			self::sendPrivacyHeaders();
		}
	}

	private static function sendPrivacyHeaders(): void {
		if ( headers_sent() ) {
			return;
		}
		foreach ( self::HEADERS as $name => $value ) {
			header( "{$name}: {$value}" );
		}
	}

	/** The token sent by the manage page, from the header only; '' when absent or malformed. */
	public static function tokenFromHeader( ?string $value ): string {
		$value = strtolower( trim( (string) $value ) );
		return 1 === preg_match( '#^' . self::TOKEN . '$#', $value ) ? $value : '';
	}
}
