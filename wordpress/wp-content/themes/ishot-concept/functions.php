<?php
/**
 * iShot Concept theme bootstrap.
 *
 * The design system's tokens.css and bundle.css are loaded unchanged (generated into assets/css by
 * `npm run sync:ds`), so the `fo-` markup renders exactly as in the approved React prototype.
 * theme.css only adapts WordPress core blocks to the tokens.
 *
 * @package IShot\Theme
 */

defined( 'ABSPATH' ) || exit;

const ISHOT_THEME_STYLES = array(
	'ishot-tokens' => 'assets/css/tokens.css',
	'ishot-bundle' => 'assets/css/bundle.css',
	'ishot-theme'  => 'assets/css/theme.css',
);

/** Fonts used above the fold on every page; preloaded so headings don't swap late. */
const ISHOT_THEME_PRELOAD_FONTS = array(
	'assets/fonts/oswald-latin-700-normal.woff2',
	'assets/fonts/space-grotesk-latin-400-normal.woff2',
);

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'ishot-concept', get_template_directory() . '/languages' );
		add_theme_support( 'editor-styles' );
		add_editor_style( array_values( ISHOT_THEME_STYLES ) );
		// Core's generic patterns don't follow the design system; the theme ships its own (Phase 3f).
		remove_theme_support( 'core-block-patterns' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$deps = array();
		foreach ( ISHOT_THEME_STYLES as $handle => $path ) {
			wp_enqueue_style( $handle, get_theme_file_uri( $path ), $deps, (string) filemtime( get_theme_file_path( $path ) ) );
			$deps = array( $handle );
		}
	}
);

add_action(
	'wp_head',
	static function (): void {
		foreach ( ISHOT_THEME_PRELOAD_FONTS as $path ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( get_theme_file_uri( $path ) ) );
		}
	},
	1
);

// Core's remote pattern directory would offer the owner off-brand layouts.
add_filter( 'should_load_remote_block_patterns', '__return_false' );
