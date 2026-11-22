<?php
namespace IShot\Blocks\Tests\Integration;

use IShot\Blocks\Notice;
use IShot\Booking\Content\Fields;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/** The `ishot/notice` block: the prototype's PrototypeNotice for the current post while it is a placeholder. */
final class NoticeBlockTest extends WP_UnitTestCase {

	private const BLOCK = '<!-- wp:ishot/notice /-->';

	private function post( ?string $status ): int {
		$id = self::factory()->post->create();
		if ( null !== $status ) {
			update_post_meta( $id, 'content_status', $status );
		}
		return $id;
	}

	/** Renders the block inside the main loop for $id, as a singular template does. */
	private function renderFor( int $id ): string {
		$this->go_to( get_permalink( $id ) );
		$html = '';
		while ( have_posts() ) {
			the_post();
			$html = do_blocks( self::BLOCK );
		}
		return trim( $html );
	}

	public function test_block_is_registered_from_the_plugin_build_with_an_editor_script(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'ishot/notice' );
		$this->assertNotNull( $type );
		$this->assertNotEmpty( $type->editor_script_handles, 'the Site Editor needs an editorScript' );
		$this->assertContains( 'postId', $type->uses_context );
		$this->assertFalse( $type->supports['html'] ?? true );
	}

	public function test_prints_the_notice_for_placeholder_content(): void {
		$id = $this->post( Fields::PLACEHOLDER );
		$this->assertSame( Notice::forPost( $id ), $this->renderFor( $id ) );
		$this->assertStringContainsString( '<strong>Sample content.</strong>', $this->renderFor( $id ) );
	}

	/** @return array<string, array{?string}> */
	public static function notPlaceholder(): array {
		return array(
			'approved'  => array( Fields::APPROVED ),
			'no status' => array( null ),
		);
	}

	/** @dataProvider notPlaceholder */
	public function test_prints_nothing_otherwise( ?string $status ): void {
		$this->assertSame( '', $this->renderFor( $this->post( $status ) ) );
	}

	public function test_hides_again_once_the_owner_approves(): void {
		$id = $this->post( Fields::PLACEHOLDER );
		$this->assertNotSame( '', $this->renderFor( $id ) );
		update_post_meta( $id, 'content_status', Fields::APPROVED );
		$this->assertSame( '', $this->renderFor( $id ) );
	}

	public function test_block_context_post_id_wins_over_the_global_post(): void {
		$placeholder = $this->post( Fields::PLACEHOLDER );
		$approved    = $this->post( Fields::APPROVED );
		$this->go_to( get_permalink( $approved ) );

		$block = new \WP_Block( array( 'blockName' => 'ishot/notice' ), array( 'postId' => $placeholder ) );
		$this->assertStringContainsString( 'fo-alert', $block->render() );
	}

	public function test_prints_nothing_outside_a_post(): void {
		$this->go_to( '/' );
		$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- no current post.
		$this->assertSame( '', trim( do_blocks( self::BLOCK ) ) );
	}

	public function test_markup_is_the_same_for_guests_and_logged_in_users(): void {
		$id    = $this->post( Fields::PLACEHOLDER );
		$guest = $this->renderFor( $id );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( $guest, $this->renderFor( $id ) );
	}
}
