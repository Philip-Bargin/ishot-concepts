<?php
namespace IShot\Blocks;

/**
 * Inline SVG icons: the Lucide set the prototype uses (generated into Icons/lucide-data.php by
 * scripts/export-icons.mjs) plus the three brand glyphs from the prototype's SocialLinks.
 */
final class Icons {

	/** Attribute pair every unlabelled icon carries (matches the prototype's Icon). */
	private const HIDDEN = ' aria-hidden="true" focusable="false"';

	/**
	 * Brand glyphs, copied byte for byte from GLYPHS/BrandIcon in src/components/SocialLinks.jsx
	 * (as React serialises them).
	 */
	private const BRAND_HEAD = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5"' . self::HIDDEN . '>';

	private const BRAND = array(
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="0.6" fill="currentColor"></circle>',
		'facebook'  => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v7h4v-7h3l1-4h-4V8z"></path>',
		'whatsapp'  => '<path d="M3.5 20.5l1.3-4.3A8.5 8.5 0 1 1 7.9 19.3z"></path><path d="M9 8.2c-.2 3.3 3.4 6.9 6.8 6.8l.9-1.7-2.1-1-1 .9a5 5 0 0 1-2.6-2.6l.9-1-1-2.1z"></path>',
	);

	/** @var array<string, string>|null */
	private static ?array $lucide = null;

	/**
	 * The SVG for an icon, or '' when the name is unknown.
	 *
	 * @param string $name  kebab-case Lucide name (arrow-right, circle-alert, ...) or instagram | facebook | whatsapp.
	 * @param string $label Accessible name. Empty (default) hides the icon from assistive technology.
	 * @param int    $size  Rendered width and height in px (the prototype's Icon `size`); the viewBox is unchanged.
	 */
	public static function svg( string $name, string $label = '', int $size = 16 ): string {
		$svg = self::markup( $name );
		if ( '' === $svg ) {
			return '';
		}
		$end = strpos( $svg, '>' );
		if ( false === $end ) {
			return '';
		}
		$tag  = substr( $svg, 0, $end );
		$rest = substr( $svg, $end );
		if ( $size > 0 && 16 !== $size ) {
			$tag = (string) preg_replace( '/\s(width|height)="\d+"/', ' $1="' . $size . '"', $tag );
		}
		if ( '' !== $label ) {
			// Attribute-order independent: drop the hiding attributes, then add the accessible name.
			$tag  = (string) preg_replace( '/\s(aria-hidden|focusable|role|aria-label)="[^"]*"/', '', $tag );
			$tag .= ' role="img" aria-label="' . htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ) . '" focusable="false"';
		}
		return $tag . $rest;
	}

	private static function markup( string $name ): string {
		if ( isset( self::BRAND[ $name ] ) ) {
			return self::BRAND_HEAD . self::BRAND[ $name ] . '</svg>';
		}
		if ( null === self::$lucide ) {
			/** @var array<string, string> $data */
			$data         = require __DIR__ . '/Icons/lucide-data.php';
			self::$lucide = $data;
		}
		return self::$lucide[ $name ] ?? '';
	}
}
