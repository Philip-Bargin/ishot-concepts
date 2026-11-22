<?php
/**
 * iShot Concept theme bootstrap.
 *
 * The design system's tokens.css and bundle.css are loaded unchanged (generated into assets/css by
 * `npm run sync:ds`), so the `fo-` markup renders exactly as in the approved React prototype.
 * app.css is the prototype's own app layer (`ish-` classes), generated the same way. theme.css only
 * adapts WordPress core blocks to the tokens.
 *
 * @package IShot\Theme
 */

defined( 'ABSPATH' ) || exit;

const ISHOT_THEME_STYLES = array(
	'ishot-tokens' => 'assets/css/tokens.css',
	'ishot-bundle' => 'assets/css/bundle.css',
	'ishot-app'    => 'assets/css/app.css',
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

/** Absolute path of the theme's compiled frame blocks (built by `npm run build:blocks`). */
function ishot_theme_blocks_directory(): string {
	return get_theme_file_path( 'build/blocks' );
}

/**
 * Registers every `<dir>/<name>/block.json`. A folder without one is skipped silently, and a name
 * that is already registered is left alone.
 *
 * @param string $dir Directory holding one sub-folder per built block.
 * @return list<string> Names of the blocks registered by this call.
 */
function ishot_theme_register_blocks( string $dir ): array {
	$registered = array();
	foreach ( (array) glob( rtrim( $dir, '/\\' ) . '/*/block.json' ) as $file ) {
		$meta = wp_json_file_decode( $file, array( 'associative' => true ) );
		$name = is_array( $meta ) && isset( $meta['name'] ) && is_string( $meta['name'] ) ? $meta['name'] : '';
		if ( '' !== $name && WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
			continue;
		}
		$type = register_block_type( dirname( $file ) );
		if ( $type instanceof WP_Block_Type ) {
			$registered[] = $type->name;
		}
	}
	return $registered;
}

/** `init` callback: registers the theme's own frame blocks. */
function ishot_theme_register_built_blocks(): void {
	ishot_theme_register_blocks( ishot_theme_blocks_directory() );
}
add_action( 'init', 'ishot_theme_register_built_blocks' );

// Core's remote pattern directory would offer the owner off-brand layouts.
add_filter( 'should_load_remote_block_patterns', '__return_false' );
