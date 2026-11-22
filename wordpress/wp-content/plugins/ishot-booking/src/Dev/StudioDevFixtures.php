<?php
namespace IShot\Booking\Dev;

use IShot\Booking\Settings;
use IShot\Booking\Studio;

/**
 * DEV ONLY. Fills empty Studio details from the prototype's values so the local site can be
 * compared visually. Used by wordpress/dev/studio-details-dev.php and nothing else.
 *
 * It fails safe: every guard must pass before anything is written, and it never overwrites a
 * non-empty value. Everything it writes is recorded as unconfirmed, so nothing seeded is ever
 * treated as owner-approved.
 */
final class StudioDevFixtures {

	/** Prototype values (src/data.js `contact`). Facebook is deliberately absent: it is not supplied. */
	public const VALUES = array(
		'phone'     => '0881405259',
		'whatsapp'  => '+231 881 405 259',
		'instagram' => 'https://www.instagram.com/ishotconcept/',
		'email'     => 'ishotconcept01@gmail.com',
		'address'   => 'Monrovia, Liberia',
	);

	/**
	 * @param string $environment_type wp_get_environment_type().
	 * @param mixed  $opt_in           The value of ISHOT_DEV_FIXTURES, or null when undefined.
	 * @param string $home_url         home_url().
	 * @return array{ok: bool, reason: string, written: list<string>}
	 */
	public static function apply( string $environment_type, mixed $opt_in, string $home_url ): array {
		$reason = self::blocked( $environment_type, $opt_in, $home_url );
		if ( '' !== $reason ) {
			return array(
				'ok'      => false,
				'reason'  => $reason,
				'written' => array(),
			);
		}

		$s       = Settings::all();
		$values  = array();
		$written = array();
		foreach ( self::VALUES as $name => $value ) {
			$key = Studio::FIELDS[ $name ];
			if ( '' === trim( (string) $s[ $key ] ) ) {
				$values[ $key ] = $value;
				$written[]      = $name;
			}
		}
		if ( array() !== $written ) {
			$existing                     = is_array( $s['studio_unconfirmed'] ) ? $s['studio_unconfirmed'] : array();
			$values['studio_unconfirmed'] = array_values( array_unique( array_merge( $existing, $written ) ) );
			Settings::update( $values );
		}
		return array(
			'ok'      => true,
			'reason'  => '',
			'written' => $written,
		);
	}

	/** The reason writes are not allowed, or '' when every guard passes. */
	private static function blocked( string $environment_type, mixed $opt_in, string $home_url ): string {
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
