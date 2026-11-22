<?php
/**
 * The prototype's skip link (src/App.jsx), first in <body>. Core's block-theme skip link is
 * removed so there is only one.
 *
 * @package IShot\Theme
 */

defined( 'ABSPATH' ) || exit;

/** Prints `<a href="#main" class="ish-skip">Skip to content</a>` (the templates give main id="main"). */
function ishot_theme_print_skip_link(): void {
	echo '<a href="#main" class="ish-skip">' . esc_html__( 'Skip to content', 'ishot-concept' ) . '</a>';
}
add_action( 'wp_body_open', 'ishot_theme_print_skip_link', 0 );

// Core adds its own skip link to block themes (WP 6.4+); the prototype's replaces it. The deprecated
// wp_footer printer is normally unhooked by the enqueue function, so it has to go too.
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_block_template_skip_link' );
remove_action( 'wp_footer', 'the_block_template_skip_link' );
