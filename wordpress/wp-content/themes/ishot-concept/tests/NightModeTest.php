<?php
/**
 * Night mode: the head bootstrap, the server-side default and the `ishot/theme-toggle` block.
 *
 * @package IShot\Theme
 */

/** The Night choice is applied before any stylesheet; the toggle block renders the prototype's button. */
final class NightModeTest extends WP_UnitTestCase {

	public function tear_down(): void {
		// do_action( 'wp_head' ) enqueues and prints the styles; leave a clean queue for other tests.
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;
		parent::tear_down();
	}

	private function head(): string {
		ob_start();
		do_action( 'wp_head' );
		return (string) ob_get_clean();
	}

	public function test_storage_key_is_the_prototypes_key(): void {
		$this->assertSame( 'ishot-theme', ishot_theme_storage_key() );
	}

	public function test_head_script_is_printed_at_priority_zero_before_any_stylesheet(): void {
		$this->assertSame( 0, has_action( 'wp_head', 'ishot_theme_print_night_bootstrap' ) );

		$head   = $this->head();
		$script = strpos( $head, 'ishot-theme' );
		$style  = strpos( $head, 'ishot-tokens-css' );

		$this->assertNotFalse( $script, 'The Night bootstrap script is printed.' );
		$this->assertNotFalse( $style, 'The theme stylesheets are printed.' );
		$this->assertLessThan( $style, $script );
		$this->assertMatchesRegularExpression( '#<script[^>]* id="ishot-night-bootstrap">#', $head );
	}

	public function test_head_script_reads_the_guarded_choice_and_defaults_to_light(): void {
		$head = $this->head();
		preg_match( '#<script[^>]* id="ishot-night-bootstrap">(.*?)</script>#s', $head, $m );
		$script = $m[1] ?? '';

		$this->assertStringContainsString( "localStorage.getItem('ishot-theme')", $script );
		$this->assertStringContainsString( 'try', $script );
		$this->assertStringContainsString( 'catch', $script );
		$this->assertStringContainsString( 'documentElement.dataset.theme', $script );
		$this->assertStringContainsString( "'dark'", $script );
		$this->assertStringContainsString( "'light'", $script );
		$this->assertLessThan( 400, strlen( $script ), 'The bootstrap stays tiny.' );
		$this->assertStringNotContainsString( 'src=', $m[0] ?? '' );
	}

	public function test_html_element_starts_in_light(): void {
		$this->assertStringContainsString( 'data-theme="light"', get_language_attributes() );
	}

	public function test_light_default_is_not_added_twice(): void {
		$attrs = apply_filters( 'language_attributes', 'lang="en-US" data-theme="dark"', 'html' );
		$this->assertSame( 1, substr_count( $attrs, 'data-theme=' ) );
	}

	public function test_toggle_block_is_registered_from_the_build(): void {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'ishot/theme-toggle' ) );
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'ishot/theme-toggle' );
		$this->assertNotEmpty( $type->view_script_module_ids );
	}

	public function test_toggle_block_renders_the_prototypes_button(): void {
		$html = do_blocks( '<!-- wp:ishot/theme-toggle /-->' );

		$this->assertMatchesRegularExpression( '#^<button type="button" class="ish-linkbtn" aria-pressed="false"[^>]*>Night mode</button>$#', trim( $html ) );
		$this->assertStringContainsString( 'data-wp-interactive="ishot/theme"', $html );
		$this->assertStringContainsString( 'data-wp-on--click="actions.toggle"', $html );
		$this->assertStringContainsString( 'data-wp-init="callbacks.sync"', $html );
	}

	public function test_toggle_block_passes_the_shared_storage_key_through_context(): void {
		$html = do_blocks( '<!-- wp:ishot/theme-toggle /-->' );

		$this->assertMatchesRegularExpression( '#data-wp-context=\'\{"storageKey":"ishot-theme"[^\']*\}\'#', $html );
	}

	public function test_toggle_markup_is_the_same_for_guests_and_logged_in_users(): void {
		$guest = do_blocks( '<!-- wp:ishot/theme-toggle /-->' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( $guest, do_blocks( '<!-- wp:ishot/theme-toggle /-->' ) );
	}

	public function test_manage_page_csp_still_allows_inline_scripts_for_the_night_bootstrap(): void {
		$csp = \IShot\Booking\Frontend\ManageUrl::headers()['Content-Security-Policy'];
		$this->assertMatchesRegularExpression( "#script-src [^;]*'unsafe-inline'#", $csp );
	}
}
