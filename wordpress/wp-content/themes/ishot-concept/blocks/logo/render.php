<?php
/**
 * Logo: the prototype's Logo (src/components/Logo.jsx). With `isLink` the images are wrapped in the
 * prototype's `<a class="fo-logo">` home link (the footer); otherwise the parent block wraps them.
 *
 * @package IShot\Theme
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$ishot_logo = ishot_theme_logo_html( (string) ( $attributes['variant'] ?? 'auto' ) );
if ( ! empty( $attributes['isLink'] ) ) {
	$ishot_logo = '<a class="fo-logo" href="' . esc_url( home_url( '/' ) ) . '">' . $ishot_logo . '</a>';
}
echo $ishot_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in ishot_theme_logo_html() and above.
