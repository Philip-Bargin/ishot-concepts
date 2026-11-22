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

/**
 * Editor only, after the four above: the design system's `.fo-root` base rules (border-box sizing,
 * reduced motion) applied to the editor canvas root, `.editor-styles-wrapper`. The templates wrap
 * everything in `.fo-root`, but the Site Editor shows a template part (and the post editor a post)
 * without that wrapper. Generated from bundle.css by `npm run sync:ds` (ruling R-1.4-4).
 */
const ISHOT_THEME_EDITOR_ROOT_STYLE = 'assets/css/editor-root.css';

/** Editor only, last: hand-written canvas fixes for blocks the editor wraps (assets/css/editor.css). */
const ISHOT_THEME_EDITOR_CANVAS_STYLE = 'assets/css/editor.css';

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
		add_editor_style( array_merge( array_values( ISHOT_THEME_STYLES ), array( ISHOT_THEME_EDITOR_ROOT_STYLE, ISHOT_THEME_EDITOR_CANVAS_STYLE ) ) );
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

require_once __DIR__ . '/inc/footer.php';
require_once __DIR__ . '/inc/groups.php';
require_once __DIR__ . '/inc/logo.php';
require_once __DIR__ . '/inc/navigation.php';
require_once __DIR__ . '/inc/skip-link.php';
require_once __DIR__ . '/inc/templates.php';

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

/** The localStorage key holding the Night choice (`light` or `dark`); the same as `src/lib/theme.js`. */
function ishot_theme_storage_key(): string {
	return 'ishot-theme';
}

/**
 * Starts every page in light and lets the head script switch to the stored choice. The only
 * per-viewer state is applied client-side, so cached pages are identical for everyone.
 *
 * @param string $output Existing language attributes.
 * @return string
 */
function ishot_theme_html_theme_attribute( string $output ): string {
	if ( is_admin() || str_contains( $output, 'data-theme=' ) ) {
		return $output;
	}
	return trim( $output . ' data-theme="light"' );
}
add_filter( 'language_attributes', 'ishot_theme_html_theme_attribute' );

/** Prints the tiny inline script that applies the stored Night choice before any stylesheet. */
function ishot_theme_print_night_bootstrap(): void {
	$key    = esc_js( ishot_theme_storage_key() );
	$script = "try{document.documentElement.dataset.theme=localStorage.getItem('{$key}')==='dark'?'dark':'light'}catch(e){}";
	wp_print_inline_script_tag( $script, array( 'id' => 'ishot-night-bootstrap' ) );
}
add_action( 'wp_head', 'ishot_theme_print_night_bootstrap', 0 );
