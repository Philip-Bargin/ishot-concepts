<?php
namespace IShot\Blocks;

/** The visible "sample content" notice the prototype prints on placeholder data (PrototypeNotice). */
final class Notice {

	/** Notice markup, same classes and structure as the prototype's PrototypeNotice. */
	public static function render( string $label = 'Sample content.' ): string {
		return '<div class="fo-alert ish-alert--info ish-measure">' . Icons::svg( 'info' ) . '<p><strong>' . esc_html( $label ) . '</strong> </p></div>';
	}

	/**
	 * The notice for a post whose content is still placeholder, '' for anything else. The status is
	 * owned by ishot-booking; without it nothing is flagged.
	 */
	public static function forPost( int $post_id ): string {
		if ( $post_id <= 0 || ! class_exists( 'IShot\Booking\Content\Fields' ) ) {
			return '';
		}
		if ( \IShot\Booking\Content\Fields::PLACEHOLDER !== get_post_meta( $post_id, 'content_status', true ) ) {
			return '';
		}
		return self::render( __( 'Sample content.', 'ishot-blocks' ) );
	}
}
