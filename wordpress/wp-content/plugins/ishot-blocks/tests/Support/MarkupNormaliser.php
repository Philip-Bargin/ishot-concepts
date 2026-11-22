<?php
namespace IShot\Blocks\Tests\Support;

/**
 * Reduces markup to what the design depends on, so PHP output can be compared with the React
 * prototype's output (controller ruling R4): tag names, sorted class lists, role, aria-*, loading,
 * decoding, fetchpriority, data-ratio and the object-position style. src, srcset, sizes, width,
 * height and other values are ignored. SVG is kept whole, since icons must match exactly.
 */
final class MarkupNormaliser {

	private const KEEP = array( 'role', 'loading', 'decoding', 'fetchpriority', 'data-ratio', 'focusable' );

	public static function normalise( string $html ): string {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();
		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		$out  = '';
		if ( $body instanceof \DOMNode ) {
			foreach ( $body->childNodes as $child ) {
				$out .= self::node( $child );
			}
		}
		return $out;
	}

	private static function node( \DOMNode $node ): string {
		if ( $node instanceof \DOMText ) {
			return preg_replace( '/\s+/', ' ', $node->nodeValue ?? '' ) ?? '';
		}
		if ( ! $node instanceof \DOMElement ) {
			return '';
		}
		$tag = strtolower( $node->tagName );
		if ( 'svg' === $tag ) {
			return $node->ownerDocument ? (string) $node->ownerDocument->saveHTML( $node ) : '';
		}
		$attrs = array();
		$class = preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
		if ( is_array( $class ) && array() !== $class ) {
			sort( $class );
			$attrs[] = 'class="' . implode( ' ', $class ) . '"';
		}
		foreach ( $node->attributes ?? array() as $attr ) {
			$name = $attr->name;
			if ( in_array( $name, self::KEEP, true ) || 0 === strpos( $name, 'aria-' ) ) {
				$attrs[] = $name . '="' . $attr->value . '"';
			}
		}
		if ( 'img' === $tag ) {
			$attrs[] = 'alt="' . $node->getAttribute( 'alt' ) . '"';
		}
		if ( 1 === preg_match( '/object-position:\s*([^;]+)/', $node->getAttribute( 'style' ), $m ) ) {
			$attrs[] = 'object-position="' . trim( $m[1] ) . '"';
		}
		sort( $attrs );
		$inner = '';
		foreach ( $node->childNodes as $child ) {
			$inner .= self::node( $child );
		}
		return '<' . $tag . ( array() === $attrs ? '' : ' ' . implode( ' ', $attrs ) ) . '>' . $inner . '</' . $tag . '>';
	}
}
