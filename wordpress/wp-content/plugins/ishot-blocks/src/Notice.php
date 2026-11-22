<?php
namespace IShot\Blocks;

/** The visible "sample content" notice the prototype prints on placeholder data (PrototypeNotice). */
final class Notice {

	/**
	 * Notice markup, same classes and structure as the prototype's PrototypeNotice.
	 *
	 * @param string|null $label       Bold lead-in (the prototype's `label`); null = "Sample content." (translatable).
	 * @param string      $message     Text after the label (the prototype's children). Plain text, escaped.
	 * @param string      $extra_class Extra class(es) on the wrapper (the prototype's `className`, e.g. ish-notice).
	 */
	public static function render( ?string $label = null, string $message = '', string $extra_class = '' ): string {
		$label   = $label ?? __( 'Sample content.', 'ishot-blocks' );
		$classes = 'fo-alert ish-alert--info ish-measure' . ( '' !== trim( $extra_class ) ? ' ' . trim( $extra_class ) : '' );
		return '<div class="' . esc_attr( $classes ) . '">' . Icons::svg( 'info' ) . '<p><strong>' . esc_html( $label ) . '</strong> ' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * The notice for a post whose content is still placeholder, '' for anything else. The status is
	 * owned by ishot-booking; without it nothing is flagged.
	 */
	public static function forPost( int $post_id, string $message = '', string $extra_class = '' ): string {
		if ( $post_id <= 0 || ! class_exists( 'IShot\Booking\Content\Fields' ) ) {
			return '';
		}
		if ( \IShot\Booking\Content\Fields::PLACEHOLDER !== get_post_meta( $post_id, 'content_status', true ) ) {
			return '';
		}
		return self::render( null, $message, $extra_class );
	}
}
