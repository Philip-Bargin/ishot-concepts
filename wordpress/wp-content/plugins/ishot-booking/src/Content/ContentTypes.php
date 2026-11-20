<?php
namespace IShot\Booking\Content;

use IShot\Booking\Catalog\ServiceCatalog;
use WP_Post;

/**
 * Portfolio projects, testimonials and the shared category taxonomy. Registered by the plugin, not
 * the theme, so content survives a theme change. URLs keep the prototype's structure:
 * /work/{project}, /portfolio/{category}; services stay at /services/{service}.
 *
 * Projects, testimonials and categories are edited with edit_ishot_content (studio managers and
 * administrators). Services keep their own edit_ishot_services capability.
 */
final class ContentTypes {

	public const PROJECT          = 'ishot_project';
	public const PROJECT_REST     = 'ishot-projects';
	public const TESTIMONIAL      = 'ishot_testimonial';
	public const TESTIMONIAL_REST = 'ishot-testimonials';
	public const CATEGORY         = 'ishot_category';
	public const CAP              = 'edit_ishot_content';

	/** The logo has four aperture blades; categories cycle through their colours. */
	public const APERTURES = 4;

	public static function register(): void {
		register_post_type(
			self::PROJECT,
			array(
				'labels'       => self::labels( __( 'Projects', 'ishot-booking' ), __( 'Project', 'ishot-booking' ) ),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => self::PROJECT_REST,
				'has_archive'  => false, // /portfolio is a page; its categories live at /portfolio/{category}
				'rewrite'      => array(
					'slug'       => 'work',
					'with_front' => false,
				),
				'menu_icon'    => 'dashicons-format-gallery',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions', 'custom-fields' ),
				'map_meta_cap' => true,
				'capabilities' => self::capabilities(),
			)
		);

		register_post_type(
			self::TESTIMONIAL,
			array(
				'labels'              => self::labels( __( 'Testimonials', 'ishot-booking' ), __( 'Testimonial', 'ishot-booking' ) ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_rest'        => true,
				'rest_base'           => self::TESTIMONIAL_REST,
				'menu_icon'           => 'dashicons-format-quote',
				'supports'            => array( 'title', 'page-attributes', 'revisions', 'custom-fields' ),
				'map_meta_cap'        => true,
				'capabilities'        => self::capabilities(),
			)
		);

		register_taxonomy(
			self::CATEGORY,
			array( ServiceCatalog::POST_TYPE, self::PROJECT, 'attachment' ),
			array(
				'labels'                => array(
					'name'          => __( 'Categories', 'ishot-booking' ),
					'singular_name' => __( 'Category', 'ishot-booking' ),
					'add_new_item'  => __( 'Add category', 'ishot-booking' ),
					'edit_item'     => __( 'Edit category', 'ishot-booking' ),
				),
				'public'                => true,
				'hierarchical'          => false,
				'show_in_rest'          => true,
				'show_admin_column'     => true,
				'rewrite'               => array(
					'slug'       => 'portfolio',
					'with_front' => false,
				),
				// Attachments are "inherit", not "publish": count every attached object.
				'update_count_callback' => '_update_generic_term_count',
				'capabilities'          => array(
					'manage_terms' => self::CAP,
					'edit_terms'   => self::CAP,
					'delete_terms' => self::CAP,
					'assign_terms' => self::CAP,
				),
			)
		);

		register_term_meta(
			self::CATEGORY,
			'aperture',
			array(
				'type'          => 'integer',
				'single'        => true,
				'default'       => 0,
				'show_in_rest'  => array(
					'schema' => array(
						'type'    => 'integer',
						'minimum' => 0,
						'maximum' => self::APERTURES - 1,
					),
				),
				'auth_callback' => static fn() => current_user_can( self::CAP ),
			)
		);

		foreach ( array( ServiceCatalog::POST_TYPE, self::PROJECT, self::TESTIMONIAL ) as $type ) {
			self::registerMeta( $type );
		}
		register_post_meta(
			'attachment',
			'focus',
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => array( 'schema' => array( 'type' => 'string', 'pattern' => '^(\d{1,3}% \d{1,3}%)?$' ) ),
				'auth_callback' => static fn( $allowed, $key, $id ) => current_user_can( 'edit_post', (int) $id ),
				'description'   => 'CSS object-position that keeps the subject in frame when the image is cropped.',
			)
		);
	}

	private static function registerMeta( string $type ): void {
		foreach ( Fields::for( $type ) as $key => $field ) {
			register_post_meta(
				$type,
				$key,
				array(
					'type'          => $field['type'],
					'single'        => true,
					'default'       => $field['default'],
					'show_in_rest'  => array( 'schema' => Fields::schema( $field ) ),
					'auth_callback' => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', (int) $post_id ),
				)
			);
		}
	}

	/** The category's aperture colour, 0–3. */
	public static function aperture( int $term_id ): int {
		return abs( (int) get_term_meta( $term_id, 'aperture', true ) ) % self::APERTURES;
	}

	/**
	 * A testimonial is shown only with the client's recorded consent. Hooked to wp_after_insert_post,
	 * which every publish path reaches after meta is saved: the editor (REST), the Details box,
	 * Quick Edit, Bulk Edit and scheduled publishing.
	 */
	public static function enforceConsent( WP_Post $post ): void {
		if ( self::TESTIMONIAL === $post->post_type && 'publish' === $post->post_status && ! get_post_meta( $post->ID, 'consent', true ) ) {
			wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => 'draft',
				)
			);
		}
	}

	public static function afterInsert( int $post_id, WP_Post $post ): void {
		self::enforceConsent( $post );
		self::enforceApproval( get_post( $post_id ) );
	}

	/**
	 * Placeholder (prototype) copy never goes live: on a staging or production site a published
	 * placeholder is put back to draft until the owner sets Content status to Approved. Local and
	 * development sites may publish it for previews. Filter: ishot_booking_allow_placeholder_publish.
	 */
	public static function enforceApproval( ?WP_Post $post ): void {
		if ( null === $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, array( ServiceCatalog::POST_TYPE, self::PROJECT, self::TESTIMONIAL ), true ) ) {
			return;
		}
		$allowed = (bool) apply_filters( 'ishot_booking_allow_placeholder_publish', self::placeholderPublishAllowedOn( wp_get_environment_type() ) );
		if ( ! $allowed && Fields::PLACEHOLDER === get_post_meta( $post->ID, 'content_status', true ) ) {
			wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => 'draft',
				)
			);
		}
	}

	public static function placeholderPublishAllowedOn( string $environment ): bool {
		return in_array( $environment, array( 'local', 'development' ), true );
	}

	/** Every primitive post capability collapses to edit_ishot_content. */
	private static function capabilities(): array {
		$primitives = array( 'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts', 'create_posts' );
		return array_fill_keys( $primitives, self::CAP );
	}

	private static function labels( string $plural, string $singular ): array {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			/* translators: %s: content type, e.g. Project */
			'add_new_item'  => sprintf( __( 'Add %s', 'ishot-booking' ), strtolower( $singular ) ),
			/* translators: %s: content type, e.g. Project */
			'edit_item'     => sprintf( __( 'Edit %s', 'ishot-booking' ), strtolower( $singular ) ),
		);
	}
}
