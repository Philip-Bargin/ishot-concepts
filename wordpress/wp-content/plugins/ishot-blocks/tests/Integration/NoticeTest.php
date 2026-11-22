<?php
namespace IShot\Blocks\Tests\Integration;

use IShot\Blocks\Icons;
use IShot\Blocks\Notice;
use IShot\Blocks\Tests\Support\MarkupNormaliser;
use WP_UnitTestCase;

final class NoticeTest extends WP_UnitTestCase {

	public function test_render_matches_the_prototype_notice(): void {
		// Source: PrototypeNotice in src/components/ui.jsx with the Info icon from Icon.jsx. The
		// prototype passes the message as children; here only the label is printed.
		$expected = '<div class="fo-alert ish-alert--info ish-measure">' . Icons::svg( 'info' ) . '<p><strong>Sample content.</strong> </p></div>';
		$this->assertSame( MarkupNormaliser::normalise( $expected ), MarkupNormaliser::normalise( Notice::render() ) );
	}

	public function test_render_with_a_message_matches_the_prototype_notice(): void {
		// Source: <PrototypeNotice label="Sample content.">Prices are examples.</PrototypeNotice>
		$expected = '<div class="fo-alert ish-alert--info ish-measure">' . Icons::svg( 'info' ) . '<p><strong>Sample content.</strong> Prices are examples.</p></div>';
		$this->assertSame( MarkupNormaliser::normalise( $expected ), MarkupNormaliser::normalise( Notice::render( 'Sample content.', 'Prices are examples.' ) ) );
		$this->assertStringContainsString( '</strong> Prices are examples.</p>', Notice::render( 'Sample content.', 'Prices are examples.' ) );
	}

	public function test_render_with_a_class_matches_the_prototype_notice(): void {
		// Source: <PrototypeNotice className="ish-notice"> (Testimonials).
		$expected = '<div class="fo-alert ish-alert--info ish-measure ish-notice">' . Icons::svg( 'info' ) . '<p><strong>Sample content.</strong> Hello</p></div>';
		$this->assertSame( MarkupNormaliser::normalise( $expected ), MarkupNormaliser::normalise( Notice::render( 'Sample content.', 'Hello', 'ish-notice' ) ) );
	}

	public function test_default_label_is_translatable(): void {
		$translate = static fn( $translation, $text, $domain ) => 'ishot-blocks' === $domain && 'Sample content.' === $text ? 'Exemple.' : $translation;
		add_filter( 'gettext', $translate, 10, 3 );
		try {
			$this->assertStringContainsString( '<strong>Exemple.</strong>', Notice::render() );
		} finally {
			remove_filter( 'gettext', $translate, 10 );
		}
	}

	public function test_message_and_class_are_escaped(): void {
		$html = Notice::render( 'L', '<b>x</b>', '"><script>' );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_for_post_passes_message_and_class_through(): void {
		$id = self::factory()->post->create();
		update_post_meta( $id, 'content_status', \IShot\Booking\Content\Fields::PLACEHOLDER );
		$html = Notice::forPost( $id, 'Draft copy.', 'ish-notice' );
		$this->assertStringContainsString( 'ish-notice', $html );
		$this->assertStringContainsString( '</strong> Draft copy.</p>', $html );
	}

	public function test_render_escapes_the_label(): void {
		$html = Notice::render( '<script>x</script>' );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_for_post_shows_only_for_placeholder_content(): void {
		$id = self::factory()->post->create();
		$this->assertSame( '', Notice::forPost( $id ) );

		update_post_meta( $id, 'content_status', 'approved' );
		$this->assertSame( '', Notice::forPost( $id ) );

		update_post_meta( $id, 'content_status', \IShot\Booking\Content\Fields::PLACEHOLDER );
		$this->assertStringContainsString( 'fo-alert ish-alert--info', Notice::forPost( $id ) );
	}

	public function test_for_post_is_empty_for_a_missing_post(): void {
		$this->assertSame( '', Notice::forPost( 0 ) );
		$this->assertSame( '', Notice::forPost( 999999 ) );
	}
}
