<?php
namespace IShot\Blocks\Tests\Integration;

use IShot\Blocks\Plugin;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/** Block registration and the plugin's dependency notice. */
final class PluginTest extends WP_UnitTestCase {

	private const FIXTURES = __DIR__ . '/../fixtures/build/blocks';

	public function tear_down(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'ishot-fixture/sample' ) ) {
			unregister_block_type( 'ishot-fixture/sample' );
		}
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
		// Content independence: registration reads only this plugin's own build folder.
		$before = get_stylesheet();
		switch_theme( WP_DEFAULT_THEME );
		$result = Plugin::registerBlocks( self::FIXTURES );
		switch_theme( $before );

		$this->assertCount( 1, $result['registered'] );
	}

	public function test_notice_is_shown_when_ishot_booking_is_missing(): void {
		$this->assertSame( '', $this->noticeHtml( true ) );
		$html = $this->noticeHtml( false );
		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'iShot Booking', $html );
	}

	private function noticeHtml( bool $booking_active ): string {
		ob_start();
		Plugin::renderBookingNotice( $booking_active );
		return (string) ob_get_clean();
	}
}
