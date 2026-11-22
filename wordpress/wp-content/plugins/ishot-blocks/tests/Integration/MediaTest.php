<?php
namespace IShot\Blocks\Tests\Integration;

use IShot\Blocks\Icons;
use IShot\Blocks\Media;
use IShot\Blocks\Tests\Support\MarkupNormaliser;
use WP_UnitTestCase;

final class MediaTest extends WP_UnitTestCase {

	private int $attachment = 0;

	public function set_up(): void {
		parent::set_up();
		$this->attachment = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/33772.jpg' );
		update_post_meta( $this->attachment, '_wp_attachment_image_alt', 'A bride laughing' );
		update_post_meta( $this->attachment, 'focus', '30% 20%' );
	}

	private function norm( string $html ): string {
		return MarkupNormaliser::normalise( $html );
	}

	public function test_photo_prints_srcset_sizes_and_the_ratio_class(): void {
		$html = Media::photo( $this->attachment, 'landscape', '(min-width: 768px) 50vw, 100vw' );
		$this->assertStringContainsString( 'srcset="', $html );
		$this->assertMatchesRegularExpression( '/sizes="(auto, )?\(min-width: 768px\) 50vw, 100vw"/', $html );
		$this->assertStringContainsString( 'class="fo-img ish-r-landscape"', $html );
	}

	public function test_photo_takes_object_position_from_the_focus_meta(): void {
		$this->assertStringContainsString( 'object-position:30% 20%', Media::photo( $this->attachment ) );
	}

	public function test_photo_omits_object_position_when_focus_is_missing_or_malformed(): void {
		update_post_meta( $this->attachment, 'focus', 'url(javascript:x)' );
		$this->assertStringNotContainsString( 'object-position', Media::photo( $this->attachment ) );
		delete_post_meta( $this->attachment, 'focus' );
		$this->assertStringNotContainsString( 'object-position', Media::photo( $this->attachment ) );
	}

	public function test_priority_photo_is_eager_with_high_fetchpriority(): void {
		$html = Media::photo( $this->attachment, 'portrait', '100vw', true );
		$this->assertStringContainsString( 'loading="eager"', $html );
		$this->assertStringContainsString( 'fetchpriority="high"', $html );
		$this->assertStringContainsString( 'decoding="async"', $html );
	}

	public function test_normal_photo_is_lazy_without_fetchpriority(): void {
		$html = Media::photo( $this->attachment );
		$this->assertStringContainsString( 'loading="lazy"', $html );
		$this->assertStringNotContainsString( 'fetchpriority', $html );
		$this->assertStringContainsString( 'decoding="async"', $html );
	}

	public function test_alt_defaults_to_the_attachment_alt_and_empty_means_decorative(): void {
		$this->assertStringContainsString( 'alt="A bride laughing"', Media::photo( $this->attachment ) );
		$this->assertStringContainsString( 'alt=""', Media::photo( $this->attachment, 'blog', '100vw', false, '' ) );
		$this->assertStringContainsString( 'alt="Custom"', Media::photo( $this->attachment, 'blog', '100vw', false, 'Custom' ) );
	}

	public function test_a_missing_or_zero_id_gives_the_placeholder_frame(): void {
		$this->assertSame( Media::placeholder( 'portrait' ), Media::photo( 0 ) );
		$this->assertSame( Media::placeholder( 'wide' ), Media::photo( 999999, 'wide' ) );
		$post = self::factory()->post->create();
		$this->assertSame( Media::placeholder( 'square' ), Media::photo( $post, 'square' ), 'a non-image post is not a photo' );
	}

	public function test_an_unknown_ratio_falls_back_to_portrait(): void {
		$this->assertStringContainsString( 'ish-r-portrait', Media::photo( $this->attachment, 'bogus' ) );
		$this->assertStringContainsString( 'ish-r-portrait', Media::placeholder( 'bogus' ) );
	}

	public function test_photo_matches_the_prototype_photo(): void {
		// Source: Photo in src/components/ui.jsx (alt from the photo).
		$expected = '<span class="fo-img ish-r-landscape"><img src="x" srcset="x" sizes="x" alt="A bride laughing" loading="lazy" decoding="async" style="object-position:30% 20%"></span>';
		$this->assertSame( $this->norm( $expected ), $this->norm( Media::photo( $this->attachment, 'landscape', '100vw' ) ) );

		$expected = '<span class="fo-img ish-r-portrait"><img src="x" srcset="x" sizes="x" alt="A bride laughing" loading="eager" fetchpriority="high" decoding="async" style="object-position:30% 20%"></span>';
		$this->assertSame( $this->norm( $expected ), $this->norm( Media::photo( $this->attachment, 'portrait', '100vw', true ) ) );
	}

	public function test_page_output_filter_keeps_the_class_list_clean_and_adds_auto_to_sizes(): void {
		// Block-theme output runs wp_filter_content_tags( $html, 'template' ), which core uses to
		// prefix "auto, " to the sizes of lazy images. That is accepted; the class list must stay clean.
		$html = wp_filter_content_tags( Media::photo( $this->attachment, 'landscape', '(min-width: 768px) 50vw, 100vw' ), 'template' );
		$this->assertStringContainsString( 'sizes="auto, (min-width: 768px) 50vw, 100vw"', $html );
		$this->assertStringNotContainsString( 'wp-image-', $html );
		$this->assertStringNotContainsString( 'attachment-large', $html );
		$this->assertStringContainsString( 'class="fo-img ish-r-landscape"', $html );
		$this->assertStringContainsString( 'loading="lazy"', $html );
	}

	public function test_a_throwing_filter_leaves_no_ishot_filter_attached(): void {
		global $wp_filter;
		$before  = count( $wp_filter['wp_get_attachment_image_attributes']->callbacks[10] ?? array() );
		$thrower = static function () {
			throw new \RuntimeException( 'boom' );
		};
		add_filter( 'wp_get_attachment_image_attributes', $thrower, 20 );
		try {
			Media::photo( $this->attachment );
			$this->fail( 'expected the exception to propagate' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'boom', $e->getMessage() );
		}
		remove_filter( 'wp_get_attachment_image_attributes', $thrower, 20 );
		$after = count( $wp_filter['wp_get_attachment_image_attributes']->callbacks[10] ?? array() );
		$this->assertSame( $before, $after );
		$this->assertStringContainsString( 'attachment-large', wp_get_attachment_image( $this->attachment, 'large' ) );
	}

	public function test_a_nested_call_for_another_attachment_keeps_core_classes(): void {
		$other  = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$nested = '';
		$spy    = null;
		$spy    = static function ( array $attr, $attachment ) use ( $other, &$nested, &$spy ) {
			if ( $attachment instanceof \WP_Post && $attachment->ID !== $other ) {
				remove_filter( 'wp_get_attachment_image_attributes', $spy, 11 );
				$nested = wp_get_attachment_image( $other, 'large' );
			}
			return $attr;
		};
		add_filter( 'wp_get_attachment_image_attributes', $spy, 11, 2 );
		$outer = Media::photo( $this->attachment );
		$this->assertStringNotContainsString( 'attachment-large', $outer );
		$this->assertStringContainsString( 'attachment-large', $nested );
	}

	/** @dataProvider placeholder_labels */
	public function test_placeholder_matches_the_prototype( string $ratio, string $label ): void {
		// Source: Placeholder + RATIO_LABEL in src/components/ui.jsx; the video and blog labels come
		// from src/index.css (--ratio-hero 16 / 9, --ish-ratio-blog 3 / 2).
		$expected = '<span class="fo-img fo-img--ph ish-r-' . $ratio . '" data-ratio="' . $label . '" aria-hidden="true"></span>';
		$this->assertSame( $this->norm( $expected ), $this->norm( Media::placeholder( $ratio ) ) );
	}

	/** @return array<string, array{string, string}> */
	public function placeholder_labels(): array {
		return array(
			'portrait'  => array( 'portrait', '4:5' ),
			'landscape' => array( 'landscape', '4:3' ),
			'square'    => array( 'square', '1:1' ),
			'tall'      => array( 'tall', '2:3' ),
			'strip'     => array( 'strip', '1:2' ),
			'wide'      => array( 'wide', '21:9' ),
			'video'     => array( 'video', '16:9' ),
			'blog'      => array( 'blog', '3:2' ),
		);
	}

	public function test_every_booking_service_icon_renders_an_svg(): void {
		$this->assertNotEmpty( \IShot\Booking\Content\Fields::ICONS );
		foreach ( \IShot\Booking\Content\Fields::ICONS as $name ) {
			$this->assertStringStartsWith( '<svg', Icons::svg( $name ), "ishot-booking allows the icon '{$name}' but ishot-blocks cannot draw it" );
		}
	}
}
