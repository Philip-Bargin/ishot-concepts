<?php
/**
 * The `ishot/site-header` block: the prototype's Header and MenuSheet (src/components/Header.jsx and
 * MenuSheet.jsx at 50e60f4), with the menu from a core wp_navigation entity.
 *
 * @package IShot\Theme
 */

use IShot\Blocks\Icons;
use IShot\Blocks\Plugin;
use IShot\Blocks\SocialLinks;
use IShot\Blocks\Tests\Support\MarkupNormaliser;
use IShot\Booking\Settings;

/** Header markup, the menu sheet, navigation sources, aria-current and the contact line. */
final class SiteHeaderBlockTest extends WP_UnitTestCase {

	private const BLOCK = '<!-- wp:ishot/site-header /-->';

	private const LABELS = array( 'Home', 'About', 'Services', 'Portfolio', 'Creative direction', 'Journal', 'Contact', 'Book a session' );

	private string $request_uri = '';

	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
		$this->request_uri = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI'] = '/';
	}

	public function tear_down(): void {
		$_SERVER['REQUEST_URI'] = $this->request_uri;
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'ishot/social-links' ) ) {
			Plugin::registerBuiltBlocks();
		}
		parent::tear_down();
	}

	private static function render( string $block = self::BLOCK ): string {
		return do_blocks( $block );
	}

	private static function studio(): void {
		Settings::update(
			array(
				'studio_address'       => 'Monrovia, Liberia',
				'studio_phone'         => '0881405259',
				'studio_instagram_url' => 'https://www.instagram.com/ishotconcept/',
				'studio_whatsapp'      => '+231 881 405 259',
			)
		);
	}

	/** @param list<array{0: string, 1: string}> $links label, url */
	private function navigation( array $links, string $status = 'publish', string $extra = '' ): int {
		$content = $extra;
		foreach ( $links as $link ) {
			$content .= serialize_block(
				array(
					'blockName'    => 'core/navigation-link',
					'attrs'        => array(
						'label' => $link[0],
						'url'   => $link[1],
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		}
		return self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => $status,
				'post_title'   => 'Nav',
				'post_content' => wp_slash( $content ),
			)
		);
	}

	/** @return list<string> Labels of the menu links (without the NN numerals). */
	private static function menuLabels( string $html ): array {
		preg_match_all( '#<li style="--i:\d+"><a [^>]*><span>\d\d</span>([^<]*)</a></li>#', $html, $m );
		return array_map( 'html_entity_decode', $m[1] );
	}

	private static function menuHrefs( string $html ): array {
		preg_match_all( '#<li style="--i:\d+"><a [^>]*href="([^"]*)"#', $html, $m );
		return $m[1];
	}

	public function test_block_is_registered_with_a_view_script_module(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'ishot/site-header' );
		$this->assertNotNull( $type );
		$this->assertNotEmpty( $type->view_script_module_ids );
	}

	public function test_markup_matches_the_prototype_header_and_menu_sheet(): void {
		self::studio();
		$_SERVER['REQUEST_URI'] = '/about/';
		$social = SocialLinks::fromStudio();
		$logo   = '<img class="ish-logo__full" src="x" alt="iShot Concept"><img class="ish-logo__rev" src="x" alt="iShot Concept">';
		$items  = '';
		foreach ( self::LABELS as $i => $label ) {
			$current = 'About' === $label ? ' aria-current="page" class="active"' : '';
			$items  .= '<li style="--i:' . $i . '"><a href="x"' . $current . '><span>' . sprintf( '%02d', $i + 1 ) . '</span>' . $label . '</a></li>';
		}

		// As React renders Header + MenuSheet (open) for /about with the prototype's data.
		$expected = '<header class="fo-header"><div class="fo-container ish-header__inner"><a class="fo-logo" href="/">' . $logo . '</a>'
			. '<nav class="fo-header__social" aria-label="Social">' . $social . '</nav>'
			. '<div class="fo-header__actions"><a class="fo-navlink ish-signin" href="/account">My bookings</a><a class="fo-btn fo-btn--primary fo-btn--sm" href="/book">Book now</a>'
			. '<button type="button" class="fo-menu-toggle" aria-expanded="false" aria-controls="site-menu">Menu ' . Icons::svg( 'menu', '', 20 ) . '</button></div></div></header>'
			. '<div id="site-menu" class="fo-menu fo-band" role="dialog" aria-modal="true" aria-label="Site menu"><div class="ish-menu__top"><a href="/" class="fo-logo"><img src="x" alt="iShot Concept"></a>'
			. '<button type="button" class="fo-icon-btn" aria-label="Close menu">' . Icons::svg( 'x' ) . '</button></div>'
			. '<nav aria-label="Main"><ol class="fo-menu__list">' . $items . '</ol></nav>'
			. '<div class="ish-menu__foot"><a class="fo-btn fo-btn--primary" href="/book">Book a session</a><p class="small">Monrovia, Liberia · <a href="tel:0881405259">0881405259</a></p><div class="ish-social">' . $social . '</div></div></div>';

		$this->assertSame( MarkupNormaliser::normalise( $expected ), MarkupNormaliser::normalise( self::render() ) );
	}

	public function test_links_point_to_the_wordpress_urls(): void {
		$html = self::render();

		$this->assertStringContainsString( '<a class="fo-logo" href="' . esc_url( home_url( '/' ) ) . '">', $html );
		$this->assertStringContainsString( 'href="' . esc_url( home_url( '/account/' ) ) . '">My bookings</a>', $html );
		$this->assertStringContainsString( 'href="' . esc_url( home_url( '/book/' ) ) . '">Book now</a>', $html );
		$this->assertStringContainsString( 'href="' . esc_url( home_url( '/book/' ) ) . '"', substr( $html, (int) strpos( $html, 'ish-menu__foot' ) ) );
	}

	public function test_sheet_is_server_rendered_hidden_with_the_dialog_attributes(): void {
		$html = self::render();

		$this->assertMatchesRegularExpression( '#<div id="site-menu" class="fo-menu fo-band" role="dialog" aria-modal="true" aria-label="Site menu"[^>]* hidden[ >=]#', $html );
		$this->assertMatchesRegularExpression( '#<button type="button" class="fo-menu-toggle" aria-expanded="false" aria-controls="site-menu"#', $html );
		$this->assertSame( 1, substr_count( $html, 'id="site-menu"' ) );
	}

	public function test_interactivity_directives_are_present(): void {
		$html = self::render();

		$this->assertSame( 2, substr_count( $html, 'data-wp-interactive="ishot/site-header"' ) );
		$this->assertStringContainsString( 'data-wp-on--click="actions.open"', $html );
		$this->assertStringContainsString( 'data-wp-bind--aria-expanded="state.isOpen"', $html );
		$this->assertStringContainsString( 'data-wp-bind--hidden="!state.isOpen"', $html );
		$this->assertStringContainsString( 'data-wp-watch="callbacks.trap"', $html );
		// Close button and every menu link close the sheet.
		$this->assertSame( 1 + 8 + 2, substr_count( $html, 'data-wp-on--click="actions.close"' ), 'Close button, 8 links, the sheet logo and Book a session.' );
	}

	public function test_first_menu_link_is_the_autofocus_target_and_stagger_indexes_count_from_zero(): void {
		$html = self::render();

		$this->assertSame( 1, substr_count( $html, 'data-autofocus' ) );
		$this->assertMatchesRegularExpression( '#<li style="--i:0"><a [^>]*data-autofocus=""#', $html );
		$this->assertStringContainsString( '<li style="--i:7">', $html );
		$this->assertStringContainsString( '<span>01</span>Home</a>', $html );
		$this->assertStringContainsString( '<span>08</span>Book a session</a>', $html );
	}

	public function test_without_a_navigation_entity_the_eight_prototype_items_are_used(): void {
		$html = self::render();

		$this->assertSame( self::LABELS, self::menuLabels( $html ) );
		$paths = array( '/', '/about/', '/services/', '/portfolio/', '/creative-direction/', '/journal/', '/contact/', '/book/' );
		$this->assertSame( array_map( static fn( $p ) => esc_url( home_url( $p ) ), $paths ), self::menuHrefs( $html ) );
	}

	public function test_menu_comes_from_the_first_published_navigation(): void {
		$this->navigation( array( array( 'Draft', '/draft/' ) ), 'draft' );
		$this->navigation( array( array( 'Studio', home_url( '/about/' ) ), array( 'Say hello', home_url( '/contact/' ) ) ), 'publish', '<!-- wp:paragraph --><p>ignored</p><!-- /wp:paragraph -->' );
		$this->navigation( array( array( 'Later', '/later/' ) ) );

		$html = self::render();
		$this->assertSame( array( 'Studio', 'Say hello' ), self::menuLabels( $html ) );
		$this->assertStringNotContainsString( 'ignored', $html );
	}

	public function test_the_published_primary_navigation_is_preferred_over_an_older_one(): void {
		// Core can leave an older "Navigation" menu behind (e.g. a page list from a previous theme);
		// the one `wp ishot import-prototype` creates and the owner edits is "Primary".
		$this->navigation( array( array( 'Older', '/older/' ) ) );
		$primary = $this->navigation( array( array( 'From primary', '/primary/' ) ) );
		wp_update_post(
			array(
				'ID'         => $primary,
				'post_title' => 'Primary',
			)
		);
		$draft = $this->navigation( array( array( 'Draft primary', '/draft/' ) ), 'draft' );
		wp_update_post(
			array(
				'ID'         => $draft,
				'post_title' => 'Primary',
			)
		);

		$this->assertSame( array( 'From primary' ), self::menuLabels( self::render() ) );
	}

	public function test_ref_attribute_selects_the_navigation(): void {
		$this->navigation( array( array( 'First', '/first/' ) ) );
		$ref = $this->navigation( array( array( 'Chosen', '/chosen/' ) ) );

		$this->assertSame( array( 'Chosen' ), self::menuLabels( self::render( '<!-- wp:ishot/site-header {"ref":' . $ref . '} /-->' ) ) );
	}

	public function test_a_missing_navigation_entity_falls_back(): void {
		$this->assertSame( self::LABELS, self::menuLabels( self::render( '<!-- wp:ishot/site-header {"ref":999999} /-->' ) ) );

		$draft = $this->navigation( array( array( 'Draft', '/draft/' ) ), 'draft' );
		$this->assertSame( self::LABELS, self::menuLabels( self::render( '<!-- wp:ishot/site-header {"ref":' . $draft . '} /-->' ) ) );

		$this->navigation( array( array( 'Published', '/published/' ) ) );
		$this->assertSame( array( 'Published' ), self::menuLabels( self::render( '<!-- wp:ishot/site-header {"ref":999999} /-->' ) ) );
	}

	public function test_a_navigation_without_links_falls_back_to_the_prototype_items(): void {
		$this->navigation( array(), 'publish', '<!-- wp:paragraph --><p>only text</p><!-- /wp:paragraph -->' );
		$this->assertSame( self::LABELS, self::menuLabels( self::render() ) );
	}

	public function test_navigation_labels_and_urls_are_escaped(): void {
		$this->navigation( array( array( '<b>Bold</b> & "q"', '/bold/' ), array( 'Evil', 'javascript:alert(1)' ) ) );
		$html = self::render();

		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringContainsString( 'Bold &amp; &quot;q&quot;', $html );
		$this->assertSame( array( 'Bold & "q"' ), self::menuLabels( $html ), 'a link with an unsafe URL is dropped' );
	}

	/** @return array<string, array{0: string, 1: string|null}> */
	public static function currentPages(): array {
		return array(
			'home'            => array( '/', 'Home' ),
			'about'           => array( '/about/', 'About' ),
			'about no slash'  => array( '/about', 'About' ),
			'journal article' => array( '/journal/a-post/', 'Journal' ),
			'portfolio cat'   => array( '/portfolio/weddings/', 'Portfolio' ),
			'query string'    => array( '/contact/?sent=1', 'Contact' ),
			'not in the menu' => array( '/account/', null ),
			'prefix only'     => array( '/aboutness/', null ),
		);
	}

	/** @dataProvider currentPages */
	public function test_aria_current_marks_the_current_url( string $uri, ?string $label ): void {
		$_SERVER['REQUEST_URI'] = $uri;
		$html                   = self::render();

		preg_match_all( '#<a [^>]*aria-current="page"[^>]*><span>\d\d</span>([^<]*)</a>#', $html, $m );
		$this->assertSame( null === $label ? array() : array( $label ), $m[1] );
	}

	public function test_contact_line_from_studio_details(): void {
		self::studio();
		$this->assertStringContainsString( '<p class="small">Monrovia, Liberia · <a href="tel:0881405259">0881405259</a></p>', self::render() );
	}

	public function test_empty_studio_details_omit_the_contact_line(): void {
		$html = self::render();
		$this->assertStringNotContainsString( '<p class="small">', $html );
		$this->assertStringNotContainsString( 'tel:', $html );
		$this->assertStringContainsString( 'Book a session</a><div class="ish-social">', $html );
	}

	public function test_contact_line_omits_an_empty_part_and_its_separator(): void {
		Settings::update( array( 'studio_address' => 'Monrovia, Liberia' ) );
		$this->assertStringContainsString( '<p class="small">Monrovia, Liberia</p>', self::render() );

		Settings::update(
			array(
				'studio_address' => '',
				'studio_phone'   => '+231 881 405 259',
			)
		);
		$this->assertStringContainsString( '<p class="small"><a href="tel:+231881405259">+231 881 405 259</a></p>', self::render() );
	}

	public function test_a_phone_without_a_link_prints_as_text(): void {
		Settings::update(
			array(
				'studio_address' => 'Monrovia',
				'studio_phone'   => 'ask us',
			)
		);
		$this->assertStringContainsString( '<p class="small">Monrovia · ask us</p>', self::render() );
	}

	public function test_no_empty_social_landmark_when_the_social_links_block_is_not_registered(): void {
		unregister_block_type( 'ishot/social-links' );
		$html = self::render();

		$this->assertStringNotContainsString( 'fo-header__social', $html, 'no empty "Social" navigation landmark' );
		$this->assertStringNotContainsString( 'ish-social', $html, 'no empty wrapper in the menu foot' );
		$this->assertStringContainsString( '<div class="fo-container ish-header__inner"><a class="fo-logo"', $html );
		$this->assertStringContainsString( '</a><div class="fo-header__actions">', $html );
		$this->assertStringContainsString( 'id="site-menu"', $html );
	}

	public function test_no_empty_social_landmark_when_the_social_links_block_prints_nothing(): void {
		// As when ishot-blocks is active without ishot-booking: the block is registered but renders ''.
		$empty = static fn( string $content, array $block ): string => 'ishot/social-links' === $block['blockName'] ? '' : $content;
		add_filter( 'render_block', $empty, 10, 2 );
		try {
			$html = self::render();
		} finally {
			remove_filter( 'render_block', $empty, 10 );
		}

		$this->assertStringNotContainsString( 'fo-header__social', $html );
		$this->assertStringNotContainsString( 'ish-social', $html );
	}

	public function test_header_html_is_identical_for_a_guest_and_a_logged_in_administrator(): void {
		self::studio();
		$this->navigation( array( array( 'About', home_url( '/about/' ) ) ) );
		$guest = self::render();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( $guest, self::render() );
	}
}
