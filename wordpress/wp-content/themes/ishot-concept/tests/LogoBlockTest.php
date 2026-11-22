<?php
/**
 * The `ishot/logo` block: the prototype's Logo (src/components/Logo.jsx at 50e60f4).
 *
 * @package IShot\Theme
 */

use IShot\Blocks\Tests\Support\MarkupNormaliser;

/** Official logo files only, with the brand name as the alternative text. */
final class LogoBlockTest extends WP_UnitTestCase {

	private const FULL     = 'assets/brand/ishot-concept-full-colour-trimmed.png';
	private const REVERSED = 'assets/brand/ishot-concept-reversed-white-trimmed.png';

	public function test_block_is_registered_from_the_build(): void {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'ishot/logo' ) );
	}

	public function test_auto_prints_the_full_colour_and_reversed_files(): void {
		$html = trim( do_blocks( '<!-- wp:ishot/logo /-->' ) );

		$expected = '<img class="ish-logo__full" src="x" alt="iShot Concept"><img class="ish-logo__rev" src="x" alt="iShot Concept">';
		$this->assertSame( MarkupNormaliser::normalise( $expected ), MarkupNormaliser::normalise( $html ) );
		$this->assertSame(
			'<img class="ish-logo__full" src="' . esc_url( get_theme_file_uri( self::FULL ) ) . '" alt="iShot Concept"><img class="ish-logo__rev" src="' . esc_url( get_theme_file_uri( self::REVERSED ) ) . '" alt="iShot Concept">',
			$html
		);
	}

	public function test_reversed_prints_only_the_white_file(): void {
		$html = trim( do_blocks( '<!-- wp:ishot/logo {"variant":"reversed"} /-->' ) );

		$this->assertSame( '<img src="' . esc_url( get_theme_file_uri( self::REVERSED ) ) . '" alt="iShot Concept">', $html );
	}

	public function test_is_link_wraps_the_images_in_the_fo_logo_home_link(): void {
		$html = trim( do_blocks( '<!-- wp:ishot/logo {"isLink":true} /-->' ) );

		$this->assertSame( '<a class="fo-logo" href="' . esc_url( home_url( '/' ) ) . '">' . ishot_theme_logo_html() . '</a>', $html );
		$this->assertStringNotContainsString( '<a', trim( do_blocks( '<!-- wp:ishot/logo /-->' ) ), 'no link by default' );
	}

	public function test_an_unknown_variant_falls_back_to_auto(): void {
		$this->assertSame( ishot_theme_logo_html( 'auto' ), ishot_theme_logo_html( 'neon' ) );
	}

	public function test_the_files_exist_in_the_theme(): void {
		$this->assertFileExists( get_theme_file_path( self::FULL ) );
		$this->assertFileExists( get_theme_file_path( self::REVERSED ) );
	}
}
