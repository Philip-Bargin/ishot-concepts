<?php
/**
 * Footer support: the `ishot-concept/site` block-bindings source (the legal line's year). The
 * server-side touches the core Group blocks of parts/footer.html need are in inc/groups.php.
 *
 * @package IShot\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Value of the `ishot-concept/site` source. Keys: `year` (the current year in the site's timezone)
 * and `copyright` (the prototype's complete legal line). Core bindings replace a paragraph's whole
 * content (WP 6.7 WP_Block::replace_html), so the legal paragraph binds `copyright`, not `year`.
 *
 * @param array<string, mixed> $source_args Binding args (`key`).
 */
function ishot_theme_site_binding_value( array $source_args ): ?string {
	$year = wp_date( 'Y' );
	switch ( $source_args['key'] ?? '' ) {
		case 'year':
			return $year;
		case 'copyright':
			/* translators: %s: the current year */
			return sprintf( __( '© %s iShot Concept · Photography, creative direction, visual storytelling', 'ishot-concept' ), $year );
		default:
			return null;
	}
}

/** `init` callback: registers the `ishot-concept/site` bindings source. */
function ishot_theme_register_site_binding(): void {
	register_block_bindings_source(
		'ishot-concept/site',
		array(
			'label'              => __( 'iShot Concept site', 'ishot-concept' ),
			'get_value_callback' => 'ishot_theme_site_binding_value',
		)
	);
}
add_action( 'init', 'ishot_theme_register_site_binding' );
