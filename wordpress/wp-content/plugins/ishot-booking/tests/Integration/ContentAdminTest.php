<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Content\ContentAdmin;
use IShot\Booking\Content\ContentTypes;
use IShot\Booking\Content\Fields;
use IShot\Booking\Roles;
use WP_UnitTestCase;

/** The wp-admin editing panels: details boxes, category colour field, placeholder flags. */
final class ContentAdminTest extends WP_UnitTestCase {

	use BookingFixtures;

	public function set_up(): void {
		parent::set_up();
		// The test harness unregisters custom types (and their meta) between tests.
		ServiceCatalog::register();
		ContentTypes::register();
		Roles::install();
		$this->makeStaff();
	}

	public function tear_down(): void {
		$this->removeStaff();
		$_POST = array();
		parent::tear_down();
	}

	private function post( int $id, array $fields ): void {
		$_POST = array(
			ContentAdmin::NONCE => wp_create_nonce( ContentAdmin::NONCE . $id ),
			'ishot'             => $fields,
		);
		ContentAdmin::save( $id, get_post( $id ) );
	}

	public function test_valid_details_are_saved_and_invalid_ones_left_unchanged(): void {
		$this->actAs( 'ishot_manager' );
		$id = self::factory()->post->create( array( 'post_type' => ContentTypes::TESTIMONIAL ) );

		$this->post( $id, array( 'client_name' => 'Ama K.', 'rating' => '4', 'consent' => '1', 'quote' => "Calm and\nfun." ) );
		$this->assertSame( 'Ama K.', get_post_meta( $id, 'client_name', true ) );
		$this->assertSame( 4, (int) get_post_meta( $id, 'rating', true ) );
		$this->assertTrue( (bool) get_post_meta( $id, 'consent', true ) );

		$this->post( $id, array( 'client_name' => 'Ama K.', 'rating' => '9', 'quote' => 'x' ) );
		$this->assertSame( 4, (int) get_post_meta( $id, 'rating', true ), 'out of range: kept' );
		$this->assertFalse( (bool) get_post_meta( $id, 'consent', true ), 'unticked checkbox clears consent' );
		$this->assertNotEmpty( ContentAdmin::takeErrors( $id ) );
	}

	public function test_service_inclusions_are_entered_one_per_line(): void {
		$this->actAs( 'ishot_manager' );
		$id = self::factory()->post->create( array( 'post_type' => ServiceCatalog::POST_TYPE ) );
		$this->post( $id, array( 'inclusions' => "Ten edited images\n\n  Online gallery  \n", 'icon' => 'heart', 'duration_min' => '90', 'price_from' => '120' ) );

		$this->assertSame( array( 'Ten edited images', 'Online gallery' ), get_post_meta( $id, 'inclusions', true ) );
		$this->assertSame( 90, (int) get_post_meta( $id, 'duration_min', true ) );
		$this->assertSame( 120.0, (float) get_post_meta( $id, 'price_from', true ) );
	}

	public function test_project_gallery_keeps_only_real_images_in_order(): void {
		$this->actAs( 'ishot_manager' );
		$a  = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		$b  = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		$id = self::factory()->post->create( array( 'post_type' => ContentTypes::PROJECT ) );

		$this->post( $id, array( 'gallery' => "{$b},999999,{$a},{$b}", 'shoot_date' => '2026-10-08' ) );

		$this->assertSame( array( $b, $a ), array_map( 'intval', get_post_meta( $id, 'gallery', true ) ) );
		$this->assertSame( '2026-10-08', get_post_meta( $id, 'shoot_date', true ) );

		$this->post( $id, array( 'gallery' => '' ) );
		$this->assertSame( array(), get_post_meta( $id, 'gallery', true ), 'clearing the field removes every image' );
	}

	public function test_without_the_capability_or_nonce_nothing_is_saved(): void {
		$id = self::factory()->post->create( array( 'post_type' => ContentTypes::PROJECT ) );

		$this->actAs( 'ishot_photographer' );
		$this->post( $id, array( 'location' => 'Hacked' ) );
		$this->assertSame( '', get_post_meta( $id, 'location', true ) );

		$this->actAs( 'ishot_manager' );
		$_POST = array( 'ishot' => array( 'location' => 'No nonce' ) );
		ContentAdmin::save( $id, get_post( $id ) );
		$this->assertSame( '', get_post_meta( $id, 'location', true ) );
	}

	public function test_the_details_box_renders_the_fields_for_each_type(): void {
		$this->actAs( 'ishot_manager' );
		$id = self::factory()->post->create( array( 'post_type' => ContentTypes::TESTIMONIAL ) );
		ob_start();
		ContentAdmin::renderBox( get_post( $id ) );
		$html = (string) ob_get_clean();
		foreach ( array( 'name="ishot[client_name]"', 'name="ishot[rating]"', 'name="ishot[consent]"', ContentAdmin::NONCE ) as $needle ) {
			$this->assertStringContainsString( $needle, $html );
		}
	}

	public function test_placeholder_content_is_flagged_in_the_list(): void {
		$id = self::factory()->post->create( array( 'post_type' => ServiceCatalog::POST_TYPE ) );
		update_post_meta( $id, 'content_status', Fields::PLACEHOLDER );
		ob_start();
		ContentAdmin::column( 'ishot_content', $id );
		$this->assertStringContainsString( 'Placeholder', (string) ob_get_clean() );
	}

	public function test_category_colour_is_saved_from_the_term_screen(): void {
		$this->actAs( 'ishot_manager' );
		$term                         = self::factory()->term->create( array( 'taxonomy' => ContentTypes::CATEGORY ) );
		$_POST                        = array( 'ishot_aperture' => '2', ContentAdmin::TERM_NONCE => wp_create_nonce( ContentAdmin::TERM_NONCE ) );
		ContentAdmin::saveTerm( $term );
		$this->assertSame( 2, ContentTypes::aperture( $term ) );
	}
}
