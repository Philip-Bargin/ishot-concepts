<?php
namespace IShot\Blocks\Tests\Support;

use WP_Block_Type_Registry;
use WP_HTML_Tag_Processor;

/**
 * Checks a theme file of block markup (template, part, pattern content) the way the editor would:
 * it round-trips through parse_blocks()/serialize_blocks(), every block is registered, no stray
 * HTML sits between blocks, and every static block's saved opening tag equals what core's save()
 * prints for its attributes (WP 6.7), so the editor reports no invalid block.
 */
trait ValidBlockMarkup {

	protected function assertValidBlockMarkup( string $source, string $label ): void {
		$blocks = parse_blocks( $source );
		$this->assertSame( trim( $source ), trim( serialize_blocks( $blocks ) ), $label . ': serialize_blocks( parse_blocks() ) gives the file back' );

		$walk = function ( array $list ) use ( &$walk, $label ): void {
			foreach ( $list as $block ) {
				if ( null === $block['blockName'] ) {
					$this->assertSame( '', trim( $block['innerHTML'] ), $label . ': no stray HTML between blocks' );
					continue;
				}
				$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( $block['blockName'] ), $label . ': ' . $block['blockName'] . ' is registered' );
				$this->assertSame( self::expectedSave( $block ), self::savedWrapper( $block ), $label . ': ' . $block['blockName'] . ' saved markup equals its save() output' );
				$walk( $block['innerBlocks'] );
			}
		};
		$walk( $blocks );
	}

	/** The opening tag of the block's saved markup, reduced to tag, sorted classes and id. */
	private static function savedWrapper( array $block ): string {
		$p = new WP_HTML_Tag_Processor( $block['innerHTML'] );
		if ( ! $p->next_tag() ) {
			return '';
		}
		$classes = iterator_to_array( $p->class_list() );
		sort( $classes );
		return strtolower( $p->get_tag() ) . '|' . implode( ' ', $classes ) . '|' . (string) $p->get_attribute( 'id' );
	}

	/**
	 * What core's save() prints as the block's opening tag for its attributes (6.7): Group is
	 * `<{tagName} class="wp-block-group {className}" id="{anchor}">`, Paragraph is
	 * `<p class="{className}" id="{anchor}">`, Heading is `<h{level} class="wp-block-heading {className}">`.
	 * Dynamic and HTML blocks save no wrapper of their own.
	 */
	private static function expectedSave( array $block ): string {
		$attrs = $block['attrs'];
		$class = isset( $attrs['className'] ) ? preg_split( '/\s+/', $attrs['className'], -1, PREG_SPLIT_NO_EMPTY ) : array();
		switch ( $block['blockName'] ) {
			case 'core/group':
				$class[] = 'wp-block-group';
				sort( $class );
				return ( $attrs['tagName'] ?? 'div' ) . '|' . implode( ' ', $class ) . '|' . ( $attrs['anchor'] ?? '' );
			case 'core/paragraph':
				sort( $class );
				return 'p|' . implode( ' ', $class ) . '|' . ( $attrs['anchor'] ?? '' );
			case 'core/heading':
				$class[] = 'wp-block-heading';
				sort( $class );
				return 'h' . ( $attrs['level'] ?? 2 ) . '|' . implode( ' ', $class ) . '|' . ( $attrs['anchor'] ?? '' );
			default:
				return self::savedWrapper( $block );
		}
	}
}
