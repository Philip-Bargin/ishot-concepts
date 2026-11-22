<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Content\Fields;
use IShot\Booking\Dev\DevGuard;
use IShot\Booking\Dev\PageShellFixtures;
use WP_UnitTestCase;

/**
 * The dev-only page shells: title-only placeholder pages for the prototype routes and one journal
 * post shell. Same layered guards as the Studio fixture (DevGuard), each of which alone blocks every write.
 */
final class PageShellFixturesTest extends WP_UnitTestCase {

	private const LOCAL = 'http://localhost:8888';

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * Reaches the private worker by reflection so each guard can be tested alone.
	 *
	 * @return array{ok: bool, reason: string, created: list<string>, skipped: list<string>, permalinks: string}
	 */
	private static function exec( string $env, mixed $opt_in, string $home ): array {
		$method = new \ReflectionMethod( PageShellFixtures::class, 'execute' );
		return $method->invoke( null, $env, $opt_in, $home );
	}

	/** Every page and post on the site with its status, title, content and meta, for before/after comparisons. */
	private static function snapshot(): string {
		$posts = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$rows = array_map( static fn( $p ) => array( $p->ID, $p->post_type, $p->post_name, $p->post_status, $p->post_title, $p->post_content, get_post_meta( $p->ID ) ), $posts );
		return serialize( array( $rows, get_option( 'permalink_structure' ) ) );
	}

	/** @return array<string, array{string, mixed, string}> */
	public static function singleFailures(): array {
		return array(
			'production environment' => array( 'production', true, self::LOCAL ),
			'staging environment'    => array( 'staging', true, self::LOCAL ),
			'opt-in missing'         => array( 'development', null, self::LOCAL ),
			'opt-in truthy not true' => array( 'development', 1, self::LOCAL ),
			'opt-in string true'     => array( 'local', 'true', self::LOCAL ),
			'public host'            => array( 'development', true, 'https://ishotconcept.com' ),
			'look-alike host'        => array( 'development', true, 'https://localhost.evil.example' ),
			'userinfo host'          => array( 'development', true, 'https://localhost@evil.example' ),
			'unparseable url'        => array( 'development', true, '' ),
		);
	}

	/** @dataProvider singleFailures */
	public function test_each_guard_alone_blocks_every_write( string $env, mixed $opt_in, string $home ): void {
		$before = self::snapshot();
		$result = self::exec( $env, $opt_in, $home );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( DevGuard::blocked( $env, $opt_in, $home ), $result['reason'], 'the shared guard decides' );
		$this->assertNotSame( '', $result['reason'] );
		$this->assertSame( array(), $result['created'] );
		$this->assertSame( $before, self::snapshot(), 'no page, post or permalink change' );
	}

	public function test_all_guards_passing_creates_published_title_only_placeholder_shells(): void {
		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array_merge( array_keys( PageShellFixtures::PAGES ), array( PageShellFixtures::POST['slug'] ) ), $result['created'] );
		foreach ( PageShellFixtures::PAGES as $slug => $title ) {
			$page = get_page_by_path( $slug );
			$this->assertNotNull( $page, $slug );
			$this->assertSame( 'publish', $page->post_status );
			$this->assertSame( $title, $page->post_title );
			$this->assertSame( '', $page->post_content, 'no business copy' );
			$this->assertSame( Fields::PLACEHOLDER, get_post_meta( $page->ID, 'content_status', true ), 'the sample notice shows' );
		}
		$posts = get_posts( array( 'name' => PageShellFixtures::POST['slug'] ) );
		$this->assertCount( 1, $posts );
		$this->assertSame( 'What to wear for your portrait session', $posts[0]->post_title );
		$this->assertSame( '', $posts[0]->post_content );
		$this->assertSame( Fields::PLACEHOLDER, get_post_meta( $posts[0]->ID, 'content_status', true ) );
	}

	public function test_the_shells_cover_the_prototype_page_routes_with_prototype_titles(): void {
		// Titles are the prototype's own (usePageTitle / navItems at 50e60f4); no route the booking plugin owns.
		$this->assertSame(
			array(
				'about'              => 'About',
				'services'           => 'Services',
				'portfolio'          => 'Portfolio',
				'creative-direction' => 'Creative direction',
				'journal'            => 'Journal',
				'contact'            => 'Contact',
				'book'               => 'Book a session',
				'account'            => 'My bookings',
			),
			PageShellFixtures::PAGES
		);
		$this->assertSame( 'what-to-wear-for-your-portrait-session', PageShellFixtures::POST['slug'] );
	}

	public function test_journal_post_shell_resolves_under_journal(): void {
		self::exec( 'development', true, self::LOCAL );

		$this->assertSame( PageShellFixtures::PERMALINKS, get_option( 'permalink_structure' ) );
		$post = get_posts( array( 'name' => PageShellFixtures::POST['slug'] ) )[0];
		$this->assertSame( home_url( '/journal/what-to-wear-for-your-portrait-session/' ), get_permalink( $post ) );
		$this->assertSame( home_url( '/about/' ), get_permalink( get_page_by_path( 'about' ) ), 'pages keep their top-level URLs' );
	}

	public function test_it_never_overwrites_existing_owner_content(): void {
		$about = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'about',
				'post_title'   => 'Our story',
				'post_content' => 'Owner copy.',
			)
		);
		$book  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'book',
				'post_status' => 'draft',
				'post_title'  => 'Book',
			)
		);
		$before_about = get_post( $about )->to_array();
		$before_book  = get_post( $book )->to_array();

		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array( 'about', 'book' ), $result['skipped'] );
		$this->assertNotContains( 'about', $result['created'] );
		$this->assertSame( $before_about, get_post( $about )->to_array() );
		$this->assertSame( $before_book, get_post( $book )->to_array() );
		$this->assertSame( '', get_post_meta( $about, 'content_status', true ), 'owner content is not marked placeholder' );
	}

	public function test_an_owner_permalink_structure_is_kept(): void {
		$this->set_permalink_structure( '/%year%/%postname%/' );
		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( '/%year%/%postname%/', get_option( 'permalink_structure' ) );
		$this->assertStringContainsString( 'kept', $result['permalinks'] );
	}

	public function test_a_second_run_changes_nothing(): void {
		self::exec( 'development', true, self::LOCAL );
		$before = self::snapshot();
		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array(), $result['created'] );
		$this->assertSame( $before, self::snapshot() );
	}

	public function test_the_only_public_entry_point_takes_no_guard_values(): void {
		$public = array_map( static fn( $m ) => $m->getName(), ( new \ReflectionClass( PageShellFixtures::class ) )->getMethods( \ReflectionMethod::IS_PUBLIC ) );
		$this->assertSame( array( 'run' ), $public );
		$this->assertSame( 0, ( new \ReflectionMethod( PageShellFixtures::class, 'run' ) )->getNumberOfParameters() );
	}

	public function test_run_reads_the_real_guards_and_refuses_on_the_tests_environment(): void {
		$before = self::snapshot();
		$result = PageShellFixtures::run();
		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( 'ISHOT_DEV_FIXTURES', $result['reason'] );
		$this->assertSame( $before, self::snapshot() );
	}

	public function test_both_dev_fixtures_share_one_guard(): void {
		// The Studio fixture's guard moved to DevGuard; neither fixture keeps a copy of its own.
		foreach ( array( PageShellFixtures::class, \IShot\Booking\Dev\StudioDevFixtures::class ) as $class ) {
			$this->assertFalse( method_exists( $class, 'blocked' ), $class );
			$this->assertFalse( method_exists( $class, 'isLocalHost' ), $class );
		}
		$this->assertSame( '', DevGuard::blocked( 'development', true, self::LOCAL ) );
	}
}
