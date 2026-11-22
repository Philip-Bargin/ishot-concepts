<?php
/**
 * The footer template part: the prototype's Footer (src/components/Footer.jsx at 50e60f4) built from
 * core blocks (editable in the Site Editor) plus ishot/logo, ishot/social-links,
 * ishot/contact-details and ishot/theme-toggle.
 *
 * @package IShot\Theme
 */

use IShot\Blocks\SocialLinks;
use IShot\Blocks\Tests\Support\MarkupNormaliser;
use IShot\Blocks\Tests\Support\ValidBlockMarkup;
use IShot\Booking\Settings;

/** Markup parity with Footer.jsx, valid blocks, the year bindings source and the empty-wrapper rules. */
final class FooterPartTest extends WP_UnitTestCase {
	use ValidBlockMarkup;

	private const PART = '<!-- wp:template-part {"slug":"footer","theme":"ishot-concept","tagName":"div"} /-->';

	private const LEGAL = 'iShot Concept · Photography, creative direction, visual storytelling';

	/** @var array<string, WP_Block_Type> Blocks unregistered by a test, restored in tear_down. */
	private array $unregistered = array();

	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
	}

	public function tear_down(): void {
		foreach ( $this->unregistered as $type ) {
			register_block_type( $type );
		}
		$this->unregistered = array();
		parent::tear_down();
	}

	private function unregister( string $name ): void {
		$this->unregistered[ $name ] = WP_Block_Type_Registry::get_instance()->get_registered( $name );
		unregister_block_type( $name );
	}

	private static function fillStudio(): void {
		Settings::update(
			array(
				'studio_phone'         => '0881405259',
				'studio_public_email'  => 'ishotconcept01@gmail.com',
				'studio_address'       => 'Monrovia, Liberia',
				'studio_instagram_url' => 'https://www.instagram.com/ishotconcept/',
				'studio_whatsapp'      => '+231 881 405 259',
			)
		);
	}

	private static function partFile(): string {
		return (string) file_get_contents( get_theme_file_path( 'parts/footer.html' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
	}

	/** Footer.jsx with the prototype's data, as React renders it (year from the server clock). */
	private static function prototype(): string {
		$links = static function ( string $id, string $label, array $items ): string {
			$out = '<nav class="fo-footer__col" aria-labelledby="' . $id . '"><p id="' . $id . '" class="fo-eyebrow">' . $label . '</p>';
			foreach ( $items as $path => $text ) {
				$out .= '<a href="' . $path . '">' . $text . '</a>';
			}
			return $out . '</nav>';
		};
		return '<footer class="fo-footer"><div class="fo-footer__grid ish-footer__outer"><div class="fo-container ish-footer__grid">'
			. '<div class="fo-footer__col ish-footer__brand"><a class="fo-logo" href="/">' . ishot_theme_logo_html() . '</a>'
			. '<span class="fo-spectrum" aria-hidden="true"><i></i><i></i><i></i><i></i></span>'
			. '<p class="small">A creative photography brand turning moments into timeless visual stories.</p>'
			. '<div class="ish-social">' . SocialLinks::fromStudio() . '</div></div>'
			. $links( 'footer-studio', 'Studio', array( '/about' => 'About', '/services' => 'Services', '/portfolio' => 'Portfolio' ) )
			. $links( 'footer-book', 'Book', array( '/book' => 'Book a session', '/account' => 'My bookings', '/contact' => 'Contact' ) )
			. '<div class="fo-footer__col"><p class="fo-eyebrow">Visit</p><span class="small">Monrovia, Liberia</span><a href="tel:0881405259">0881405259</a><a href="mailto:ishotconcept01@gmail.com">ishotconcept01@gmail.com</a></div>'
			. '</div></div>'
			. '<div class="fo-footer__legal ish-footer__outer"><div class="fo-container ish-footer__legal"><span>© ' . wp_date( 'Y' ) . ' ' . self::LEGAL . '</span><button type="button" class="ish-linkbtn" aria-pressed="false">Night mode</button></div></div>'
			. '</footer>';
	}

	/**
	 * Rendered part reduced to what the prototype has: WordPress-only classes (wp-*, is-layout-*) and
	 * whitespace between blocks removed, core's `decoding="async"` on the logo images dropped (no visual
	 * effect, see the Task 1.2 report), the link paragraphs (display: contents in theme.css) unwrapped,
	 * and the legal paragraph read as the prototype's span (accepted exception R-1.3-1).
	 */
	private static function reduce( string $html ): string {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();
		$xpath = new DOMXPath( $doc );

		foreach ( iterator_to_array( $xpath->query( '//text()[normalize-space(.) = ""]' ) ) as $node ) {
			$node->parentNode->removeChild( $node );
		}
		foreach ( iterator_to_array( $xpath->query( '//img[@decoding="async"]' ) ) as $img ) {
			$img->removeAttribute( 'decoding' );
		}
		foreach ( iterator_to_array( $xpath->query( '//*[@class]' ) ) as $el ) {
			$keep = array_filter( preg_split( '/\s+/', $el->getAttribute( 'class' ) ), static fn( $c ) => '' !== $c && ! preg_match( '/^(wp-|is-layout-)/', $c ) );
			if ( array() === $keep ) {
				$el->removeAttribute( 'class' );
			} else {
				$el->setAttribute( 'class', implode( ' ', $keep ) );
			}
		}
		foreach ( iterator_to_array( $xpath->query( '//*[contains(concat(" ", @class, " "), " fo-footer__col ")]/p[not(@class)]' ) ) as $p ) {
			while ( $p->firstChild ) {
				$p->parentNode->insertBefore( $p->firstChild, $p );
			}
			$p->parentNode->removeChild( $p );
		}
		foreach ( iterator_to_array( $xpath->query( '//*[contains(concat(" ", @class, " "), " ish-footer__legal ")]/p' ) ) as $p ) {
			$span = $doc->createElement( 'span' );
			while ( $p->firstChild ) {
				$span->appendChild( $p->firstChild );
			}
			$p->parentNode->replaceChild( $span, $p );
		}
		$footer = $xpath->query( '//footer' )->item( 0 );
		return $footer ? (string) $doc->saveHTML( $footer ) : '';
	}

	private static function render(): string {
		return do_blocks( self::PART );
	}

	public function test_footer_part_is_declared_in_the_footer_area(): void {
		$parts = wp_get_theme_data_template_parts();
		$this->assertArrayHasKey( 'footer', $parts );
		$this->assertSame( WP_TEMPLATE_PART_AREA_FOOTER, $parts['footer']['area'] );
	}

	public function test_rendered_part_matches_the_prototype_footer(): void {
		self::fillStudio();
		$html = self::render();

		$this->assertMatchesRegularExpression( '#^<div class="wp-block-template-part">#', trim( $html ) );
		$this->assertSame( 1, substr_count( $html, '<footer' ) );
		$this->assertSame( MarkupNormaliser::normalise( self::prototype() ), MarkupNormaliser::normalise( self::reduce( $html ) ) );
	}

	public function test_link_columns_carry_the_prototype_ids_labels_and_urls(): void {
		$html = self::render();

		$this->assertSame( 2, preg_match_all( '#<nav [^>]*class="wp-block-group fo-footer__col[ "]#', $html ) );
		$this->assertMatchesRegularExpression( '#<nav [^>]*aria-labelledby="footer-studio"#', $html );
		$this->assertMatchesRegularExpression( '#<nav [^>]*aria-labelledby="footer-book"#', $html );
		$this->assertStringContainsString( 'id="footer-studio"', $html );
		$this->assertStringContainsString( 'id="footer-book"', $html );
		foreach ( array( 'about', 'services', 'portfolio', 'book', 'account', 'contact' ) as $slug ) {
			$this->assertStringContainsString( 'href="/' . $slug . '/"', $html, $slug );
		}
		$this->assertStringContainsString( 'href="' . esc_url( home_url( '/' ) ) . '"', $html, 'logo links home' );
	}

	public function test_legal_paragraph_shows_the_current_year_from_the_bindings_source(): void {
		$html = self::render();
		$this->assertStringContainsString( '<p>© ' . wp_date( 'Y' ) . ' ' . self::LEGAL . '</p>', $html );
	}

	public function test_site_bindings_source_supplies_year_and_copyright(): void {
		$source = get_block_bindings_source( 'ishot-concept/site' );
		$this->assertNotNull( $source );
		$block = new WP_Block( array( 'blockName' => 'core/paragraph' ) );

		$this->assertSame( wp_date( 'Y' ), $source->get_value( array( 'key' => 'year' ), $block, 'content' ) );
		$this->assertSame( '© ' . wp_date( 'Y' ) . ' ' . self::LEGAL, $source->get_value( array( 'key' => 'copyright' ), $block, 'content' ) );
		$this->assertNull( $source->get_value( array( 'key' => 'nope' ), $block, 'content' ) );
		$this->assertNull( $source->get_value( array(), $block, 'content' ) );
	}

	public function test_site_bindings_source_is_registered_in_the_editor_too(): void {
		// WP 6.7 shows a server-only source's label ("iShot Concept site") in the editor; the client
		// source (getValues) shows the legal line. Built from blocks/theme-toggle (the legal band's block).
		$type    = WP_Block_Type_Registry::get_instance()->get_registered( 'ishot/theme-toggle' );
		$sources = array();
		foreach ( $type->editor_script_handles as $handle ) {
			$sources[] = (string) wp_scripts()->registered[ $handle ]->src;
		}
		$bindings = array_values( array_filter( $sources, static fn( $src ) => str_ends_with( $src, '/build/blocks/theme-toggle/site-bindings.js' ) ) );
		$this->assertCount( 1, $bindings, implode( ', ', $sources ) );

		$asset = require get_theme_file_path( 'build/blocks/theme-toggle/site-bindings.asset.php' );
		$this->assertContains( 'wp-blocks', $asset['dependencies'] );
		$built = (string) file_get_contents( get_theme_file_path( 'build/blocks/theme-toggle/site-bindings.js' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		$this->assertStringContainsString( 'ishot-concept/site', $built );
		$this->assertStringContainsString( self::LEGAL, $built );
	}

	public function test_theme_requires_wordpress_6_7(): void {
		// The footer filter uses WP_HTML_Processor::get_current_depth() (6.6) and 6.7 block bindings.
		$this->assertSame( '6.7', wp_get_theme()->get( 'RequiresWP' ) );
	}

	public function test_part_holds_only_valid_registered_blocks_that_round_trip(): void {
		$this->assertValidBlockMarkup( self::partFile(), 'parts/footer.html' );
	}

	public function test_part_uses_core_blocks_for_the_editable_link_columns(): void {
		$file = self::partFile();
		$this->assertSame( 2, substr_count( $file, '"tagName":"nav"' ) );
		$this->assertSame( 6, preg_match_all( '#<!-- wp:paragraph -->\s*<p><a href="/[a-z]+/">[^<]+</a></p>#', $file ) );
		$this->assertStringContainsString( '<!-- wp:ishot/contact-details {"variant":"footer"} /-->', $file );
		$this->assertStringContainsString( '<!-- wp:ishot/theme-toggle /-->', $file );
	}

	public function test_nav_label_filter_adds_aria_labelledby_from_the_eyebrow_id(): void {
		$html = '<nav class="wp-block-group fo-footer__col"><p id="footer-x" class="fo-eyebrow">X</p><p><a href="/a/">A</a></p></nav>';
		$this->assertSame(
			'<nav aria-labelledby="footer-x" class="wp-block-group fo-footer__col"><p id="footer-x" class="fo-eyebrow">X</p><p><a href="/a/">A</a></p></nav>',
			ishot_theme_render_group( $html )
		);
		// Not a footer column, or no eyebrow id: untouched.
		$other = '<nav class="wp-block-group"><p id="y" class="fo-eyebrow">Y</p><p><a href="/a/">A</a></p></nav>';
		$this->assertSame( $other, ishot_theme_render_group( $other ) );
		$plain = '<nav class="wp-block-group fo-footer__col"><p class="fo-eyebrow">Y</p><p><a href="/a/">A</a></p></nav>';
		$this->assertSame( $plain, ishot_theme_render_group( $plain ) );
		// An aria-labelledby set by the owner is kept.
		$own = '<nav aria-labelledby="mine" class="wp-block-group fo-footer__col"><p id="footer-x" class="fo-eyebrow">X</p><p><a href="/a/">A</a></p></nav>';
		$this->assertSame( $own, ishot_theme_render_group( $own ) );
	}

	public function test_empty_social_wrapper_and_eyebrow_only_columns_are_not_printed(): void {
		$this->assertSame( '', ishot_theme_render_group( "<div class=\"wp-block-group ish-social\">\n\n</div>" ) );
		$this->assertSame( '', ishot_theme_render_group( "<div class=\"wp-block-group fo-footer__col\">\n<p class=\"fo-eyebrow\">Visit <em>us</em></p>\n\n</div>" ) );
		$this->assertSame( '', ishot_theme_render_group( '<nav class="wp-block-group fo-footer__col"><p id="f" class="fo-eyebrow">Studio</p></nav>' ) );

		$social = '<div class="wp-block-group ish-social"><span class="fo-icon-btn"><svg viewBox="0 0 24 24"><path d="M0 0"/></svg></span></div>';
		$this->assertSame( $social, ishot_theme_render_group( $social ) );
		$visit = '<div class="wp-block-group fo-footer__col"><p class="fo-eyebrow">Visit</p><a href="mailto:a@b.co">a@b.co</a></div>';
		$this->assertSame( $visit, ishot_theme_render_group( $visit ) );
	}

	public function test_without_the_plugin_blocks_the_footer_prints_no_empty_wrappers(): void {
		$this->unregister( 'ishot/social-links' );
		$this->unregister( 'ishot/contact-details' );
		$html = self::render();

		$this->assertStringContainsString( 'ish-footer__brand', $html );
		$this->assertStringNotContainsString( 'ish-social', $html );
		$this->assertStringNotContainsString( '>Visit<', $html );
		$this->assertSame( 3, substr_count( $html, 'fo-footer__col' ), 'brand, Studio and Book' );
	}

	public function test_empty_studio_details_drop_the_visit_column_but_keep_the_inactive_icons(): void {
		$html = self::render();

		$this->assertStringNotContainsString( '>Visit<', $html );
		$this->assertSame( 3, substr_count( $html, 'ish-social--pending' ) );
	}

	public function test_markup_is_the_same_for_guests_and_logged_in_users(): void {
		self::fillStudio();
		$guest = self::render();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( $guest, self::render() );
	}

	public function test_templates_place_the_footer_part_after_main(): void {
		foreach ( (array) glob( get_theme_file_path( 'templates/*.html' ) ) as $file ) {
			$source = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
			$this->assertStringContainsString( '<!-- wp:template-part {"slug":"footer","tagName":"div","area":"footer"} /-->', $source, basename( $file ) );
			$html = do_blocks( $source );
			$this->assertGreaterThan( strpos( $html, '</main>' ), strpos( $html, '<footer class="wp-block-group fo-footer' ), basename( $file ) );
		}
	}

	public function test_theme_css_neutralises_the_core_wrappers_with_tokens_only(): void {
		$css = (string) file_get_contents( get_theme_file_path( 'assets/css/theme.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.

		$this->assertMatchesRegularExpression( '#\.fo-footer\.is-layout-flow > \*,\s*\.fo-footer \.is-layout-flow > \*\s*\{\s*margin-block: 0;\s*\}#', $css );
		$this->assertMatchesRegularExpression( '#\.fo-footer__col > p:not\(\[class\]\)\s*\{\s*display: contents;\s*\}#', $css );
	}
}
