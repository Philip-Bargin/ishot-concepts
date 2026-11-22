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

		// Tokens first, then the design-system bundle, then the prototype's app layer, then the theme overrides.
		$this->assertSame( array( 'ishot-tokens', 'ishot-bundle', 'ishot-app', 'ishot-theme' ), $styles->to_do );
		$this->assertSame( array(), $styles->registered['ishot-tokens']->deps );
		$this->assertSame( array( 'ishot-tokens' ), $styles->registered['ishot-bundle']->deps );
		$this->assertSame( array( 'ishot-bundle' ), $styles->registered['ishot-app']->deps );
		$this->assertSame( array( 'ishot-app' ), $styles->registered['ishot-theme']->deps );
	}

	public function test_editor_loads_the_same_four_stylesheets_in_order(): void {
		$expected = array( 'assets/css/tokens.css', 'assets/css/bundle.css', 'assets/css/app.css', 'assets/css/theme.css' );
		$this->assertSame( $expected, array_values( ISHOT_THEME_STYLES ) );
		foreach ( $expected as $path ) {
			$this->assertFileExists( get_theme_file_path( $path ) );
		}
		$this->assertSame( $expected, array_values( $GLOBALS['editor_styles'] ?? array() ) );
	}

	public function test_registers_theme_blocks_from_a_build_folder_and_skips_one_without_block_json(): void {
		$dir = __DIR__ . '/fixtures/build/blocks';
		try {
			$registered = ishot_theme_register_blocks( $dir );
			$this->assertSame( array( 'ishot-theme-fixture/frame' ), $registered );
			$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'ishot-theme-fixture/frame' ) );
			// A second call in the same request is harmless: names already registered are not registered twice.
			$this->assertSame( array(), ishot_theme_register_blocks( $dir ) );
		} finally {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( 'ishot-theme-fixture/frame' ) ) {
				unregister_block_type( 'ishot-theme-fixture/frame' );
			}
		}
	}

	public function test_missing_build_folder_is_silent(): void {
		$this->assertSame( array(), ishot_theme_register_blocks( __DIR__ . '/does-not-exist' ) );
	}

	public function test_theme_registers_its_own_build_folder_on_init(): void {
		$this->assertSame( 10, has_action( 'init', 'ishot_theme_register_built_blocks' ) );
		$this->assertSame( get_theme_file_path( 'build/blocks' ), ishot_theme_blocks_directory() );
	}
}
