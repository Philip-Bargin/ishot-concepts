<?php
/**
 * Logo: the prototype's Logo (src/components/Logo.jsx). The parent block or part wraps it in
 * `<a class="fo-logo" href="/">`.
 *
 * @package IShot\Theme
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

echo ishot_theme_logo_html( (string) ( $attributes['variant'] ?? 'auto' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in ishot_theme_logo_html().
