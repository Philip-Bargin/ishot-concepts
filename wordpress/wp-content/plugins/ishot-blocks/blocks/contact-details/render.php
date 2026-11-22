<?php
/**
 * Contact details: the Visit lines of the prototype's Footer (src/components/Footer.jsx), from
 * Studio::details(). Prints nothing when the iShot Booking plugin (which holds them) is not active.
 *
 * @package IShot\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

echo \IShot\Blocks\ContactDetails::fromStudio( (string) ( $attributes['variant'] ?? 'footer' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped in ContactDetails::render().
