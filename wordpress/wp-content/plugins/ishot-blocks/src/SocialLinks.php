<?php
namespace IShot\Blocks;

/**
 * The prototype's SocialLinks (src/components/SocialLinks.jsx): Instagram, Facebook and WhatsApp,
 * always in that order. A platform with a URL is a link that opens in a new tab; one without is the
 * inactive, labelled, non-focusable icon. Used by the `ishot/social-links` block.
 */
final class SocialLinks {

	/** The only platforms the site shows, in order (owner decision 2026-11-21). */
	private const ORDER = array( 'instagram', 'facebook', 'whatsapp' );

	/**
	 * Markup for the studio's socials, or '' when the booking plugin (which owns them) is not loaded.
	 *
	 * @param string $studio_class The Studio class to read (a test seam for "plugin not active").
	 */
	public static function fromStudio( string $studio_class = 'IShot\\Booking\\Studio' ): string {
		$socials = array( $studio_class, 'socials' );
		if ( ! class_exists( $studio_class ) || ! is_callable( $socials ) ) {
			return '';
		}
		return self::render( (array) $socials() );
	}

	/**
	 * @param array<int, array{id?: mixed, label?: mixed, url?: mixed}> $socials Studio::socials() shape.
	 */
	public static function render( array $socials ): string {
		$by_id = array();
		foreach ( $socials as $social ) {
			$id = (string) ( $social['id'] ?? '' );
			if ( in_array( $id, self::ORDER, true ) && ! isset( $by_id[ $id ] ) ) {
				$by_id[ $id ] = $social;
			}
		}

		$out = '';
		foreach ( self::ORDER as $id ) {
			if ( ! isset( $by_id[ $id ] ) ) {
				continue;
			}
			$label = (string) ( $by_id[ $id ]['label'] ?? '' );
			$url   = is_string( $by_id[ $id ]['url'] ?? null ) ? esc_url( $by_id[ $id ]['url'], array( 'https' ) ) : '';
			$glyph = Icons::svg( $id );
			if ( '' !== $url ) {
				/* translators: %s: platform name, e.g. Instagram */
				$name = sprintf( __( '%s (opens in a new tab)', 'ishot-blocks' ), $label );
				$out .= '<a class="fo-icon-btn" href="' . $url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $name ) . '">' . $glyph . '</a>';
			} else {
				/* translators: %s: platform name, e.g. Facebook */
				$name = esc_attr( sprintf( __( '%s: link not added yet', 'ishot-blocks' ), $label ) );
				$out .= '<span class="fo-icon-btn ish-social--pending" role="img" aria-label="' . $name . '" title="' . $name . '">' . $glyph . '</span>';
			}
		}
		return $out;
	}
}
