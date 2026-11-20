<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Content\ContentTypes;
use IShot\Booking\Content\Fields;
use IShot\Booking\Roles;
use WP_REST_Request;
use WP_UnitTestCase;

/** Projects, testimonials, categories and their meta: registration, URLs, access and validation. */
final class ContentTypesTest extends WP_UnitTestCase {

	use BookingFixtures;

	public function set_up(): void {
		parent::set_up();
		// The test harness unregisters custom types (and their meta) between tests.
		ServiceCatalog::register();
		ContentTypes::register();
		Roles::install();
		$this->makeStaff();
		$this->users['author'] = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->users['editor'] = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->users['administrator'] = self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	public function tear_down(): void {
		$this->removeStaff();
		parent::tear_down();
	}

	private function rest( string $method, string $route, array $body = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $body as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	public function test_content_types_and_the_shared_category_are_registered_for_the_editor(): void {
		foreach ( array( ContentTypes::PROJECT, ContentTypes::TESTIMONIAL, ServiceCatalog::POST_TYPE ) as $type ) {
			$object = get_post_type_object( $type );
			$this->assertNotNull( $object, $type );
			$this->assertTrue( $object->show_in_rest, $type );
			$this->assertTrue( $object->show_ui, $type );
		}
		$tax = get_taxonomy( ContentTypes::CATEGORY );
		$this->assertNotFalse( $tax );
		$this->assertEqualsCanonicalizing( array( ServiceCatalog::POST_TYPE, ContentTypes::PROJECT, 'attachment' ), $tax->object_type );
		$this->assertFalse( get_post_type_object( ContentTypes::TESTIMONIAL )->publicly_queryable, 'testimonials have no pages of their own' );
	}

	public function test_urls_keep_the_prototype_structure(): void {
		$this->set_permalink_structure( '/%postname%/' );
		ContentTypes::register();
		flush_rewrite_rules( false );

		$project = self::factory()->post->create( array( 'post_type' => ContentTypes::PROJECT, 'post_name' => 'birthday-call-concept', 'post_status' => 'publish' ) );
		$term    = self::factory()->term->create( array( 'taxonomy' => ContentTypes::CATEGORY, 'slug' => 'portraits', 'name' => 'Portraits' ) );

		$this->assertSame( home_url( '/work/birthday-call-concept/' ), get_permalink( $project ) );
		$this->assertSame( home_url( '/portfolio/portraits/' ), get_term_link( $term ) );
		$this->go_to( '/work/birthday-call-concept/' );
		$this->assertQueryTrue( 'is_single', 'is_singular' );
		$this->go_to( '/portfolio/portraits/' );
		$this->assertQueryTrue( 'is_tax', 'is_archive' );
	}

	/** @return array<string, array{string, bool}> */
	public static function roles(): array {
		return array(
			'manager'       => array( 'ishot_manager', true ),
			'administrator' => array( 'administrator', true ),
			'photographer'  => array( 'ishot_photographer', false ),
			'client'        => array( 'ishot_client', false ),
			'author'        => array( 'author', false ),
			'editor'        => array( 'editor', false ),
		);
	}

	/** @dataProvider roles */
	public function test_only_managers_and_admins_edit_projects_testimonials_and_categories( string $role, bool $allowed ): void {
		$this->actAs( $role );
		$project     = self::factory()->post->create( array( 'post_type' => ContentTypes::PROJECT ) );
		$testimonial = self::factory()->post->create( array( 'post_type' => ContentTypes::TESTIMONIAL ) );

		$this->assertSame( $allowed, current_user_can( 'edit_post', $project ), 'project' );
		$this->assertSame( $allowed, current_user_can( 'edit_post', $testimonial ), 'testimonial' );
		$this->assertSame( $allowed, current_user_can( get_post_type_object( ContentTypes::PROJECT )->cap->create_posts ), 'create project' );
		$this->assertSame( $allowed, current_user_can( get_taxonomy( ContentTypes::CATEGORY )->cap->manage_terms ), 'manage categories' );
		$this->assertSame( $allowed, 201 === $this->rest( 'POST', '/wp/v2/' . ContentTypes::PROJECT_REST, array( 'title' => 'x' ) )->get_status(), 'REST create' );
	}

	public function test_service_permissions_are_unchanged(): void {
		$this->actAs( 'ishot_manager' );
		$this->assertTrue( current_user_can( 'edit_ishot_services' ) );
		$this->actAs( 'ishot_photographer' );
		$this->assertFalse( current_user_can( 'edit_ishot_services' ) );
		$this->assertFalse( current_user_can( 'edit_ishot_content' ) );
	}

	public function test_meta_is_validated_at_the_rest_api(): void {
		$this->actAs( 'ishot_manager' );
		$t = self::factory()->post->create( array( 'post_type' => ContentTypes::TESTIMONIAL ) );
		$p = self::factory()->post->create( array( 'post_type' => ContentTypes::PROJECT ) );
		$s = self::factory()->post->create( array( 'post_type' => ServiceCatalog::POST_TYPE ) );

		$this->assertSame( 400, $this->rest( 'POST', "/wp/v2/ishot-testimonials/{$t}", array( 'meta' => array( 'rating' => 7 ) ) )->get_status(), 'rating 1–5' );
		$this->assertSame( 400, $this->rest( 'POST', "/wp/v2/ishot-projects/{$p}", array( 'meta' => array( 'shoot_date' => '12/01/2026' ) ) )->get_status(), 'ISO date' );
		$this->assertSame( 400, $this->rest( 'POST', "/wp/v2/ishot-services/{$s}", array( 'meta' => array( 'icon' => 'skull' ) ) )->get_status(), 'known icon' );
		$this->assertSame( 400, $this->rest( 'POST', "/wp/v2/ishot-services/{$s}", array( 'meta' => array( 'price_from' => -5 ) ) )->get_status(), 'no negative price' );
		$this->assertSame( 400, $this->rest( 'POST', "/wp/v2/ishot-services/{$s}", array( 'meta' => array( 'content_status' => 'final' ) ) )->get_status(), 'status enum' );

		$ok = $this->rest( 'POST', "/wp/v2/ishot-services/{$s}", array( 'meta' => array( 'inclusions' => array( 'Ten edited images', 'Online gallery' ), 'icon' => 'camera' ) ) );
		$this->assertSame( 200, $ok->get_status() );
		$this->assertSame( array( 'Ten edited images', 'Online gallery' ), get_post_meta( $s, 'inclusions', true ) );
	}

	public function test_new_content_counts_as_approved_and_imports_are_marked_placeholder(): void {
		$p = self::factory()->post->create( array( 'post_type' => ContentTypes::PROJECT ) );
		$this->assertSame( Fields::APPROVED, get_post_meta( $p, 'content_status', true ) );
	}

	public function test_a_testimonial_cannot_be_published_without_recorded_consent(): void {
		$this->actAs( 'ishot_manager' );
		$response = $this->rest( 'POST', '/wp/v2/ishot-testimonials', array( 'title' => 'Ama', 'status' => 'publish', 'meta' => array( 'quote' => 'Lovely session.' ) ) );
		$id       = $response->get_data()['id'];
		$this->assertSame( 'draft', get_post_status( $id ) );

		$this->rest( 'POST', "/wp/v2/ishot-testimonials/{$id}", array( 'status' => 'publish', 'meta' => array( 'consent' => true ) ) );
		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_consent_is_enforced_on_every_publish_path_not_only_the_editor(): void {
		// Quick Edit, Bulk Edit and scheduled publishing all end in wp_insert_post without the Details box.
		$id = self::factory()->post->create( array( 'post_type' => ContentTypes::TESTIMONIAL, 'post_status' => 'draft' ) );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		$this->assertSame( 'draft', get_post_status( $id ) );

		$future = self::factory()->post->create( array( 'post_type' => ContentTypes::TESTIMONIAL, 'post_status' => 'future', 'post_date' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) ) );
		wp_publish_post( $future );
		$this->assertSame( 'draft', get_post_status( $future ) );
	}

	public function test_categories_carry_an_aperture_colour_index(): void {
		$term = self::factory()->term->create( array( 'taxonomy' => ContentTypes::CATEGORY ) );
		$this->assertTrue( registered_meta_key_exists( 'term', 'aperture', ContentTypes::CATEGORY ) );
		update_term_meta( $term, 'aperture', 7 );
		$this->assertSame( 3, ContentTypes::aperture( $term ), 'cycles through the four aperture colours' );
	}
}
