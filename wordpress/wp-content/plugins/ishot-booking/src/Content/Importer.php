<?php
namespace IShot\Booking\Content;

use IShot\Booking\Catalog\ServiceCatalog;
use WP_Error;

/**
 * Imports the React prototype's content (wordpress/seed/prototype-content.json, written by
 * `npm run seed:export`) and its photos. Safe to run any number of times:
 *
 * - Every item carries an import key (_ishot_import_key), so nothing is created twice.
 * - Two hashes are stored per item: of the seed it came from and of what WordPress held right
 *   after the import. On a later run an item is
 *     unchanged       when neither the seed nor WordPress changed,
 *     updated         when only the seed changed,
 *     skipped_edited  when someone edited it in WordPress (overwritten only with `force`).
 * - New posts are drafts unless `publish` is given; later runs never change a post's status.
 * - Everything imported is marked content_status = placeholder. Prices and deposits are never
 *   set, services are never made bookable, and testimonials are never imported: those must come
 *   from the owner.
 */
final class Importer {

	public const KEY_META       = '_ishot_import_key';
	private const SEED_HASH     = '_ishot_import_seed_hash';
	private const STATE_HASH    = '_ishot_import_state_hash';

	/** @var array{created: int, updated: int, unchanged: int, skipped_edited: int, errors: int, warnings: int, messages: string[]} */
	private array $report;
	/** @var array<string, int> photo key => attachment id (0 in a dry run) */
	private array $photos = array();
	private array $options;

	public function __construct( private readonly string $image_dir ) {}

	/**
	 * @param array<string, mixed>                                    $seed
	 * @param array{dry_run?: bool, force?: bool, publish?: bool}     $options
	 * @return array{created: int, updated: int, unchanged: int, skipped_edited: int, errors: int, warnings: int, messages: string[]}
	 */
	public function run( array $seed, array $options = array() ): array {
		$this->options = $options + array(
			'dry_run' => false,
			'force'   => false,
			'publish' => false,
		);
		$this->report  = array(
			'created'        => 0,
			'updated'        => 0,
			'unchanged'      => 0,
			'skipped_edited' => 0,
			'errors'         => 0,
			'warnings'       => 0,
			'messages'       => array(),
		);
		$this->photos  = array();

		foreach ( (array) ( $seed['categories'] ?? array() ) as $i => $category ) {
			$this->category( (array) $category, (int) $i );
		}
		$this->importPhotos( (array) ( $seed['photos'] ?? array() ), array_values( (array) ( $seed['portfolio'] ?? array() ) ) );
		foreach ( (array) ( $seed['services'] ?? array() ) as $i => $service ) {
			$this->service( (array) $service, (int) $i );
		}
		foreach ( (array) ( $seed['projects'] ?? array() ) as $i => $project ) {
			$this->project( (array) $project, (int) $i );
		}
		if ( ! empty( $seed['testimonials'] ) ) {
			$this->warn( 'The seed contains testimonials; they are never imported. Add real ones, with consent, in wp-admin.' );
		}
		return $this->report;
	}

	// ----- items -------------------------------------------------------------------------------

	private function category( array $c, int $index ): void {
		$key = 'category:' . ( $c['slug'] ?? '' );
		if ( empty( $c['slug'] ) || empty( $c['label'] ) ) {
			$this->error( "Category #{$index}: slug and label are required." );
			return;
		}
		$want = array(
			'name'        => (string) $c['label'],
			'slug'        => sanitize_title( (string) $c['slug'] ),
			'description' => (string) ( $c['blurb'] ?? '' ),
			'aperture'    => $index % ContentTypes::APERTURES,
		);
		$existing = get_terms(
			array(
				'taxonomy'   => ContentTypes::CATEGORY,
				'hide_empty' => false,
				'meta_key'   => self::KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'     => 'ids',
			)
		);
		$term_id  = is_array( $existing ) && array() !== $existing ? (int) $existing[0] : 0;
		if ( 0 === $term_id ) {
			// Created by hand with the same slug: adopt it; with no import hashes it counts as edited.
			$same    = get_term_by( 'slug', $want['slug'], ContentTypes::CATEGORY );
			$term_id = false === $same ? 0 : (int) $same->term_id;
		}
		$state    = static fn( int $id ): array => self::termState( $id );
		$write    = function ( int $id ) use ( $want, $key ): int {
			$args   = array(
				'slug'        => $want['slug'],
				'description' => $want['description'],
			);
			$result = 0 === $id
				? wp_insert_term( $want['name'], ContentTypes::CATEGORY, $args )
				: wp_update_term( $id, ContentTypes::CATEGORY, $args + array( 'name' => $want['name'] ) );
			if ( is_wp_error( $result ) ) {
				return $this->error( "{$key}: " . $result->get_error_message() );
			}
			$id = (int) $result['term_id'];
			update_term_meta( $id, 'aperture', $want['aperture'] );
			update_term_meta( $id, self::KEY_META, $key );
			return $id;
		};
		$this->upsert( $key, $want, $term_id, $state, $write, 'term' );
	}

	/** @param array<int, array<string, mixed>> $photos */
	private function importPhotos( array $photos, array $portfolio ): void {
		$alts = array(); // alt text => first photo key that used it
		foreach ( $photos as $i => $p ) {
			$photo_key = (string) ( $p['key'] ?? '' );
			$key       = 'photo:' . $photo_key;
			$alt       = trim( (string) ( $p['alt'] ?? '' ) );
			$file      = $this->image_dir . '/' . basename( (string) ( $p['image'] ?? '' ) );
			if ( '' === $photo_key || '' === $alt || ( isset( $alts[ $alt ] ) && $alts[ $alt ] !== $photo_key ) ) {
				$this->error( "{$key}: every photo needs a key and its own alt text (empty, or already used by " . ( $alts[ $alt ] ?? "another photo" ) . ")." );
				continue;
			}
			$alts[ $alt ] = $photo_key;
			if ( empty( $p['image'] ) || ! is_readable( $file ) || false === wp_getimagesize( $file ) ) {
				$this->error( "{$key}: image " . basename( (string) ( $p['image'] ?? '' ) ) . " not found or not an image in {$this->image_dir}." );
				continue;
			}
			$order = array_search( $photo_key, $portfolio, true );
			$want  = array(
				'title'      => wp_trim_words( $alt, 8, '' ),
				'alt'        => $alt,
				'focus'      => (string) ( $p['focus'] ?? '' ),
				'menu_order' => false === $order ? 0 : $order + 1,
				'terms'      => $this->termSlugs( array( $p['cat'] ?? '' ), $key ),
			);
			$id    = $this->findPost( 'attachment', $key );
			$write = function ( int $id ) use ( $want, $key, $file ): int {
				if ( 0 === $id ) {
					$id = $this->sideload( $file, $key );
					if ( 0 === $id ) {
						return 0;
					}
				}
				wp_update_post(
					array(
						'ID'         => $id,
						'post_title' => $want['title'],
						'menu_order' => $want['menu_order'],
					)
				);
				update_post_meta( $id, '_wp_attachment_image_alt', $want['alt'] );
				update_post_meta( $id, 'focus', $want['focus'] );
				wp_set_object_terms( $id, $want['terms'], ContentTypes::CATEGORY );
				return $id;
			};
			$this->photos[ $photo_key ] = $this->upsert( $key, $want, $id, array( self::class, 'attachmentState' ), $write );
		}
	}

	private function service( array $s, int $index ): void {
		$key = 'service:' . ( $s['key'] ?? '' );
		if ( empty( $s['key'] ) || empty( $s['title'] ) || empty( $s['slug'] ) ) {
			$this->error( "Service #{$index}: key, title and slug are required." );
			return;
		}
		$icon = (string) ( $s['icon'] ?? '' );
		if ( '' !== $icon && ! in_array( $icon, Fields::ICONS, true ) ) {
			$this->error( "{$key}: unknown icon \"{$icon}\" (allowed: " . implode( ', ', Fields::ICONS ) . ').' );
			return;
		}
		$this->post(
			ServiceCatalog::POST_TYPE,
			$key,
			array(
				'post_title'   => (string) $s['title'],
				'post_name'    => sanitize_title( (string) $s['slug'] ),
				'post_excerpt' => (string) ( $s['blurb'] ?? '' ),
				'post_content' => self::paragraphs( (string) ( $s['long'] ?? '' ) ),
				'menu_order'   => $index + 1,
			),
			array(
				'short_title'    => (string) ( $s['short'] ?? '' ),
				'tag'            => (string) ( $s['tag'] ?? '' ),
				'icon'           => $icon,
				'content_status' => Fields::PLACEHOLDER,
			),
			$this->termSlugs( array( $s['cat'] ?? '' ), $key ),
			$this->photoId( $s['photo'] ?? null, $key )
		);
	}

	private function project( array $p, int $index ): void {
		$key = 'project:' . ( $p['slug'] ?? '' );
		if ( empty( $p['slug'] ) || empty( $p['title'] ) ) {
			$this->error( "Project #{$index}: slug and title are required." );
			return;
		}
		$gallery = array_values( array_filter( array_map( fn( $ref ) => $this->photoId( $ref, $key ), (array) ( $p['gallery'] ?? array() ) ) ) );
		$this->post(
			ContentTypes::PROJECT,
			$key,
			array(
				'post_title'   => (string) $p['title'],
				'post_name'    => sanitize_title( (string) $p['slug'] ),
				'post_excerpt' => (string) ( $p['lead'] ?? '' ),
				'post_content' => self::paragraphs( (string) ( $p['text'] ?? '' ) ),
				'menu_order'   => $index + 1,
			),
			array(
				'location'       => (string) ( $p['location'] ?? '' ),
				'gallery'        => $gallery,
				'content_status' => Fields::PLACEHOLDER,
			),
			$this->termSlugs( array( $p['cat'] ?? '' ), $key ),
			$this->photoId( $p['cover'] ?? null, $key )
		);
	}

	// ----- generic post upsert -----------------------------------------------------------------

	private function post( string $type, string $key, array $fields, array $meta, array $terms, int $thumb ): void {
		$want  = array(
			'fields' => $fields,
			'meta'   => $meta,
			'terms'  => $terms,
			'thumb'  => $thumb,
		);
		$id    = $this->findPost( $type, $key );
		if ( 0 === $id ) {
			// Created by hand with the same slug: adopt it; with no import hashes it counts as edited.
			$same = get_page_by_path( $fields['post_name'], OBJECT, $type );
			$id   = null === $same ? 0 : $same->ID;
		}
		$state = static fn( int $id ): array => self::postState( $id, array_keys( $meta ) );
		$write = function ( int $id ) use ( $type, $key, $want ): int {
			// meta_input lands before wp_after_insert_post, so the placeholder guard sees it.
			$args = $want['fields'] + array(
				'post_type'  => $type,
				'meta_input' => $want['meta'],
			);
			if ( 0 === $id ) {
				$args['post_status'] = $this->options['publish'] ? 'publish' : 'draft';
				$id                  = wp_insert_post( wp_slash( $args ), true );
			} else {
				$id = wp_update_post( wp_slash( $args + array( 'ID' => $id ) ), true );
			}
			if ( is_wp_error( $id ) ) {
				return $this->error( "{$key}: " . $id->get_error_message() );
			}
			foreach ( $want['meta'] as $meta_key => $value ) {
				update_post_meta( $id, $meta_key, $value );
			}
			update_post_meta( $id, self::KEY_META, $key );
			wp_set_object_terms( $id, $want['terms'], ContentTypes::CATEGORY );
			$want['thumb'] > 0 ? set_post_thumbnail( $id, $want['thumb'] ) : delete_post_thumbnail( $id );
			return $id;
		};
		$this->upsert( $key, $want, $id, $state, $write );
	}

	/**
	 * Decides create / update / unchanged / skip and applies it.
	 *
	 * @param callable(int): array $state Reads the managed fields back from WordPress.
	 * @param callable(int): int   $write Writes the seed values; returns the id (0 on failure).
	 * @return int The item's id (0 when it does not exist, e.g. in a dry run).
	 */
	private function upsert( string $key, array $want, int $id, callable $state, callable $write, string $kind = 'post' ): int {
		$seed_hash = self::hash( $want );
		if ( 0 !== $id ) {
			$get       = 'term' === $kind ? 'get_term_meta' : 'get_post_meta';
			$edited    = self::hash( $state( $id ) ) !== $get( $id, self::STATE_HASH, true );
			$seed_same = $seed_hash === $get( $id, self::SEED_HASH, true );
			if ( $edited && ! $this->options['force'] ) {
				++$this->report['skipped_edited'];
				$this->report['messages'][] = "{$key}: edited in WordPress, kept as is (use --force to overwrite).";
				return $id;
			}
			if ( $seed_same && ! $edited ) {
				++$this->report['unchanged'];
				return $id;
			}
		}
		if ( $this->options['dry_run'] ) {
			++$this->report[ 0 === $id ? 'created' : 'updated' ];
			$this->report['messages'][] = "{$key}: would be " . ( 0 === $id ? 'created' : 'updated' ) . '.';
			return $id;
		}
		$created = 0 === $id;
		$id      = $write( $id );
		if ( 0 === $id ) {
			return 0;
		}
		$set = 'term' === $kind ? 'update_term_meta' : 'update_post_meta';
		$set( $id, self::SEED_HASH, $seed_hash );
		$set( $id, self::STATE_HASH, self::hash( $state( $id ) ) );
		++$this->report[ $created ? 'created' : 'updated' ];
		return $id;
	}

	// ----- state readers (what counts as "edited") ---------------------------------------------

	/** @param string[] $meta_keys */
	private static function postState( int $id, array $meta_keys ): array {
		clean_post_cache( $id );
		$post = get_post( $id );
		$meta = array();
		foreach ( $meta_keys as $k ) {
			$meta[ $k ] = get_post_meta( $id, $k, true );
		}
		return array(
			'fields' => array( $post->post_title, $post->post_name, $post->post_excerpt, $post->post_content, (int) $post->menu_order ),
			'meta'   => $meta,
			'terms'  => self::slugs( $id ),
			'thumb'  => (int) get_post_thumbnail_id( $id ),
		);
	}

	private static function attachmentState( int $id ): array {
		clean_post_cache( $id );
		$post = get_post( $id );
		return array( $post->post_title, (int) $post->menu_order, get_post_meta( $id, '_wp_attachment_image_alt', true ), get_post_meta( $id, 'focus', true ), self::slugs( $id ) );
	}

	private static function termState( int $id ): array {
		clean_term_cache( $id, ContentTypes::CATEGORY );
		$term = get_term( $id, ContentTypes::CATEGORY );
		return array( $term->name, $term->slug, $term->description, (int) get_term_meta( $id, 'aperture', true ) );
	}

	/** @return string[] */
	private static function slugs( int $id ): array {
		$slugs = wp_get_object_terms( $id, ContentTypes::CATEGORY, array( 'fields' => 'slugs' ) );
		$slugs = is_wp_error( $slugs ) ? array() : $slugs;
		sort( $slugs );
		return $slugs;
	}

	// ----- helpers -----------------------------------------------------------------------------

	private function findPost( string $type, string $key ): int {
		$ids = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'any',
				'meta_key'         => self::KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'           => 'ids',
				'numberposts'      => 1,
				'suppress_filters' => true,
			)
		);
		return (int) ( $ids[0] ?? 0 );
	}

	/** Copies the file to a temp name (sideloading moves it) and lets WordPress make the sizes. */
	private function sideload( string $file, string $key ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( basename( $file ) );
		copy( $file, $tmp );
		$id = media_handle_sideload(
			array(
				'name'     => basename( $file ),
				'tmp_name' => $tmp,
			),
			0
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $this->error( "{$key}: " . $id->get_error_message() );
		}
		update_post_meta( $id, self::KEY_META, $key );
		return $id;
	}

	/** @return string[] Category slugs that exist (or are about to, in a dry run). */
	private function termSlugs( array $slugs, string $key ): array {
		$out = array();
		foreach ( array_filter( array_map( 'strval', $slugs ) ) as $slug ) {
			if ( get_term_by( 'slug', $slug, ContentTypes::CATEGORY ) || $this->options['dry_run'] ) {
				$out[] = $slug;
			} else {
				$this->warn( "{$key}: unknown category \"{$slug}\"." );
			}
		}
		sort( $out );
		return $out;
	}

	private function photoId( mixed $ref, string $key ): int {
		if ( null === $ref || '' === $ref ) {
			return 0;
		}
		if ( ! array_key_exists( (string) $ref, $this->photos ) ) {
			$this->warn( "{$key}: photo \"{$ref}\" was not imported." );
			return 0;
		}
		return $this->photos[ (string) $ref ];
	}

	private static function paragraphs( string $text ): string {
		$text = trim( $text );
		return '' === $text ? '' : "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->";
	}

	private static function hash( array $value ): string {
		return md5( (string) wp_json_encode( $value ) );
	}

	private function error( string $message ): int {
		++$this->report['errors'];
		$this->report['messages'][] = 'Error: ' . $message;
		return 0;
	}

	private function warn( string $message ): void {
		++$this->report['warnings'];
		$this->report['messages'][] = 'Warning: ' . $message;
	}
}
