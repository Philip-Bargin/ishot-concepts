<?php
/**
 * The official logo files, printed as the prototype's Logo (src/components/Logo.jsx).
 *
 * @package IShot\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logo images. `auto` prints the full-colour and reversed files (app.css swaps them in Night);
 * `reversed` prints only the white file, for navy bands. Any other value is treated as `auto`.
 * The caller wraps the result in `<a class="fo-logo">`.
 *
 * The alternative text is the brand name shown in the image (the prototype's `logos.alt`), not
 * the editable site title, so it always describes the picture.
 *
 * @param string $variant `auto` or `reversed`.
 */
function ishot_theme_logo_html( string $variant = 'auto' ): string {
	$alt      = esc_attr__( 'iShot Concept', 'ishot-concept' );
	$full     = esc_url( get_theme_file_uri( 'assets/brand/ishot-concept-full-colour-trimmed.png' ) );
	$reversed = esc_url( get_theme_file_uri( 'assets/brand/ishot-concept-reversed-white-trimmed.png' ) );

	if ( 'reversed' === $variant ) {
		return '<img src="' . $reversed . '" alt="' . $alt . '">';
	}
	return '<img class="ish-logo__full" src="' . $full . '" alt="' . $alt . '"><img class="ish-logo__rev" src="' . $reversed . '" alt="' . $alt . '">';
}
