<?php
namespace IShot\Blocks\Tests\Integration;

use IShot\Blocks\Plugin;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/** Block registration and the plugin's dependency notice. */
final class PluginTest extends WP_UnitTestCase {

	private const FIXTURES = __DIR__ . '/../fixtures/build/blocks';

	public function set_up(): void {
		parent::set_up();
		Plugin::forgetSkipped();
	}

	public function tear_down(): void {
		// Every test starts with a clean registry and an empty skipped list.
		foreach ( array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $name ) {
			if ( str_starts_with( $name, 'ishot-fixture/' ) ) {
				unregister_block_type( $name );
			}
		}
		Plugin::forgetSkipped();
		parent::tear_down();
	}

	public function test_registers_every_built_block(): void {
		$result = Plugin::registerBlocks( self::FIXTURES );

		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'ishot-fixture/sample' ) );
		$this->assertCount( 1, $result['registered'] );
	}

	public function test_a_block_folder_without_block_json_does_not_fatal_and_is_reported(): void {
		$result = Plugin::registerBlocks( self::FIXTURES );

		$this->assertCount( 1, $result['missing'] );
		$this->assertStringEndsWith( 'incomplete', $result['missing'][0] );
	}

	public function test_registers_blocks_on_init_for_the_real_build_folder(): void {
		$this->assertNotFalse( has_action( 'init', array( Plugin::class, 'registerBuiltBlocks' ) ) );
		// The shipped build folder is valid even with zero blocks.
		$this->assertSame( array(), Plugin::registerBlocks( dirname( __DIR__, 2 ) . '/build/blocks' )['missing'] );
	}

	public function test_blocks_register_without_the_theme_being_consulted(): void {
		// The bootstrap pins the template/stylesheet options; lift that so the switch is real.
		$pinned = array( 'pre_option_template', 'pre_option_stylesheet' );
		$saved  = array();
		foreach ( $pinned as $hook ) {
			$saved[ $hook ] = $GLOBALS['wp_filter'][ $hook ] ?? null;
			unset( $GLOBALS['wp_filter'][ $hook ] );
		}
		$before = get_stylesheet();

		try {
			$other = 'twentytwentyfive';
			$this->assertTrue( wp_get_theme( $other )->exists(), 'The tests environment needs a second theme.' );
			switch_theme( $other );
			$this->assertSame( $other, get_stylesheet() );
			$this->assertNotSame( 'ishot-concept', get_template() );

			// The plugin's own entry point, wired to init, resolving its folder from ISHOT_BLOCKS_FILE.
			$build = Plugin::buildDirectory();
			$this->assertSame( dirname( ISHOT_BLOCKS_FILE ) . '/build/blocks', $build );
			$this->assertStringStartsWith( WP_PLUGIN_DIR . '/', $build );
			$this->assertStringNotContainsString( get_theme_root(), $build );
			$this->assertSame( 10, has_action( 'init', array( Plugin::class, 'registerBuiltBlocks' ) ) );

			Plugin::registerBuiltBlocks();
			// The shipped build has no blocks yet: nothing fatal, nothing skipped.
			$this->assertSame( array(), Plugin::registerBlocks( $build )['missing'] );

			// And a block folder from the plugin registers while a different theme is active.
			$result = Plugin::registerBlocks( self::FIXTURES );
			$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'ishot-fixture/sample' ) );
			$this->assertCount( 1, $result['registered'] );
		} finally {
			switch_theme( $before );
			foreach ( $saved as $hook => $callbacks ) {
				if ( null !== $callbacks ) {
					$GLOBALS['wp_filter'][ $hook ] = $callbacks;
				}
			}
		}
	}

	public function test_registering_the_same_build_twice_in_one_request_reports_nothing(): void {
		// A real block in a real build folder: a copy of the sample fixture in a throwaway directory.
		$build = trailingslashit( get_temp_dir() ) . 'ishot-blocks-twice-' . wp_generate_password( 8, false ) . '/blocks';
		wp_mkdir_p( $build . '/sample' );
		copy( self::FIXTURES . '/sample/block.json', $build . '/sample/block.json' );

		try {
			$first = Plugin::registerBlocks( $build );
			$this->assertSame( array( 'ishot-fixture/sample' ), $first['registered'] );
			$this->assertSame( array(), $first['missing'] );

			$second = Plugin::registerBlocks( $build );
			$this->assertSame( array(), $second['missing'] );
			$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'ishot-fixture/sample' ) );

			wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
			$this->assertSame( '', $this->skippedNoticeHtml() );
		} finally {
			unlink( $build . '/sample/block.json' );
			rmdir( $build . '/sample' );
			rmdir( $build );
			rmdir( dirname( $build ) );
		}
	}

	public function test_rejected_block_is_reported_with_its_reason_to_administrators_only(): void {
		Plugin::forgetSkipped();
		$result = Plugin::registerBlocks( __DIR__ . '/../fixtures/rejected' );
		$this->assertCount( 1, $result['missing'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$html = $this->skippedNoticeHtml();
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'broken: could not be registered because WordPress rejected its block.json', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( '', $this->skippedNoticeHtml() );
		Plugin::forgetSkipped();
	}

	public function test_no_skipped_notice_when_everything_registered(): void {
		Plugin::forgetSkipped();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( '', $this->skippedNoticeHtml() );
	}

	public function test_booking_notice_is_shown_to_administrators_only(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( '', $this->noticeHtml( true ) );
		$html = $this->noticeHtml( false );
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'iShot Booking', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( '', $this->noticeHtml( false ) );
	}

	private function skippedNoticeHtml(): string {
		ob_start();
		Plugin::renderSkippedNotice();
		return (string) ob_get_clean();
	}

	private function noticeHtml( bool $booking_active ): string {
		ob_start();
		Plugin::renderBookingNotice( $booking_active );
		return (string) ob_get_clean();
	}
}
