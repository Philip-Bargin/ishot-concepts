<?php
namespace IShot\Booking;

/**
 * The studio's public contact details, read from the one settings option so they survive a theme change.
 * Consumers (header, footer, contact page, share row, booking success) must escape on output:
 * esc_html for text, esc_url for links.
 *
 * Only owner-confirmed values may be published. Values that were filled in for visual comparison
 * are listed by unconfirmed() until the owner saves or confirms them.
 */
final class Studio {

	/** Short name => settings key. Order is the order used by unconfirmed(). */
	public const FIELDS = array(
		'phone'     => 'studio_phone',
		'whatsapp'  => 'studio_whatsapp',
		'instagram' => 'studio_instagram_url',
		'facebook'  => 'studio_facebook_url',
		'email'     => 'studio_public_email',
		'address'   => 'studio_address',
	);

	/** @return array{phone: string, phone_href: ?string, whatsapp_url: ?string, instagram_url: ?string, facebook_url: ?string, email: string, address: string} */
	public static function details(): array {
		$s     = Settings::all();
		$phone = (string) $s['studio_phone'];
		$wa    = self::e164Digits( (string) $s['studio_whatsapp'] );
		$phd   = self::e164Digits( $phone );
		return array(
			'phone'         => $phone,
			'phone_href'    => null === $phd ? null : 'tel:+' . $phd,
			'whatsapp_url'  => null === $wa ? null : 'https://wa.me/' . $wa,
			'instagram_url' => self::socialUrl( (string) $s['studio_instagram_url'], 'instagram.com' ),
			'facebook_url'  => self::socialUrl( (string) $s['studio_facebook_url'], 'facebook.com' ),
			'email'         => is_email( (string) $s['studio_public_email'] ) ? (string) $s['studio_public_email'] : '',
			'address'       => (string) $s['studio_address'],
		);
	}

	/** @return list<array{id: 'instagram'|'facebook'|'whatsapp', label: string, url: ?string}> Always all three, in this order; a null url is an inactive icon. */
	public static function socials(): array {
		$d = self::details();
		return array(
			array(
				'id'    => 'instagram',
				'label' => 'Instagram',
				'url'   => $d['instagram_url'],
			),
			array(
				'id'    => 'facebook',
				'label' => 'Facebook',
				'url'   => $d['facebook_url'],
			),
			array(
				'id'    => 'whatsapp',
				'label' => 'WhatsApp',
				'url'   => $d['whatsapp_url'],
			),
		);
	}

	/**
	 * Short names (phone, whatsapp, instagram, facebook, email, address) whose value has not been
	 * confirmed by the studio. Empty fields are never listed.
	 *
	 * @return list<string>
	 */
	public static function unconfirmed(): array {
		$s    = Settings::all();
		$list = is_array( $s['studio_unconfirmed'] ) ? $s['studio_unconfirmed'] : array();
		$out  = array();
		foreach ( self::FIELDS as $name => $key ) {
			if ( in_array( $name, $list, true ) && '' !== trim( (string) $s[ $key ] ) ) {
				$out[] = $name;
			}
		}
		return $out;
	}

	/**
	 * E.164 digits (no plus) for a number written with a leading + or 00, otherwise null.
	 * A local number such as 0881... is never given a guessed country code.
	 */
	public static function e164Digits( string $number ): ?string {
		$number = trim( $number );
		if ( '' === $number || ! preg_match( '/^[0-9 +()\-]+$/', $number ) ) {
			return null;
		}
		$digits = (string) preg_replace( '/\D/', '', $number );
		if ( str_starts_with( $number, '00' ) ) {
			$digits = substr( $digits, 2 );
		} elseif ( ! str_starts_with( $number, '+' ) ) {
			return null;
		}
		return strlen( $digits ) >= 7 && strlen( $digits ) <= 15 && '0' !== $digits[0] ? $digits : null;
	}

	/** The URL when it is https on exactly $domain (optionally www. or m.), with no userinfo or port; otherwise null. */
	public static function socialUrl( string $url, string $domain ): ?string {
		$url = trim( $url );
		if ( '' === $url || preg_match( '/[\s\\\\\x00-\x1f\x7f]/', $url ) ) {
			return null;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return null;
		}
		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( ! preg_match( '/^(www\.|m\.)?' . preg_quote( $domain, '/' ) . '$/', $host ) ) {
			return null;
		}
		return esc_url_raw( $url, array( 'https' ) );
	}
}
