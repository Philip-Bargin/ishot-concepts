<?php
/**
 * Frame-only blocks are not offered in the inserter (Stage 1 final review I1/D1): inserted alone they
 * break the page (duplicate banner and #site-menu, unsized logo, a second out-of-sync theme toggle).
 * Template parts still render and edit them.
 *
 * @package IShot\Theme
 */

/** The block.json supports flag, and the parts that must keep rendering the blocks. */
final class InserterTest extends WP_UnitTestCase {

	/** @return array<string, array{0: string}> */
	public static function frameBlocks(): array {
		return array(
			'logo'            => array( 'ishot/logo' ),
			'site-header'     => array( 'ishot/site-header' ),
			'contact-details' => array( 'ishot/contact-details' ),
			'social-links'    => array( 'ishot/social-links' ),
			'theme-toggle'    => array( 'ishot/theme-toggle' ),
			'spectrum'        => array( 'ishot/spectrum' ),
		);
	}

	/** @dataProvider frameBlocks */
	public function test_frame_block_is_not_offered_in_the_inserter( string $name ): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
		$this->assertNotNull( $type, "$name is registered" );
		$this->assertFalse( $type->supports['inserter'] ?? true, "$name must set supports.inserter to false" );
	}

	public function test_spectrum_has_no_class_field_and_hides_only_the_inner_span_in_the_editor(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'ishot/spectrum' );
		$this->assertFalse( $type->supports['className'] ?? true );
		$this->assertFalse( $type->supports['customClassName'] ?? true );

		// The focusable block wrapper must not carry aria-hidden; only the inner decorative span does.
		$src = (string) file_get_contents( get_theme_file_path( 'blocks/spectrum/index.js' ) );
		$this->assertStringNotContainsString( "'aria-hidden': 'true' } )", $src, 'no aria-hidden in the useBlockProps call' );
		$this->assertMatchesRegularExpression( '#<span aria-hidden="true">\s*<i />#', $src );
	}

	public function test_ishot_notice_stays_in_the_inserter(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'ishot/notice' );
		$this->assertNotFalse( $type->supports['inserter'] ?? true );
	}

	public function test_template_parts_still_render_the_frame_blocks(): void {
		$header = do_blocks( '<!-- wp:template-part {"slug":"header","theme":"ishot-concept","tagName":"div"} /-->' );
		$this->assertStringContainsString( 'class="fo-header"', $header );
		$this->assertStringContainsString( 'id="site-menu"', $header );

		$footer = do_blocks( '<!-- wp:template-part {"slug":"footer","theme":"ishot-concept","tagName":"div"} /-->' );
		foreach ( array( 'ish-logo__full', 'fo-spectrum', 'ish-linkbtn', 'ish-social' ) as $needle ) {
			$this->assertStringContainsString( $needle, $footer, "footer part still renders $needle" );
		}
	}
}
