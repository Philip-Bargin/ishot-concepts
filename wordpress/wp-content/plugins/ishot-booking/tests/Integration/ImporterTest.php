<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Content\ContentTypes;
use IShot\Booking\Content\Fields;
use IShot\Booking\Content\Importer;
use WP_UnitTestCase;

/** The prototype import: creates once, updates only untouched items, never duplicates, never clobbers edits. */
final class ImporterTest extends WP_UnitTestCase {

	private string $dir;

	public function set_up(): void {
		parent::set_up();
		// The test harness unregisters custom types (and their meta) between tests.
		ServiceCatalog::register();
		ContentTypes::register();
		$this->dir = get_temp_dir() . 'ishot-seed-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->dir );
		foreach ( array( 'hero', 'portrait' ) as $i => $name ) {
			$img = imagecreatetruecolor( 40, 50 );
			imagefill( $img, 0, 0, imagecolorallocate( $img, 20 * $i, 40, 90 ) );
			imagejpeg( $img, "{$this->dir}/{$name}-2400.jpg" );
		}
	}

	public function tear_down(): void {
		foreach ( glob( "{$this->dir}/*" ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->dir );
		parent::tear_down();
	}

	/** @return array<string, mixed> */
	private static function seed(): array {
		return array(
			'categories' => array(
				array( 'slug' => 'portraits', 'label' => 'Portraits', 'blurb' => 'Personality-rich portraits.' ),
				array( 'slug' => 'creative', 'label' => 'Creative concepts', 'blurb' => 'One strong idea.' ),
			),
			'photos'     => array(
				array( 'key' => 'phoneBirthday', 'image' => 'hero-2400.jpg', 'alt' => 'A gloved hand holds up a phone showing a birthday cake', 'cat' => 'creative', 'focus' => '50% 45%' ),
				array( 'key' => 'redFurSofa', 'image' => 'portrait-2400.jpg', 'alt' => 'Woman in a red faux-fur coat on a velvet sofa', 'cat' => 'portraits', 'focus' => '45% 30%' ),
			),
			'portfolio'  => array( 'redFurSofa', 'phoneBirthday' ),
			'services'   => array(
				array( 'key' => 's1', 'slug' => 'birthday-portrait', 'title' => 'Birthday & portrait photography', 'short' => 'Birthday & portrait', 'tag' => 'Portraits', 'cat' => 'portraits', 'icon' => 'camera', 'photo' => 'redFurSofa', 'blurb' => 'Joyful sessions.', 'long' => 'From milestone birthdays to signature portraits.' ),
				array( 'key' => 's2', 'slug' => 'weddings', 'title' => 'Weddings', 'short' => 'Weddings', 'tag' => 'Celebrations', 'cat' => 'portraits', 'icon' => 'heart', 'photo' => null, 'blurb' => 'Forever moments.', 'long' => 'Engagements and weddings.' ),
			),
			'projects'   => array(
				array( 'slug' => 'birthday-call-concept', 'title' => 'Birthday call concept', 'cat' => 'creative', 'location' => 'Monrovia', 'cover' => 'phoneBirthday', 'lead' => 'One idea.', 'text' => 'Carried through styling.', 'gallery' => array( 'redFurSofa', 'phoneBirthday' ) ),
			),
		);
	}

	private function import( array $seed = array(), array $options = array() ): array {
		return ( new Importer( $this->dir ) )->run( array() === $seed ? self::seed() : $seed, $options );
	}

	private static function byKey( string $type, string $key ): ?\WP_Post {
		$posts = get_posts(
			array(
				'post_type'   => $type,
				'post_status' => 'any',
				'meta_key'    => Importer::KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'numberposts' => -1,
			)
		);
		return $posts[0] ?? null;
	}

	public function test_first_run_creates_everything_as_placeholder_drafts_with_relationships(): void {
		$report = $this->import();

		$this->assertSame( 0, $report['errors'] );
		$this->assertSame( 2 + 2 + 2 + 1, $report['created'] );

		$portraits = get_term_by( 'slug', 'portraits', ContentTypes::CATEGORY );
		$this->assertSame( 'Personality-rich portraits.', $portraits->description );
		$this->assertSame( 0, ContentTypes::aperture( $portraits->term_id ), 'first category takes the first aperture colour' );

		$photo = self::byKey( 'attachment', 'photo:redFurSofa' );
		$this->assertSame( 'Woman in a red faux-fur coat on a velvet sofa', get_post_meta( $photo->ID, '_wp_attachment_image_alt', true ) );
		$this->assertSame( '45% 30%', get_post_meta( $photo->ID, 'focus', true ) );
		$this->assertSame( 1, $photo->menu_order, 'portfolio order' );
		$this->assertSame( array( 'portraits' ), wp_get_object_terms( $photo->ID, ContentTypes::CATEGORY, array( 'fields' => 'slugs' ) ) );

		$service = self::byKey( ServiceCatalog::POST_TYPE, 'service:s1' );
		$this->assertSame( 'draft', $service->post_status );
		$this->assertSame( 'birthday-portrait', $service->post_name );
		$this->assertSame( Fields::PLACEHOLDER, get_post_meta( $service->ID, 'content_status', true ) );
		$this->assertSame( $photo->ID, (int) get_post_thumbnail_id( $service ) );
		$this->assertSame( 'camera', get_post_meta( $service->ID, 'icon', true ) );
		$this->assertFalse( (bool) get_post_meta( $service->ID, 'bookable', true ), 'never bookable until the owner sets prices' );
		$this->assertSame( 0.0, (float) get_post_meta( $service->ID, 'price_from', true ), 'no invented prices' );
		$this->assertSame( 1, $service->menu_order );

		$project = self::byKey( ContentTypes::PROJECT, 'project:birthday-call-concept' );
		$cover   = self::byKey( 'attachment', 'photo:phoneBirthday' );
		$this->assertSame( $cover->ID, (int) get_post_thumbnail_id( $project ) );
		$this->assertSame( array( $photo->ID, $cover->ID ), array_map( 'intval', get_post_meta( $project->ID, 'gallery', true ) ) );
		$this->assertSame( 'Monrovia', get_post_meta( $project->ID, 'location', true ) );
		$this->assertSame( 0, wp_count_posts( ContentTypes::TESTIMONIAL )->draft + wp_count_posts( ContentTypes::TESTIMONIAL )->publish, 'testimonials are never imported' );
	}

	public function test_running_again_changes_nothing_and_duplicates_nothing(): void {
		$this->import();
		$counts = array( wp_count_posts( 'attachment' )->inherit, wp_count_posts( ServiceCatalog::POST_TYPE )->draft, wp_count_posts( ContentTypes::PROJECT )->draft, wp_count_terms( array( 'taxonomy' => ContentTypes::CATEGORY, 'hide_empty' => false ) ) );

		$report = $this->import();

		$this->assertSame( 0, $report['created'] );
		$this->assertSame( 0, $report['updated'] );
		$this->assertSame( 7, $report['unchanged'] );
		$this->assertSame( $counts, array( wp_count_posts( 'attachment' )->inherit, wp_count_posts( ServiceCatalog::POST_TYPE )->draft, wp_count_posts( ContentTypes::PROJECT )->draft, wp_count_terms( array( 'taxonomy' => ContentTypes::CATEGORY, 'hide_empty' => false ) ) ) );
	}

	public function test_a_seed_change_updates_items_nobody_has_edited(): void {
		$this->import();
		$seed                         = self::seed();
		$seed['services'][0]['blurb'] = 'Updated blurb.';
		$seed['photos'][0]['alt']     = 'Updated alt text';

		$report = $this->import( $seed );

		$this->assertSame( 2, $report['updated'] );
		$this->assertSame( 'Updated blurb.', self::byKey( ServiceCatalog::POST_TYPE, 'service:s1' )->post_excerpt );
		$this->assertSame( 'Updated alt text', get_post_meta( self::byKey( 'attachment', 'photo:phoneBirthday' )->ID, '_wp_attachment_image_alt', true ) );
	}

	public function test_items_edited_in_wordpress_are_kept_unless_forced(): void {
		$this->import();
		$service = self::byKey( ServiceCatalog::POST_TYPE, 'service:s1' );
		wp_update_post( array( 'ID' => $service->ID, 'post_excerpt' => 'Written by the owner.' ) );
		$term = get_term_by( 'slug', 'creative', ContentTypes::CATEGORY );
		wp_update_term( $term->term_id, ContentTypes::CATEGORY, array( 'name' => 'Concepts' ) );

		$seed                         = self::seed();
		$seed['services'][0]['blurb'] = 'Seed wants this.';
		$report                       = $this->import( $seed );

		$this->assertSame( 2, $report['skipped_edited'] );
		$this->assertSame( 'Written by the owner.', get_post( $service->ID )->post_excerpt );
		$this->assertSame( 'Concepts', get_term( $term->term_id )->name );

		$forced = $this->import( $seed, array( 'force' => true ) );
		$this->assertSame( 0, $forced['skipped_edited'] );
		$this->assertSame( 'Seed wants this.', get_post( $service->ID )->post_excerpt );
		$this->assertSame( 'Creative concepts', get_term( $term->term_id )->name );
	}

	public function test_publishing_is_explicit_and_later_runs_never_change_status(): void {
		$this->import( array(), array( 'publish' => true ) );
		$service = self::byKey( ServiceCatalog::POST_TYPE, 'service:s1' );
		$this->assertSame( 'publish', $service->post_status );

		wp_update_post( array( 'ID' => $service->ID, 'post_status' => 'draft' ) );
		$this->import( array(), array( 'publish' => true ) );
		$this->assertSame( 'draft', get_post_status( $service->ID ), 'the owner unpublished it' );
	}

	public function test_dry_run_reports_without_writing(): void {
		$report = $this->import( array(), array( 'dry_run' => true ) );

		$this->assertSame( 7, $report['created'] );
		$this->assertSame( 0, wp_count_posts( ServiceCatalog::POST_TYPE )->draft );
		$this->assertFalse( get_term_by( 'slug', 'portraits', ContentTypes::CATEGORY ) );
		$this->assertNull( self::byKey( 'attachment', 'photo:redFurSofa' ) );
	}

	public function test_a_missing_image_or_bad_reference_is_reported_not_fatal(): void {
		$seed                         = self::seed();
		$seed['photos'][1]['image']   = 'gone-2400.jpg';
		$seed['services'][0]['photo'] = 'noSuchPhoto';

		$report = $this->import( $seed );

		$this->assertSame( 1, $report['errors'], 'the unreadable image' );
		$this->assertGreaterThanOrEqual( 1, $report['warnings'], 'references to photos that are not there' );
		$this->assertNull( self::byKey( 'attachment', 'photo:redFurSofa' ) );
		$this->assertNotNull( self::byKey( ServiceCatalog::POST_TYPE, 'service:s1' ), 'the service is still created, without an image' );
		$this->assertStringContainsString( 'gone-2400.jpg', implode( "\n", $report['messages'] ) );
	}

	public function test_alt_text_must_be_unique_and_present(): void {
		$seed                       = self::seed();
		$seed['photos'][1]['alt']   = $seed['photos'][0]['alt'];
		$report                     = $this->import( $seed );
		$this->assertSame( 1, $report['errors'] );
		$this->assertStringContainsString( 'alt text', implode( ' | ', $report['messages'] ) );
	}

	public function test_an_unknown_icon_in_the_seed_is_rejected(): void {
		$seed                        = self::seed();
		$seed['services'][1]['icon'] = 'skull';
		$report                      = $this->import( $seed );
		$this->assertSame( 1, $report['errors'] );
		$this->assertNull( self::byKey( ServiceCatalog::POST_TYPE, 'service:s2' ) );
	}
}
