<?php
namespace IShot\Blocks;

/**
 * Photo and placeholder frames with the same markup as the prototype's Photo and Placeholder.
 * Image URLs, srcset and alt come from WordPress core; the focus point comes from the
 * `focus` attachment meta that ishot-booking registers.
 */
final class Media {

	/** Ratio => label printed on placeholders. video/blog labels follow src/index.css. */
	private const RATIO_LABEL = array(
		'portrait'  => '4:5',
		'landscape' => '4:3',
		'square'    => '1:1',
		'tall'      => '2:3',
		'strip'     => '1:2',
		'wide'      => '21:9',
		'video'     => '16:9',
		'blog'      => '3:2',
	);

	/** Image size whose file backs `src`; core builds the srcset from the attachment's other sizes. */
	private const SIZE = 'large';

	/**
	 * A responsive photo in a fixed-ratio frame; a placeholder frame when the attachment is not an image.
	 *
	 * @param int         $attachment_id Attachment (image) ID.
	 * @param string      $ratio         portrait|landscape|square|tall|wide|strip|video|blog.
	 * @param string      $sizes         The img sizes attribute. WordPress (6.7+) prefixes `auto, ` to it on lazy images, both here
	 *                                   and again when the page output passes through wp_filter_content_tags(); the browser
	 *                                   still sizes by the rest of the list, and layout is fixed by the .fo-img ratio frame.
	 * @param bool        $priority      True for the above-the-fold image: eager, high fetch priority.
	 * @param string|null $alt           null = the attachment's own alt text; '' = decorative.
	 */
	public static function photo( int $attachment_id, string $ratio = 'portrait', string $sizes = '100vw', bool $priority = false, ?string $alt = null ): string {
		$ratio = self::ratio( $ratio );
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return self::placeholder( $ratio );
		}

		$attr = array(
			'sizes'    => $sizes,
			'loading'  => $priority ? 'eager' : 'lazy',
			'decoding' => 'async',
		);
		if ( $priority ) {
			$attr['fetchpriority'] = 'high';
		}
		if ( null !== $alt ) {
			$attr['alt'] = $alt;
		}
		$focus = get_post_meta( $attachment_id, 'focus', true );
		if ( is_string( $focus ) && 1 === preg_match( '/^\d{1,3}(\.\d+)?% \d{1,3}(\.\d+)?%$/', $focus ) ) {
			$attr['style'] = 'object-position:' . $focus;
		}

		// Core adds a class list (attachment-large size-large wp-image-N) that the prototype's img
		// does not have. Drop it for this attachment only, and always unhook, even on an exception.
		$drop_class = static function ( array $attributes, $attachment ) use ( $attachment_id ): array {
			if ( $attachment instanceof \WP_Post && $attachment->ID === $attachment_id ) {
				unset( $attributes['class'] );
			}
			return $attributes;
		};
		add_filter( 'wp_get_attachment_image_attributes', $drop_class, 10, 2 );
		try {
			$img = wp_get_attachment_image( $attachment_id, self::SIZE, false, $attr );
		} finally {
			remove_filter( 'wp_get_attachment_image_attributes', $drop_class, 10 );
		}
		if ( '' === $img ) {
			return self::placeholder( $ratio );
		}
		return '<span class="fo-img ish-r-' . $ratio . '">' . $img . '</span>';
	}

	/** The design system's placeholder slot, shown where real photography is still to come. */
	public static function placeholder( string $ratio ): string {
		$ratio = self::ratio( $ratio );
		return '<span class="fo-img fo-img--ph ish-r-' . $ratio . '" data-ratio="' . self::RATIO_LABEL[ $ratio ] . '" aria-hidden="true"></span>';
	}

	private static function ratio( string $ratio ): string {
		return isset( self::RATIO_LABEL[ $ratio ] ) ? $ratio : 'portrait';
	}
}
