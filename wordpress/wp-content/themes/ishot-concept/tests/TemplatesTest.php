<?php
/**
 * The base templates (index, page, single, 404, search), the `page-title` pattern and the document
 * title. Reference: src/App.jsx (App, NotFound) and src/components/ui.jsx (PageTitle) at 50e60f4.
 *
 * @package IShot\Theme
 */

use IShot\Blocks\Tests\Support\MarkupNormaliser;
use IShot\Blocks\Tests\Support\ValidBlockMarkup;
use IShot\Booking\Content\Fields;

/** Every template is header part + main#main[tabindex=-1] + footer part inside .fo-root.ish-app. */
final class TemplatesTest extends WP_UnitTestCase {
	use ValidBlockMarkup;

	private const TEMPLATES = array( '404', 'index', 'page', 'search', 'single' );

	private const LEAD = 'The page you\'re looking for has moved or never existed.';

	/** @var int|null HTTP status sent through status_header() during the last request. */
	private ?int $status = null;

	public function set_up(): void {
		parent::set_up();
		update_option( 'blogname', 'iShot Concept' );
		$this->set_permalink_structure( '/%postname%/' );
		add_filter( 'status_header', array( $this, 'record_status' ), 10, 2 );
	}

	public function tear_down(): void {
		remove_filter( 'status_header', array( $this, 'record_status' ), 10 );
		unset( $GLOBALS['_wp_current_template_id'], $GLOBALS['_wp_current_template_content'] );
		parent::tear_down();
	}

	/** @internal status_header filter. */
	public function record_status( string $header, int $code ): string {
		$this->status = $code;
		return $header;
	}

	private static function source( string $slug ): string {
		return (string) file_get_contents( get_theme_file_path( "templates/{$slug}.html" ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
	}

	/**
	 * Requests $url, lets the block template loader pick the template as on the front end, and
	 * returns the template HTML (get_the_block_template_html(), which wraps it in .wp-site-blocks).
	 */
	private function request( string $url ): string {
		$this->status = null;
		$this->go_to( $url );
		if ( is_404() ) {
			get_404_template();
		} elseif ( is_search() ) {
			get_search_template();
		} elseif ( is_page() ) {
			get_page_template();
		} elseif ( is_single() ) {
			get_single_template();
		} else {
			get_index_template();
		}
		return (string) get_the_block_template_html();
	}

	private static function dom( string $html ): DOMXPath {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();
		return new DOMXPath( $doc );
	}

	/** The first element matching $root with WordPress-only classes (wp-*, is-layout-*) and whitespace between tags removed. */
	private static function reduced( string $html, string $root ): string {
		$xpath = self::dom( $html );
		foreach ( iterator_to_array( $xpath->query( '//text()[normalize-space(.) = ""]' ) ) as $node ) {
			$node->parentNode->removeChild( $node );
		}
		foreach ( iterator_to_array( $xpath->query( '//*[@class]' ) ) as $el ) {
			$keep = array_filter( preg_split( '/\s+/', $el->getAttribute( 'class' ) ), static fn( $c ) => '' !== $c && ! str_starts_with( $c, 'wp-' ) && ! str_starts_with( $c, 'is-layout-' ) );
			if ( array() === $keep ) {
				$el->removeAttribute( 'class' );
			} else {
				$el->setAttribute( 'class', implode( ' ', $keep ) );
			}
		}
		$el = $xpath->query( $root )->item( 0 );
		return $el ? (string) $el->ownerDocument->saveHTML( $el ) : '';
	}

	public function test_the_theme_ships_the_five_base_templates(): void {
		$files = array_map( static fn( $f ) => basename( $f, '.html' ), (array) glob( get_theme_file_path( 'templates/*.html' ) ) );
		sort( $files );
		$this->assertSame( self::TEMPLATES, $files );
		foreach ( self::TEMPLATES as $slug ) {
			$this->assertNotNull( get_block_template( get_stylesheet() . '//' . $slug ), $slug );
		}
	}

	public function test_each_template_parses_into_registered_blocks_that_save_as_written(): void {
		foreach ( self::TEMPLATES as $slug ) {
			$this->assertValidBlockMarkup( self::source( $slug ), "templates/{$slug}.html" );
		}
	}

	public function test_page_title_pattern_holds_valid_blocks(): void {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'ishot-concept/page-title' );
		$this->assertIsArray( $pattern );
		$this->assertValidBlockMarkup( $pattern['content'], 'patterns/page-title.php' );
	}

	/** @return array<string, array{string}> */
	public static function frames(): array {
		return array_combine( self::TEMPLATES, array_map( static fn( $t ) => array( $t ), self::TEMPLATES ) );
	}

	/** @dataProvider frames */
	public function test_main_is_the_direct_focusable_child_of_the_app_wrapper_with_no_layout_geometry( string $slug ): void {
		$html  = do_blocks( self::source( $slug ) );
		$xpath = self::dom( $html );

		$root = $xpath->query( '/html/body/div' )->item( 0 );
		$this->assertInstanceOf( DOMElement::class, $root );
		$this->assertSame( 'wp-block-group fo-root ish-app', preg_replace( '/\s+(?:wp-container-|is-layout-|wp-block-group-is-layout-)\S+/', '', $root->getAttribute( 'class' ) ) );

		$children = array_values( array_filter( iterator_to_array( $root->childNodes ), static fn( $n ) => $n instanceof DOMElement ) );
		$this->assertSame( array( 'div', 'main', 'div' ), array_map( static fn( $n ) => $n->tagName, $children ), 'header part, main, footer part' );
		$this->assertStringContainsString( 'wp-block-template-part', $children[0]->getAttribute( 'class' ) );
		$this->assertStringContainsString( 'wp-block-template-part', $children[2]->getAttribute( 'class' ) );

		$main = $children[1];
		$this->assertSame( 'main', $main->getAttribute( 'id' ) );
		$this->assertSame( '-1', $main->getAttribute( 'tabindex' ) );
		$this->assertSame( '', $main->getAttribute( 'style' ), 'no inline padding or gap' );
		$this->assertSame( 'wp-block-group is-layout-flow wp-block-group-is-layout-flow', $main->getAttribute( 'class' ), 'default (flow) layout only: no constrained layout, root padding or generated container rules' );
		$this->assertSame( 1, $xpath->query( '//*[@id="main"]' )->length, 'one skip-link target' );
	}

	public function test_tabindex_is_added_to_the_main_landmark_only(): void {
		$this->assertSame( '<main tabindex="-1" id="main" class="wp-block-group"><p>x</p></main>', ishot_theme_render_group( '<main id="main" class="wp-block-group"><p>x</p></main>' ) );
		// A value the owner set is kept; another main or a div with id="main" is left alone.
		$own = '<main id="main" tabindex="0" class="wp-block-group"></main>';
		$this->assertSame( $own, ishot_theme_render_group( $own ) );
		$other = '<main id="content" class="wp-block-group"></main>';
		$this->assertSame( $other, ishot_theme_render_group( $other ) );
		$div = '<div id="main" class="wp-block-group"></div>';
		$this->assertSame( $div, ishot_theme_render_group( $div ) );
		$this->assertSame( 10, has_filter( 'render_block_core/group', 'ishot_theme_render_group' ) );
	}

	/**
	 * The prototype's NotFound inside App's main (src/App.jsx), as React renders it, with the two
	 * accepted markup exceptions (docs/phase-3b-3c-progress.md, Task 1.4): each button sits in a
	 * class-less paragraph (an editable core Paragraph; theme.css gives it display: contents), and the
	 * gallery link has WordPress's trailing slash (`/portfolio/`; the prototype's Link renders `/portfolio`).
	 */
	private static function notFound(): string {
		return '<main id="main" tabindex="-1">'
			. '<section class="fo-section ish-title"><div class="fo-container ish-stack"><p class="fo-eyebrow">404</p><h1 class="fo-h1">Page not found</h1><p class="lead fo-muted ish-measure">' . self::LEAD . '</p></div></section>'
			. '<section class="fo-section"><div class="fo-container ish-actions"><p><a class="fo-btn fo-btn--primary" href="/">Go home</a></p><p><a class="fo-btn fo-btn--outline" href="/portfolio/">View gallery</a></p></div></section>'
			. '</main>';
	}

	public function test_404_returns_status_404_and_the_prototypes_not_found_markup(): void {
		$html = $this->request( '/this-page-does-not-exist/' );

		$this->assertTrue( is_404() );
		$this->assertSame( 404, $this->status );
		$this->assertSame( get_stylesheet() . '//404', $GLOBALS['_wp_current_template_id'] );
		$main = self::reduced( $html, '//main' );
		$this->assertSame( MarkupNormaliser::normalise( self::notFound() ), MarkupNormaliser::normalise( $main ) );
		// The normaliser ignores these; they matter for the skip link and the buttons.
		$this->assertMatchesRegularExpression( '#^<main (?=[^>]*id="main")(?=[^>]*tabindex="-1")#', $main );
		// Exact button markup, hrefs included (the normaliser ignores href).
		$this->assertStringContainsString( '<p><a class="fo-btn fo-btn--primary" href="/">Go home</a></p>', $html );
		$this->assertStringContainsString( '<p><a class="fo-btn fo-btn--outline" href="/portfolio/">View gallery</a></p>', $html );
		$this->assertStringContainsString( ">The page you&#39;re looking for has moved or never existed.</p>", $html, 'straight apostrophe, as in the source (not texturized)' );
	}

	public function test_document_title_uses_the_prototypes_separator(): void {
		$this->assertSame( '·', apply_filters( 'document_title_separator', '-' ) );
		$this->request( '/this-page-does-not-exist/' );
		$this->assertSame( 'Page not found · iShot Concept', wp_get_document_title() );
	}

	private function page( string $status ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'About',
				'post_name'    => 'about',
				'post_content' => '<!-- wp:paragraph --><p>Body copy.</p><!-- /wp:paragraph -->',
			)
		);
		update_post_meta( $id, 'content_status', $status );
		return $id;
	}

	/**
	 * The title block's stack children as "tag.class|text" (classes without wp-* / is-layout-*).
	 *
	 * @return list<string>
	 */
	private static function titleStack( string $html ): array {
		$xpath = self::dom( self::reduced( $html, '//main' ) );
		$out   = array();
		foreach ( $xpath->query( '//main/*[contains(concat(" ", @class, " "), " ish-title ")]/div/*' ) as $el ) {
			$out[] = $el->tagName . '.' . str_replace( ' ', '.', $el->getAttribute( 'class' ) ) . '|' . trim( preg_replace( '/\s+/', ' ', $el->textContent ) );
		}
		return $out;
	}

	public function test_page_uses_the_page_title_pattern_bound_to_the_post_title_then_the_content(): void {
		$id   = $this->page( Fields::APPROVED );
		$html = $this->request( get_permalink( $id ) );

		$this->assertSame( get_stylesheet() . '//page', $GLOBALS['_wp_current_template_id'] );
		// PageTitle with the post title; the template's empty eyebrow and lead are not printed, and
		// approved content prints no notice section at all.
		$this->assertSame( array( 'h1.fo-h1|About' ), self::titleStack( $html ) );
		$main = self::reduced( $html, '//main' );
		$this->assertMatchesRegularExpression( '#^<main[^>]*><section class="fo-section ish-title"><div class="fo-container ish-stack"><h1 class="fo-h1">About</h1></div></section><div class="entry-content"><p>Body copy.</p></div></main>$#', $main );
	}

	public function test_page_shows_the_sample_notice_in_its_own_section_after_the_title(): void {
		$id   = $this->page( Fields::PLACEHOLDER );
		$html = $this->request( get_permalink( $id ) );

		// PageTitle stays exactly PageTitle; the notice follows in the next section, as on the
		// prototype's Journal, Account and ManageBooking pages.
		$this->assertSame( array( 'h1.fo-h1|About' ), self::titleStack( $html ) );
		$main = self::reduced( $html, '//main' );
		$this->assertMatchesRegularExpression( '#^<main[^>]*><section class="fo-section ish-title">.*?</section><section class="fo-section"><div class="fo-container"><div class="fo-alert ish-alert--info ish-measure"><svg .*?</svg><p><strong>Sample content\.</strong> ?</p></div></div></section><div class="entry-content"><p>Body copy\.</p></div></main>$#s', $main );
	}

	public function test_a_group_holding_only_an_empty_notice_is_not_printed(): void {
		$section = parse_blocks( '<!-- wp:group {"tagName":"section","className":"fo-section"} --><section class="wp-block-group fo-section"><!-- wp:group {"className":"fo-container"} --><div class="wp-block-group fo-container"><!-- wp:ishot/notice /--></div><!-- /wp:group --></section><!-- /wp:group -->' )[0];
		$inner   = $section['innerBlocks'][0];

		$this->assertSame( '', ishot_theme_render_group( "<div class=\"wp-block-group fo-container\">\n</div>", $inner ) );
		$this->assertSame( '', ishot_theme_render_group( "<section class=\"wp-block-group fo-section\">\n\n</section>", $section ) );
		$shown = '<section class="wp-block-group fo-section"><div class="wp-block-group fo-container"><div class="fo-alert"><p><strong>Sample content.</strong> </p></div></div></section>';
		$this->assertSame( $shown, ishot_theme_render_group( $shown, $section ) );
		// Any other empty group is the owner's business.
		$other = parse_blocks( '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p></p><!-- /wp:paragraph --></div><!-- /wp:group -->' )[0];
		$this->assertSame( '<div class="wp-block-group"><p></p></div>', ishot_theme_render_group( '<div class="wp-block-group"><p></p></div>', $other ) );
	}

	public function test_single_shows_the_notice_the_title_and_the_content(): void {
		$id = self::factory()->post->create(
			array(
				'post_title'   => 'What to wear for your portrait session',
				'post_content' => '<!-- wp:paragraph --><p>Body copy.</p><!-- /wp:paragraph -->',
			)
		);
		$html = $this->request( get_permalink( $id ) );
		$this->assertSame( get_stylesheet() . '//single', $GLOBALS['_wp_current_template_id'] );
		$this->assertSame( array( 'h1.fo-h1|What to wear for your portrait session' ), self::titleStack( $html ) );
		$this->assertStringContainsString( '<p>Body copy.</p>', $html );

		update_post_meta( $id, 'content_status', Fields::PLACEHOLDER );
		$html = $this->request( get_permalink( $id ) );
		$this->assertSame( array( 'div.fo-alert.ish-alert--info.ish-measure|Sample content.', 'h1.fo-h1|What to wear for your portrait session' ), self::titleStack( $html ), 'notice first in the article header, as in the prototype Article' );
	}

	public function test_search_shows_the_query_title_and_a_result_list(): void {
		self::factory()->post->create( array( 'post_title' => 'Portrait tips' ) );
		$html = $this->request( '/?s=portrait' );

		$this->assertSame( get_stylesheet() . '//search', $GLOBALS['_wp_current_template_id'] );
		$this->assertMatchesRegularExpression( '#<h1 class="[^"]*wp-block-query-title[^"]*fo-h1|<h1 class="[^"]*fo-h1[^"]*wp-block-query-title#', $html );
		$this->assertStringContainsString( 'Portrait tips', $html );
		$this->assertStringContainsString( 'wp-block-post-template', $html );
	}

	public function test_page_title_pattern_is_exactly_page_title_and_not_in_the_inserter(): void {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'ishot-concept/page-title' );
		$this->assertStringNotContainsString( 'ishot/notice', $pattern['content'] );
		$this->assertFalse( $pattern['inserter'] );
	}

	public function test_page_title_pattern_matches_the_prototype_page_title(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'Our services' ) );
		$this->go_to( get_permalink( $id ) );
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'ishot-concept/page-title' );
		// The owner fills the eyebrow and lead in the editor (src/pages/Services.jsx).
		$content = str_replace(
			array( '<p class="fo-eyebrow"></p>', '<p class="lead fo-muted ish-measure"></p>' ),
			array( '<p class="fo-eyebrow">What we do</p>', '<p class="lead fo-muted ish-measure">Six ways we tell your story, each one planned around you.</p>' ),
			$pattern['content']
		);
		$html = '';
		while ( have_posts() ) {
			the_post();
			$html = do_blocks( $content );
		}
		$prototype = '<section class="fo-section ish-title"><div class="fo-container ish-stack"><p class="fo-eyebrow">What we do</p><h1 class="fo-h1">Our services</h1><p class="lead fo-muted ish-measure">Six ways we tell your story, each one planned around you.</p></div></section>';
		$this->assertSame( MarkupNormaliser::normalise( $prototype ), MarkupNormaliser::normalise( self::reduced( $html, '//section' ) ) );
	}

	public function test_empty_eyebrow_and_lead_paragraphs_are_not_printed(): void {
		$this->assertSame( '', ishot_theme_render_title_paragraph( '<p class="fo-eyebrow"></p>', array( 'attrs' => array( 'className' => 'fo-eyebrow' ) ) ) );
		$this->assertSame( '', ishot_theme_render_title_paragraph( "<p class=\"lead fo-muted ish-measure\"> \n</p>", array( 'attrs' => array( 'className' => 'lead fo-muted ish-measure' ) ) ) );
		$filled = '<p class="fo-eyebrow">404</p>';
		$this->assertSame( $filled, ishot_theme_render_title_paragraph( $filled, array( 'attrs' => array( 'className' => 'fo-eyebrow' ) ) ) );
		// Any other empty paragraph is the owner's business.
		$plain = '<p></p>';
		$this->assertSame( $plain, ishot_theme_render_title_paragraph( $plain, array( 'attrs' => array() ) ) );
	}

	public function test_theme_css_unwraps_the_action_paragraphs(): void {
		$css = (string) file_get_contents( get_theme_file_path( 'assets/css/theme.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		$this->assertMatchesRegularExpression( '#\.ish-actions > p:not\(\[class\]\)\s*\{\s*display: contents;\s*\}#', $css );
		// No high-specificity margin reset in main: it beat the prototype's own margins (Task 1.4 review I3).
		$this->assertStringNotContainsString( '.ish-app > main', $css );
	}

	/**
	 * Core's flow layout adds a block gap between children, and its first/last-child rules (specificity
	 * 0,2,0) beat the design system's own margins (`.ish-strip`, `.fo-heading`, …). The design system
	 * spaces everything with its own classes, so the theme opts out of core's block gap styles
	 * (`settings.spacing.blockGap: null`): core prints no flow margin rule at all (Task 1.4 review I3).
	 */
	public function test_core_prints_no_flow_layout_margins(): void {
		$this->assertNull( wp_get_global_settings( array( 'spacing', 'blockGap' ) ) );
		$css = wp_get_global_stylesheet();
		$this->assertStringNotContainsString( 'is-layout-flow) > :first-child', $css );
		$this->assertStringNotContainsString( 'is-layout-flow) > *{margin-block-start', $css );
		// Flex and grid layouts keep core's fallback gap (owner rows and buttons still lay out).
		$this->assertStringContainsString( 'is-layout-flex', $css );

		// No per-block gap rule either, even for a Group asking for one.
		$html = do_blocks( '<!-- wp:group {"style":{"spacing":{"blockGap":"2rem"}},"layout":{"type":"default"}} --><div class="wp-block-group"><!-- wp:paragraph --><p>a</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );
		$this->assertStringNotContainsString( 'wp-container-', $html );
	}

	public function test_templates_carry_no_block_gap_attributes(): void {
		foreach ( self::TEMPLATES as $slug ) {
			$this->assertStringNotContainsString( 'blockGap', self::source( $slug ), $slug );
		}
	}
}
