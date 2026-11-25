<?php
namespace IShot\Blocks\Tests\Integration;

use IShot\Booking\Content\Fields;
use IShot\Booking\Content\Importer;
use IShot\Booking\Dev\DevGuard;
use IShot\Dev\ComponentFixtures;
use WP_UnitTestCase;

/**
 * The dev-only component fixture pages (Task 2.1a, ruling R-2-1): /ishot-fixtures/{component}/ pages for the
 * Stage 2 component visual gate. The class lives in wordpress/dev/lib (no plugin autoloads it); the tests
 * container maps wordpress/dev to wp-content/ishot-dev (Task 2.0), so the real file is loaded here.
 */
final class ComponentFixturesTest extends WP_UnitTestCase {

	private const LOCAL = 'http://localhost:8888';

	/** @var array<string, int> photo key => attachment id */
	private array $photos = array();

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		$file = WP_CONTENT_DIR . '/ishot-dev/lib/ComponentFixtures.php';
		if ( ! is_readable( $file ) ) {
			self::fail( "{$file} not found: wp-content/ishot-dev must be mapped in .wp-env.json env.tests.mappings." );
		}
		require_once $file;
	}

	public function set_up(): void {
		parent::set_up();
		foreach ( ComponentFixtures::photoKeys() as $key ) {
			$id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/33772.jpg' );
			update_post_meta( $id, Importer::KEY_META, 'photo:' . $key );
			$this->photos[ $key ] = $id;
		}
	}

	public function tear_down(): void {
		// The rollback removes the rows but not the uploaded files (about 2 MB per test otherwise piles up in uploads/).
		foreach ( $this->photos as $id ) {
			wp_delete_attachment( $id, true );
		}
		$this->photos = array();
		parent::tear_down();
	}

	/** @return array{ok: bool, reason: string, created: list<string>, skipped: list<string>} */
	private static function exec( string $env, mixed $opt_in, string $home ): array {
		return ( new \ReflectionMethod( ComponentFixtures::class, 'execute' ) )->invoke( null, $env, $opt_in, $home );
	}

	/** @return array{ok: bool, reason: string, removed: list<string>, kept: list<string>} */
	private static function execRemove( string $env, mixed $opt_in, string $home ): array {
		return ( new \ReflectionMethod( ComponentFixtures::class, 'executeRemove' ) )->invoke( null, $env, $opt_in, $home );
	}

	/** Every page and post with status, parent, content and meta, for before/after comparisons. */
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
		return serialize( array_map( static fn( $p ) => array( $p->ID, $p->post_name, $p->post_parent, $p->post_status, $p->post_content, get_post_meta( $p->ID ) ), $posts ) );
	}

	private static function page( string $path ): ?\WP_Post {
		return get_page_by_path( $path, OBJECT, 'page' );
	}

	/** @return array<string, array{string, mixed, string}> */
	public static function singleFailures(): array {
		return array(
			'production environment' => array( 'production', true, self::LOCAL ),
			'staging environment'    => array( 'staging', true, self::LOCAL ),
			'opt-in missing'         => array( 'development', null, self::LOCAL ),
			'opt-in truthy not true' => array( 'development', 1, self::LOCAL ),
			'public host'            => array( 'development', true, 'https://ishotconcept.com' ),
			'look-alike host'        => array( 'development', true, 'https://localhost.evil.example' ),
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
		$this->assertSame( $before, self::snapshot() );
	}

	/** @dataProvider singleFailures */
	public function test_each_guard_alone_blocks_remove( string $env, mixed $opt_in, string $home ): void {
		self::exec( 'development', true, self::LOCAL );
		$before = self::snapshot();
		$result = self::execRemove( $env, $opt_in, $home );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( array(), $result['removed'] );
		$this->assertSame( $before, self::snapshot() );
	}

	public function test_a_pass_creates_the_parent_and_one_page_per_component_marked_and_placeholder(): void {
		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'], $result['reason'] );
		$this->assertSame( array_merge( array( ComponentFixtures::PARENT ), array_keys( ComponentFixtures::PAGES ) ), $result['created'] );
		$parent = self::page( ComponentFixtures::PARENT );
		$this->assertNotNull( $parent );
		foreach ( array_keys( ComponentFixtures::PAGES ) as $slug ) {
			$page = self::page( ComponentFixtures::PARENT . '/' . $slug );
			$this->assertNotNull( $page, $slug );
			$this->assertSame( $parent->ID, $page->post_parent );
		}
		foreach ( array_merge( array( $parent ), array_map( static fn( $s ) => self::page( ComponentFixtures::PARENT . '/' . $s ), array_keys( ComponentFixtures::PAGES ) ) ) as $post ) {
			$this->assertSame( 'publish', $post->post_status, $post->post_name );
			$this->assertSame( Fields::PLACEHOLDER, get_post_meta( $post->ID, 'content_status', true ), $post->post_name );
			$this->assertSame( '1', get_post_meta( $post->ID, ComponentFixtures::MARKER, true ), $post->post_name );
		}
	}

	public function test_the_strip_page_is_a_strip_gallery_of_the_five_prototype_photos_by_importer_key(): void {
		self::exec( 'development', true, self::LOCAL );
		$content = self::page( ComponentFixtures::PARENT . '/strip' )->post_content;

		$flatten = static function ( array $blocks ) use ( &$flatten ): array {
			$all = array();
			foreach ( $blocks as $b ) {
				$all = array_merge( $all, array( $b ), $flatten( $b['innerBlocks'] ) );
			}
			return $all;
		};
		$all = $flatten( parse_blocks( $content ) );
		$this->assertSame( array( 'core/group', 'core/group', 'core/gallery' ), array_slice( array_column( $all, 'blockName' ), 0, 3 ), 'section > container > gallery' );
		$gallery = array_values( array_filter( $all, static fn( $b ) => 'core/gallery' === $b['blockName'] ) );
		$this->assertCount( 1, $gallery );
		$this->assertSame( 'is-style-strip', $gallery[0]['attrs']['className'] );
		$ids = array_map( static fn( $b ) => $b['attrs']['id'], $gallery[0]['innerBlocks'] );
		$order = array( 'denimSmile', 'burgundyLounge', 'graduate', 'redFurSofa', 'gownStair' );
		$this->assertSame( array_map( fn( $k ) => $this->photos[ $k ], $order ), $ids, 'Home.jsx:17-23 order' );
		foreach ( $gallery[0]['innerBlocks'] as $image ) {
			$this->assertSame( 'core/image', $image['blockName'] );
			$this->assertStringContainsString( 'alt=""', $image['innerHTML'], 'decorative, as the prototype' );
			$this->assertStringContainsString( 'wp-image-' . $image['attrs']['id'], $image['innerHTML'] );
		}
		$this->assertStringNotContainsString( '{{', $content, 'every token resolved' );
	}

	public function test_a_second_run_creates_nothing_and_changes_nothing(): void {
		self::exec( 'development', true, self::LOCAL );
		$before = self::snapshot();
		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array(), $result['created'] );
		$this->assertSame( array_merge( array( ComponentFixtures::PARENT ), array_keys( ComponentFixtures::PAGES ) ), $result['skipped'] );
		$this->assertSame( $before, self::snapshot() );
	}

	public function test_owner_content_with_the_parent_slug_is_left_alone_and_nothing_is_created_under_it(): void {
		$owner = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => ComponentFixtures::PARENT,
				'post_status'  => 'draft',
				'post_content' => 'The owner\'s own page.',
			)
		);
		$before = self::snapshot();
		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array(), $result['created'] );
		$this->assertContains( ComponentFixtures::PARENT, $result['skipped'] );
		$this->assertSame( $before, self::snapshot() );
		$this->assertSame( '', get_post_meta( $owner, ComponentFixtures::MARKER, true ) );
	}

	public function test_owner_content_with_a_component_slug_under_the_parent_is_left_alone(): void {
		self::exec( 'development', true, self::LOCAL );
		$strip = self::page( ComponentFixtures::PARENT . '/strip' );
		wp_delete_post( $strip->ID, true );
		$owner = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_name'    => 'strip',
				'post_parent'  => self::page( ComponentFixtures::PARENT )->ID,
				'post_status'  => 'publish',
				'post_content' => 'Owner text.',
			)
		);
		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertContains( 'strip', $result['skipped'] );
		$this->assertNotContains( 'strip', $result['created'] );
		$this->assertSame( 'Owner text.', get_post( $owner )->post_content );
		$this->assertSame( '', get_post_meta( $owner, ComponentFixtures::MARKER, true ) );
	}

	public function test_a_missing_photo_skips_that_page_with_the_reason_and_creates_the_rest(): void {
		delete_post_meta( $this->photos['graduate'], Importer::KEY_META );
		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'] );
		$this->assertNotContains( 'strip', $result['created'] );
		$this->assertContains( 'strip (photo:graduate not imported; run npm run wp:import)', $result['skipped'] );
		$this->assertNull( self::page( ComponentFixtures::PARENT . '/strip' ) );
	}

	public function test_remove_deletes_only_marked_items(): void {
		self::exec( 'development', true, self::LOCAL );
		$parent = self::page( ComponentFixtures::PARENT );
		$elsewhere = self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'about' ) );

		$result = self::execRemove( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'] );
		$this->assertEqualsCanonicalizing( array_merge( array( ComponentFixtures::PARENT ), array_keys( ComponentFixtures::PAGES ) ), $result['removed'] );
		foreach ( array_keys( ComponentFixtures::PAGES ) as $slug ) {
			$this->assertNull( self::page( ComponentFixtures::PARENT . '/' . $slug ), $slug );
		}
		$this->assertNull( get_post( $parent->ID ) );
		$this->assertSame( array(), $result['kept'] );
		$this->assertNotNull( get_post( $elsewhere ) );
		$this->assertSame( array(), self::execRemove( 'development', true, self::LOCAL )['removed'], 'idempotent' );
	}

	/** @return array<string, array{0: string}> every status an owner child may have */
	public static function owner_child_statuses(): array {
		return array(
			'publish' => array( 'publish' ),
			'draft'   => array( 'draft' ),
			'private' => array( 'private' ),
			'trash'   => array( 'trash' ),
		);
	}

	/** @dataProvider owner_child_statuses */
	public function test_remove_keeps_the_parent_while_an_owner_child_exists_and_never_reparents_it( string $status ): void {
		self::exec( 'development', true, self::LOCAL );
		$parent = self::page( ComponentFixtures::PARENT );
		$owner_child = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'owner-notes',
				'post_parent' => $parent->ID,
				'post_status' => $status,
			)
		);
		$before = get_post( $owner_child );
		$path   = 'trash' === $status ? '' : wp_parse_url( get_permalink( $owner_child ), PHP_URL_PATH );

		$result = self::execRemove( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'] );
		$this->assertEqualsCanonicalizing( array_keys( ComponentFixtures::PAGES ), $result['removed'] );
		$this->assertCount( 1, $result['kept'] );
		$this->assertStringContainsString( ComponentFixtures::PARENT, $result['kept'][0] );
		$this->assertStringContainsString( 'owner', $result['kept'][0] );
		$this->assertNotNull( get_post( $parent->ID ), 'the parent is kept' );
		$this->assertSame( '1', get_post_meta( $parent->ID, ComponentFixtures::MARKER, true ) );
		$after = get_post( $owner_child );
		$this->assertSame( $parent->ID, (int) $after->post_parent, 'the owner child is not reparented' );
		$this->assertSame( $before->post_name, $after->post_name );
		$this->assertSame( $status, $after->post_status );
		if ( '' !== $path ) {
			$this->assertSame( $path, wp_parse_url( get_permalink( $owner_child ), PHP_URL_PATH ), 'the owner permalink path is unchanged' );
		}

		$again = self::execRemove( 'development', true, self::LOCAL );
		$this->assertSame( array(), $again['removed'], 'idempotent' );
		$this->assertCount( 1, $again['kept'], 'still kept' );
		$this->assertSame( $parent->ID, (int) get_post( $owner_child )->post_parent );

		wp_delete_post( $owner_child, true );
		$last = self::execRemove( 'development', true, self::LOCAL );
		$this->assertSame( array( ComponentFixtures::PARENT ), $last['removed'], 'once the owner child is gone the parent goes' );
		$this->assertSame( array(), $last['kept'] );
	}

	public function test_a_child_that_cannot_be_created_is_reported_as_skipped_with_a_reason(): void {
		$block = static fn( bool $empty, array $postarr ): bool => 'strip' === ( $postarr['post_name'] ?? '' ) ? true : $empty;
		add_filter( 'wp_insert_post_empty_content', $block, 10, 2 );
		$result = self::exec( 'development', true, self::LOCAL );
		remove_filter( 'wp_insert_post_empty_content', $block, 10 );

		$this->assertNotContains( 'strip', $result['created'] );
		$this->assertContains( 'strip (could not be created)', $result['skipped'] );
		$this->assertNull( self::page( ComponentFixtures::PARENT . '/strip' ) );
	}

	public function test_a_trashed_fixture_parent_is_restored_before_children_are_created(): void {
		self::exec( 'development', true, self::LOCAL );
		$parent = self::page( ComponentFixtures::PARENT );
		wp_delete_post( self::page( ComponentFixtures::PARENT . '/strip' )->ID, true );
		wp_trash_post( $parent->ID );
		$this->assertSame( 'trash', get_post_status( $parent->ID ) );

		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertSame( 'publish', get_post_status( $parent->ID ), 'our own parent is restored' );
		$this->assertContains( 'strip', $result['created'] );
		$this->assertSame( 'publish', self::page( ComponentFixtures::PARENT . '/strip' )->post_status );
	}

	public function test_a_trashed_owner_page_with_the_parent_slug_is_still_owner_content(): void {
		$owner = self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => ComponentFixtures::PARENT, 'post_status' => 'publish' ) );
		wp_trash_post( $owner );
		$result = self::exec( 'development', true, self::LOCAL );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'trash', get_post_status( $owner ), 'the owner page stays in the trash' );
		$this->assertSame( '', get_post_meta( $owner, ComponentFixtures::MARKER, true ) );
		$this->assertSame( ComponentFixtures::PARENT . '__trashed', get_post( $owner )->post_name );
	}

	/** @return array<string, array{list<string>, string, bool}> */
	public static function arguments(): array {
		return array(
			'none creates'        => array( array(), 'run', true ),
			'remove removes'      => array( array( 'remove' ), 'remove', true ),
			'--remove is refused' => array( array( '--remove' ), '', false ),
			'removed is refused'  => array( array( 'removed' ), '', false ),
			'extra is refused'    => array( array( 'remove', 'now' ), '', false ),
		);
	}

	/**
	 * @dataProvider arguments
	 * @param list<string> $args Positional arguments as wp eval-file passes them.
	 */
	public function test_only_no_argument_or_remove_is_accepted( array $args, string $command, bool $ok ): void {
		$parsed = ComponentFixtures::command( $args );
		$this->assertSame( $command, $parsed['command'] );
		$this->assertSame( $ok, '' === $parsed['error'] );
		if ( ! $ok ) {
			$this->assertStringContainsString( 'remove', $parsed['error'] );
		}
	}

	public function test_run_and_remove_read_the_guard_inputs_themselves(): void {
		// The tests site is not opted in (ISHOT_DEV_FIXTURES is defined in env.development only).
		$before = self::snapshot();
		$this->assertFalse( ComponentFixtures::run()['ok'] );
		$this->assertFalse( ComponentFixtures::remove()['ok'] );
		$this->assertSame( $before, self::snapshot() );
	}
}
