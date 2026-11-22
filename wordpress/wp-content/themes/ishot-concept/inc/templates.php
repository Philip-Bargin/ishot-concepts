<?php
/**
 * Template support: the document title separator and the `page-title` pattern's optional paragraphs.
 *
 * @package IShot\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * The prototype's titles are "{Title} · iShot Concept" (src/lib/hooks.js usePageTitle), so the
 * separator is a middle dot (ruling R-1.4-3).
 */
function ishot_theme_document_title_separator(): string {
	return '·';
}
add_filter( 'document_title_separator', 'ishot_theme_document_title_separator' );

/**
 * `render_block_core/paragraph` filter: a title block's eyebrow (`.fo-eyebrow`) or lead
 * (`.lead.fo-muted.ish-measure`) paragraph with no text is not printed. The `page` template uses the
 * page-title pattern, whose eyebrow and lead the owner fills per page; left empty, they would leave
 * an empty grid item in the `.ish-stack` (PageTitle prints no lead without one). Any other paragraph
 * is returned unchanged.
 *
 * @param string               $content Rendered paragraph.
 * @param array<string, mixed> $block   Parsed block.
 */
function ishot_theme_render_title_paragraph( string $content, array $block ): string {
	$class = preg_split( '/\s+/', (string) ( $block['attrs']['className'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
	$class = is_array( $class ) ? $class : array();
	$title = in_array( 'fo-eyebrow', $class, true ) || array() === array_diff( array( 'lead', 'fo-muted', 'ish-measure' ), $class );
	if ( ! $title || '' !== trim( wp_strip_all_tags( $content ) ) ) {
		return $content;
	}
	return '';
}
add_filter( 'render_block_core/paragraph', 'ishot_theme_render_title_paragraph', 10, 2 );
