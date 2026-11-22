<?php
namespace IShot\Booking\Dev;

/**
 * DEV ONLY. The layered guards every dev fixture must pass before it writes anything (ruling R5):
 * environment type local/development, ISHOT_DEV_FIXTURES === true (set only in .wp-env.json
 * env.development.config), and a local-looking home_url() host. Each fixture reads the inputs
 * itself in its run() and passes them here; never overwriting owner content is the fixture's own rule.
 */
final class DevGuard {

	/**
	 * The reason writes are not allowed, or '' when every guard passes.
	 *
	 * @param string $environment_type wp_get_environment_type().
	 * @param mixed  $opt_in           The value of ISHOT_DEV_FIXTURES, or null when undefined.
	 * @param string $home_url         home_url().
	 */
	public static function blocked( string $environment_type, mixed $opt_in, string $home_url ): string {
		if ( ! in_array( $environment_type, array( 'local', 'development' ), true ) ) {
			return sprintf( 'Environment type is "%s"; only local or development may run dev fixtures.', $environment_type );
		}
		if ( true !== $opt_in ) {
			return 'ISHOT_DEV_FIXTURES is not defined as true (set it in .wp-env.json env.development.config only).';
		}
		$host = wp_parse_url( $home_url, PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';
		$user = wp_parse_url( $home_url, PHP_URL_USER );
		if ( '' === $host || null !== $user || ! self::isLocalHost( $host ) ) {
			return sprintf( 'Site host "%s" is not localhost, 127.0.0.1, *.local or *.test.', $host );
		}
		return '';
	}

	private static function isLocalHost( string $host ): bool {
		return in_array( $host, array( 'localhost', '127.0.0.1' ), true ) || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.test' );
	}
}
