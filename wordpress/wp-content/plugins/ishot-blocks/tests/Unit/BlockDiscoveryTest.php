<?php
namespace IShot\Blocks\Tests\Unit;

use IShot\Blocks\Plugin;
use PHPUnit\Framework\TestCase;

/** Which folders under build/blocks hold a registrable block.json. */
final class BlockDiscoveryTest extends TestCase {

	private string $dir = '';

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/ishot-blocks-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->dir . '/alpha', 0777, true );
		mkdir( $this->dir . '/beta', 0777, true );
		mkdir( $this->dir . '/empty', 0777, true );
		file_put_contents( $this->dir . '/alpha/block.json', '{}' );
		file_put_contents( $this->dir . '/beta/block.json', '{}' );
		file_put_contents( $this->dir . '/stray-file.txt', 'not a block' );
	}

	protected function tearDown(): void {
		foreach ( array( '/alpha/block.json', '/beta/block.json', '/stray-file.txt' ) as $file ) {
			unlink( $this->dir . $file );
		}
		foreach ( array( '/alpha', '/beta', '/empty', '' ) as $folder ) {
			rmdir( $this->dir . $folder );
		}
	}

	public function test_finds_block_folders_and_reports_those_without_block_json(): void {
		$result = Plugin::discover( $this->dir );

		$this->assertSame( array( $this->dir . '/alpha', $this->dir . '/beta' ), $result['found'] );
		$this->assertSame( array( $this->dir . '/empty' ), $result['missing'] );
	}

	public function test_a_missing_build_folder_is_not_an_error(): void {
		$this->assertSame(
			array(
				'found'   => array(),
				'missing' => array(),
			),
			Plugin::discover( $this->dir . '/does-not-exist' )
		);
	}
}
