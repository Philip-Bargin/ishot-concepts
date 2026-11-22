<?php
/**
 * The header template part and the prototype's skip link (src/App.jsx at 50e60f4).
 *
 * @package IShot\Theme
 */

/** One skip link, first in <body>; the header part holds the site header without a second landmark. */
final class HeaderPartTest extends WP_UnitTestCase {

	public function tear_down(): void {
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		parent::tear_down();
	}

	private static function capture( callable $fn ): string {
		ob_start();
		$fn();
		return (string) ob_get_clean();
	}

	public function test_skip_link_is_the_prototypes_and_printed_first_on_wp_body_open(): void {
		$body = self::capture( static fn() => do_action( 'wp_body_open' ) );

		$this->assertStringStartsWith( '<a href="#main" class="ish-skip">Skip to content</a>', ltrim( $body ) );
		$this->assertSame( 1, substr_count( $body, 'ish-skip' ) );
		$this->assertSame( 0, has_action( 'wp_body_open', 'ishot_theme_print_skip_link' ) );
	}

	public function test_core_block_template_skip_link_is_removed(): void {
		$this->assertFalse( has_action( 'wp_enqueue_scripts', 'wp_enqueue_block_template_skip_link' ) );
		$this->assertFalse( has_action( 'wp_footer', 'the_block_template_skip_link' ) );

		$page = self::capture(
			static function () {
				do_action( 'wp_enqueue_scripts' );
				do_action( 'wp_body_open' );
				do_action( 'wp_footer' );
			}
		);
		$this->assertStringNotContainsString( 'skip-link', $page );
		$this->assertSame( 1, substr_count( $page, 'href="#main"' ) );
	}

	public function test_header_part_file_holds_the_site_header_block(): void {
		$file = get_theme_file_path( 'parts/header.html' );
		$this->assertFileExists( $file );

		$blocks = array_values( array_filter( parse_blocks( (string) file_get_contents( $file ) ), static fn( $b ) => null !== $b['blockName'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		$this->assertCount( 1, $blocks );
		$this->assertSame( 'ishot/site-header', $blocks[0]['blockName'] );
	}

	public function test_header_part_is_declared_in_the_header_area(): void {
		$parts = wp_get_theme_data_template_parts();
		$this->assertArrayHasKey( 'header', $parts );
		$this->assertSame( WP_TEMPLATE_PART_AREA_HEADER, $parts['header']['area'] );
	}

	public function test_header_part_renders_one_header_landmark_in_a_div_wrapper(): void {
		$html = do_blocks( '<!-- wp:template-part {"slug":"header","theme":"ishot-concept","tagName":"div"} /-->' );

		$this->assertMatchesRegularExpression( '#<header class="fo-header"[ >]#', $html );
		$this->assertSame( 1, substr_count( $html, '<header' ) );
		$this->assertMatchesRegularExpression( '#^<div class="wp-block-template-part">#', trim( $html ) );
	}

	public function test_templates_reference_the_header_part_with_a_div_wrapper(): void {
		foreach ( (array) glob( get_theme_file_path( 'templates/*.html' ) ) as $file ) {
			$this->assertStringContainsString( '<!-- wp:template-part {"slug":"header","tagName":"div","area":"header"} /-->', (string) file_get_contents( $file ), basename( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		}
	}

	public function test_templates_wrap_the_frame_in_the_prototypes_fo_root_ish_app_wrapper(): void {
		// .fo-root carries the design system's border-box sizing and reduced-motion rules; without it
		// the header's icon buttons and actions are taller and wider than the prototype's.
		foreach ( (array) glob( get_theme_file_path( 'templates/*.html' ) ) as $file ) {
			$html = do_blocks( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
			$this->assertMatchesRegularExpression( '#^<div class="wp-block-group fo-root ish-app[ "]#', trim( $html ), basename( $file ) );
			$this->assertLessThan( strpos( $html, '<main' ), strpos( $html, '<header class="fo-header"' ), basename( $file ) );
		}
	}

	public function test_theme_css_neutralises_the_part_wrapper_and_hides_the_closed_sheet(): void {
		$css = (string) file_get_contents( get_theme_file_path( 'assets/css/theme.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.

		$this->assertMatchesRegularExpression( '#\.wp-block-template-part\s*\{\s*display:\s*contents;\s*\}#', $css );
		$this->assertMatchesRegularExpression( '#\.fo-menu\[hidden\]\s*\{\s*display:\s*none;\s*\}#', $css );
	}
}
