<?php
namespace IShot\Blocks;

/**
 * The studio's contact lines from Studio::details(). Used by the `ishot/contact-details` block.
 *
 * Footer variant: the Visit column of the prototype's Footer (src/components/Footer.jsx), printed
 * as direct children of the column with no wrapper of its own. Each line is left out when its value
 * is empty. A phone without a dialable `phone_href` prints as text in a <span> (the prototype always
 * linked `tel:`; the booking plugin only gives a href for a number it can dial).
 */
final class ContactDetails {

	/**
	 * Markup for the studio's details, or '' when the booking plugin (which owns them) is not loaded.
	 *
	 * @param string $variant      Block variant.
	 * @param string $studio_class The Studio class to read (a test seam for "plugin not active").
	 */
	public static function fromStudio( string $variant = 'footer', string $studio_class = 'IShot\Booking\Studio' ): string {
		$details = array( $studio_class, 'details' );
		if ( ! class_exists( $studio_class ) || ! is_callable( $details ) ) {
			return '';
		}
		return self::render( (array) $details(), $variant );
	}

	/**
	 * @param array{phone?: mixed, phone_href?: mixed, email?: mixed, address?: mixed} $details Studio::details() shape.
	 * @param string                                                                   $variant Block variant. Only `footer` exists so far (the Contact-page variant comes in Stage 2), so every value prints it.
	 */
	public static function render( array $details, string $variant = 'footer' ): string {
		return match ( $variant ) {
			default => self::footer( $details ),
		};
	}

	/**
	 * The footer lines: address, phone, email.
	 *
	 * @param array{phone?: mixed, phone_href?: mixed, email?: mixed, address?: mixed} $details Studio::details() shape.
	 */
	private static function footer( array $details ): string {
		$address = trim( (string) ( $details['address'] ?? '' ) );
		$phone   = trim( (string) ( $details['phone'] ?? '' ) );
		$href    = is_string( $details['phone_href'] ?? null ) ? esc_url( $details['phone_href'], array( 'tel' ) ) : '';
		$email   = trim( (string) ( $details['email'] ?? '' ) );

		$out = '';
		if ( '' !== $address ) {
			$out .= '<span class="small">' . esc_html( $address ) . '</span>';
		}
		if ( '' !== $phone ) {
			$out .= '' !== $href
				? '<a href="' . $href . '">' . esc_html( $phone ) . '</a>'
				: '<span>' . esc_html( $phone ) . '</span>';
		}
		if ( '' !== $email ) {
			$out .= '<a href="' . esc_url( 'mailto:' . $email, array( 'mailto' ) ) . '">' . esc_html( $email ) . '</a>';
		}
		return $out;
	}
}
