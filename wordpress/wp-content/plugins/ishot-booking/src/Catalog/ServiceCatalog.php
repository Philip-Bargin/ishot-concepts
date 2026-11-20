<?php
namespace IShot\Booking\Catalog;

use WP_Post;

/**
 * The ishot_service post type and its booking meta. Owned by the plugin, not the theme, so
 * services and their bookings survive a theme change.
 */
final class ServiceCatalog {

	public const POST_TYPE = 'ishot_service';

	private const META = array(
		'duration_min'      => array( 'integer', 60 ),
		'buffer_before_min' => array( 'integer', 0 ),
		'buffer_after_min'  => array( 'integer', 0 ),
		'price_from'        => array( 'number', 0 ),
		'deposit_amount'    => array( 'number', 0 ),
		'bookable'          => array( 'boolean', false ),
	);

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Services', 'ishot-booking' ),
					'singular_name' => __( 'Service', 'ishot-booking' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'services' ),
				'menu_icon'    => 'dashicons-camera',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields' ),
			)
		);

		foreach ( self::META as $key => [ $type, $default ] ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'default'       => $default,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}

	/** A published, bookable service, or null. */
	public static function bookable( int $id ): ?Service {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}
		$service = self::fromPost( $post );
		return $service->bookable ? $service : null;
	}

	/** @return Service[] Published bookable services in menu order. */
	public static function allBookable(): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a handful of services.
					array(
						'key'   => 'bookable',
						'value' => '1',
					),
				),
			)
		);
		return array_map( array( self::class, 'fromPost' ), $posts );
	}

	private static function fromPost( WP_Post $post ): Service {
		$meta = static fn( string $key ) => get_post_meta( $post->ID, $key, true );
		return new Service(
			$post->ID,
			get_the_title( $post ),
			max( 1, (int) $meta( 'duration_min' ) ),
			max( 0, (int) $meta( 'buffer_before_min' ) ),
			max( 0, (int) $meta( 'buffer_after_min' ) ),
			(float) $meta( 'price_from' ),
			(float) $meta( 'deposit_amount' ),
			(bool) $meta( 'bookable' )
		);
	}
}
