<?php
/**
 * Footer support: the `ishot-concept/site` block-bindings source (the legal line's year) and the
 * server-side touches the core Group blocks of parts/footer.html need to print Footer.jsx's markup.
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

/**
 * `render_block_core/group` filter for the footer's columns (`.fo-footer__col`) and social wrapper
 * (`.ish-social`):
 * - a column whose only child is its `.fo-eyebrow`, or a social wrapper with no child, is not
 *   printed (no empty wrapper or landmark when a plugin block prints nothing);
 * - a `nav.fo-footer__col` is labelled by its eyebrow: `aria-labelledby` = the eyebrow's id
 *   (Group has no aria-labelledby attribute). A value already set is kept.
 * Any other group, or markup the HTML API cannot read, is returned unchanged.
 *
 * @param string $content Rendered group markup.
 */
function ishot_theme_footer_group( string $content ): string {
	$tags = new WP_HTML_Tag_Processor( $content );
	if ( ! $tags->next_tag() ) {
		return $content;
	}
	$is_column = true === $tags->has_class( 'fo-footer__col' );
	$is_social = true === $tags->has_class( 'ish-social' );
	if ( ! $is_column && ! $is_social ) {
		return $content;
	}

	// get_current_depth() is WP 6.6+. The theme requires 6.7; never fatal on an older core.
	if ( ! method_exists( 'WP_HTML_Processor', 'get_current_depth' ) ) { // @phpstan-ignore function.alreadyNarrowedType (the stubs are 6.7; the guard is for older cores)
		return $content;
	}
	$html = WP_HTML_Processor::create_fragment( $content );
	if ( null === $html ) {
		return $content;
	}
	$depth       = null; // The group's own depth, read from its opening tag.
	$label_id    = null;
	$first       = true;
	$has_content = false;
	// Direct children only. Stops at the first child that is not the leading eyebrow.
	while ( $html->next_tag() ) {
		$current = $html->get_current_depth();
		if ( null === $depth ) {
			$depth = $current;
			continue;
		}
		if ( $current <= $depth ) {
			break;
		}
		if ( $current !== $depth + 1 ) {
			continue;
		}
		if ( $is_column && $first && 'P' === $html->get_tag() && true === $html->has_class( 'fo-eyebrow' ) ) {
			$id       = $html->get_attribute( 'id' );
			$label_id = is_string( $id ) && '' !== $id ? $id : null;
			$first    = false;
			continue;
		}
		$has_content = true;
		break;
	}
	if ( null !== $html->get_last_error() ) {
		return $content;
	}
	if ( ! $has_content ) {
		return '';
	}

	if ( null !== $label_id && 'NAV' === $tags->get_tag() && null === $tags->get_attribute( 'aria-labelledby' ) ) {
		$tags->set_attribute( 'aria-labelledby', $label_id );
		return $tags->get_updated_html();
	}
	return $content;
}
add_filter( 'render_block_core/group', 'ishot_theme_footer_group' );
