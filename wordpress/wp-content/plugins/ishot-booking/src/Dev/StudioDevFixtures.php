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

	/** Prototype values (src/data.js `contact`; socials owner-supplied 2026-11-25). */
	public const VALUES = array(
		'phone'     => '0881405259',
		'whatsapp'  => '+231 881 405 259',
		'instagram' => 'https://www.instagram.com/ishot_concept/',
		'facebook'  => 'https://www.facebook.com/people/Ishot-Concept/100083810506889/',
		'email'     => 'ishotconcept01@gmail.com',
		'address'   => 'Monrovia, Liberia',
	);

	/**
	 * The only entry point. It reads the guard inputs itself (environment type, the
	 * ISHOT_DEV_FIXTURES constant, home_url()); callers cannot supply them.
	 *
	 * @return array{ok: bool, reason: string, written: list<string>}
	 */
	public static function run(): array {
		return self::execute(
			wp_get_environment_type(),
			defined( 'ISHOT_DEV_FIXTURES' ) ? constant( 'ISHOT_DEV_FIXTURES' ) : null,
			home_url()
		);
	}

	/**
	 * Private on purpose: the guard values must come from run(). Tests reach it by reflection to
	 * exercise each guard on its own.
	 *
	 * @param string $environment_type wp_get_environment_type().
	 * @param mixed  $opt_in           The value of ISHOT_DEV_FIXTURES, or null when undefined.
	 * @param string $home_url         home_url().
	 * @return array{ok: bool, reason: string, written: list<string>}
	 */
	private static function execute( string $environment_type, mixed $opt_in, string $home_url ): array {
		$reason = DevGuard::blocked( $environment_type, $opt_in, $home_url );
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
}
