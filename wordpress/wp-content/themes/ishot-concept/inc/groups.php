<?php
/**
 * The one `render_block_core/group` filter: the server-side touches core Group blocks need to print
 * the prototype's markup where Group has no attribute for it. HTML API only; any other group, or
 * markup the HTML API cannot read, is returned unchanged.
 *
 * @package IShot\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * - A group whose only content is the `ishot/notice` block (possibly through nested groups) is not
 *   printed when the notice prints nothing, so the page template has no empty padded section.
 * - The templates' `main#main` gets `tabindex="-1"` (src/App.jsx: `<main id="main" tabIndex={-1}>`),
 *   so the skip link moves focus to it. A tabindex already set is kept.
 * - Footer columns (`.fo-footer__col`) and the social wrapper (`.ish-social`): a column whose only
 *   child is its `.fo-eyebrow`, or a social wrapper with no child, is not printed (no empty wrapper
 *   or landmark when a plugin block prints nothing); a `nav.fo-footer__col` is labelled by its
 *   eyebrow (`aria-labelledby` = the eyebrow's id). A value already set is kept.
 *
 * @param string               $content Rendered group markup.
 * @param array<string, mixed> $block   Parsed block (its inner blocks), when called as the filter.
 */
function ishot_theme_render_group( string $content, array $block = array() ): string {
	if ( ishot_theme_holds_only_notice( $block ) && '' === trim( wp_strip_all_tags( $content ) ) ) {
		return '';
	}
	$tags = new WP_HTML_Tag_Processor( $content );
	if ( ! $tags->next_tag() ) {
		return $content;
	}
	if ( 'MAIN' === $tags->get_tag() ) {
		if ( 'main' !== $tags->get_attribute( 'id' ) || null !== $tags->get_attribute( 'tabindex' ) ) {
			return $content;
		}
		$tags->set_attribute( 'tabindex', '-1' );
		return $tags->get_updated_html();
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
add_filter( 'render_block_core/group', 'ishot_theme_render_group', 10, 2 );

/**
 * Whether a parsed Group block holds nothing but one `ishot/notice`, directly or through nested
 * single-child Groups (the page template's notice section).
 *
 * @param array<string, mixed> $block Parsed block.
 */
function ishot_theme_holds_only_notice( array $block ): bool {
	$inner = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
	if ( 1 !== count( $inner ) || ! is_array( $inner[0] ) ) {
		return false;
	}
	$child = $inner[0];
	if ( 'ishot/notice' === ( $child['blockName'] ?? null ) ) {
		return true;
	}
	return 'core/group' === ( $child['blockName'] ?? null ) && ishot_theme_holds_only_notice( $child );
}
