<?php
/**
 * Social links: the prototype's SocialLinks (src/components/SocialLinks.jsx), from Studio::socials().
 * Prints nothing when the iShot Booking plugin (which holds the studio details) is not active.
 *
 * @package IShot\Blocks
 */

defined( 'ABSPATH' ) || exit;

echo \IShot\Blocks\SocialLinks::fromStudio(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped in SocialLinks::render().
