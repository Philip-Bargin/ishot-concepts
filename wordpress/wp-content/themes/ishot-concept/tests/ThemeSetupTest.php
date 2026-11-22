<?php
/**
 * Theme setup tests. They run from the ishot-blocks PHPUnit config (`theme` suite), so that
 * ishot-booking and ishot-blocks are loaded and the theme is active, as on the real site.
 *
 * @package IShot\Theme
 */

/** The theme is active in the test environment and loads the design system in order. */
final class ThemeSetupTest extends WP_UnitTestCase {

	public function test_theme_active_in_test_env(): void {
		$this->assertSame( 'ishot-concept', get_stylesheet() );
		$this->assertSame( 'ishot-concept', get_template() );
		$this->assertTrue( wp_get_theme()->exists() );
		$this->assertTrue( wp_is_block_theme() );
	}

	public function test_design_system_styles_enqueued_in_order(): void {
		do_action( 'wp_enqueue_scripts' );

		$styles = wp_styles();
		$styles->all_deps( array( 'ishot-theme' ) );

		// Tokens first, then the design-system bundle, then the theme overrides.
		$this->assertSame( array( 'ishot-tokens', 'ishot-bundle', 'ishot-theme' ), $styles->to_do );
		$this->assertSame( array(), $styles->registered['ishot-tokens']->deps );
		$this->assertSame( array( 'ishot-tokens' ), $styles->registered['ishot-bundle']->deps );
		$this->assertSame( array( 'ishot-bundle' ), $styles->registered['ishot-theme']->deps );
	}
}
