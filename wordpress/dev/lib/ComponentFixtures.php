<?php
/**
 * DEV ONLY. Component fixture pages for the Stage 2 component visual gate (plan §6 Stage 2, R-2-C).
 *
 * @package ishot-dev
 */

namespace IShot\Dev;

use IShot\Booking\Content\Fields;
use IShot\Booking\Content\Importer;
use IShot\Booking\Dev\DevGuard;

/**
 * DEV ONLY. Creates /ishot-fixtures/{component}/ pages, each holding one prototype section rebuilt from Stage 2
 * blocks and patterns, so tests/visual/components.mjs can compare it with the prototype section by section.
 * Used by wordpress/dev/component-fixtures-dev.php and the ishot-blocks ComponentFixturesTest, nothing else.
 * It lives in wordpress/dev/lib, outside every plugin's autoloaded src (ruling R-2-1): no production code can load it.
 *
 * It fails safe:
 * - every DevGuard guard must pass before anything is read for writing or written (run() and remove() alike);
 * - an existing page with a fixture slug, any status, is never touched; when the parent slug belongs to the
 *   owner, nothing is created at all;
 * - every created page is published, marked placeholder (the "Sample content." notice shows) and carries
 *   MARKER, and remove() deletes only pages carrying MARKER.
 * Copy and photos are the prototype's own; photos are found by the importer key (photo:{key}).
 */
final class ComponentFixtures {

	/** The parent page: every fixture lives at /ishot-fixtures/{slug}/. */
	public const PARENT = 'ishot-fixtures';

	/** Marks a page as fixture-made, so it can be told apart from owner content and removed. */
	public const MARKER = '_ishot_dev_fixture';

	/**
	 * Slug => block markup. `{{image:KEY}}` becomes a core/image block of the attachment imported as photo:KEY
	 * (alt "", the prototype's decorative use). Each Stage 2 task adds its own entries (tests/visual/components.mjs
	 * holds the matching rows).
	 */
	public const PAGES = array(
		// Home.jsx:17-23, 71-75 (the About strip). Task 2.1b's gallery `strip` style prints the prototype markup.
		'strip' => '<!-- wp:group {"tagName":"section","className":"fo-section fo-section--tall","layout":{"type":"default"}} -->
<section class="wp-block-group fo-section fo-section--tall"><!-- wp:group {"className":"fo-container","layout":{"type":"default"}} -->
<div class="wp-block-group fo-container"><!-- wp:gallery {"linkTo":"none","className":"is-style-strip"} -->
<figure class="wp-block-gallery has-nested-images columns-default is-cropped is-style-strip">{{image:denimSmile}}{{image:burgundyLounge}}{{image:graduate}}{{image:redFurSofa}}{{image:gownStair}}</figure>
<!-- /wp:gallery --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->',
	);

	/** Parent page title (shown by the page-title pattern on /ishot-fixtures/). */
	private const PARENT_TITLE = 'Component fixtures';

	/**
	 * The only entry point for creating. It reads the guard inputs itself; callers cannot supply them.
	 *
	 * @return array{ok: bool, reason: string, created: list<string>, skipped: list<string>}
	 */
	public static function run(): array {
		return self::execute( ...self::guardInputs() );
	}

	/**
	 * Which action the script's positional arguments ask for: none creates, `remove` removes, anything else is an error.
	 *
	 * @param list<string> $args Positional arguments as `wp eval-file` passes them.
	 * @return array{command: string, error: string}
	 */
	public static function command( array $args ): array {
		if ( array() === $args ) {
			return array(
				'command' => 'run',
				'error'   => '',
			);
		}
		if ( array( 'remove' ) === $args ) {
			return array(
				'command' => 'remove',
				'error'   => '',
			);
		}
		return array(
			'command' => '',
			'error'   => 'Unknown argument "' . implode( ' ', $args ) . '": pass nothing to create the fixture pages, or the single word `remove` to delete them (not --remove).',
		);
	}

	/**
	 * Deletes every page carrying MARKER. Same guards as run().
	 *
	 * @return array{ok: bool, reason: string, removed: list<string>, kept: list<string>}
	 */
	public static function remove(): array {
		return self::executeRemove( ...self::guardInputs() );
	}

	/**
	 * Every photo key the fixtures use, in first-use order (for tests and the missing-photo message).
	 *
	 * @return list<string>
	 */
	public static function photoKeys(): array {
		$keys = array();
		foreach ( self::PAGES as $markup ) {
			preg_match_all( '/\{\{image:([A-Za-z0-9_-]+)\}\}/', $markup, $m );
			$keys = array_merge( $keys, $m[1] );
		}
		return array_values( array_unique( $keys ) );
	}

	/** @return array{string, mixed, string} */
	private static function guardInputs(): array {
		return array(
			wp_get_environment_type(),
			defined( 'ISHOT_DEV_FIXTURES' ) ? constant( 'ISHOT_DEV_FIXTURES' ) : null,
			home_url(),
		);
	}

	/**
	 * Private on purpose: the guard values must come from run(). Tests reach it by reflection.
	 *
	 * @param string $environment_type wp_get_environment_type().
	 * @param mixed  $opt_in           The value of ISHOT_DEV_FIXTURES, or null when undefined.
	 * @param string $home_url         home_url().
	 * @return array{ok: bool, reason: string, created: list<string>, skipped: list<string>}
	 */
	private static function execute( string $environment_type, mixed $opt_in, string $home_url ): array {
		$result = array(
			'ok'      => false,
			'reason'  => DevGuard::blocked( $environment_type, $opt_in, $home_url ),
			'created' => array(),
			'skipped' => array(),
		);
		if ( '' !== $result['reason'] ) {
			return $result;
		}
		$result['ok'] = true;

		self::restoreTrashedParent();
		$existing = self::find( self::PARENT, 0 );
		if ( null !== $existing && ! self::isFixture( $existing ) ) {
			$result['skipped'][] = self::PARENT;
			return $result; // the owner's page: create nothing beneath it
		}
		if ( null === $existing ) {
			$parent_id = self::create( self::PARENT, self::PARENT_TITLE, '', 0 );
			if ( 0 === $parent_id ) {
				$result['ok']     = false;
				$result['reason'] = 'Could not create the ' . self::PARENT . ' page.';
				return $result;
			}
			$result['created'][] = self::PARENT;
		} else {
			$parent_id           = $existing->ID;
			$result['skipped'][] = self::PARENT;
		}

		foreach ( self::PAGES as $slug => $template ) {
			if ( null !== self::find( $slug, $parent_id ) ) {
				$result['skipped'][] = $slug;
				continue;
			}
			$markup = self::resolve( $template );
			if ( is_string( $markup ) ) {
				if ( 0 !== self::create( $slug, self::title( $slug ), $markup, $parent_id ) ) {
					$result['created'][] = $slug;
				} else {
					$result['skipped'][] = $slug . ' (could not be created)';
				}
				continue;
			}
			$result['skipped'][] = sprintf( '%s (photo:%s not imported; run npm run wp:import)', $slug, $markup['missing'] );
		}
		return $result;
	}

	/**
	 * @param string $environment_type wp_get_environment_type().
	 * @param mixed  $opt_in           The value of ISHOT_DEV_FIXTURES, or null when undefined.
	 * @param string $home_url         home_url().
	 * @return array{ok: bool, reason: string, removed: list<string>, kept: list<string>}
	 */
	private static function executeRemove( string $environment_type, mixed $opt_in, string $home_url ): array {
		$result = array(
			'ok'      => false,
			'reason'  => DevGuard::blocked( $environment_type, $opt_in, $home_url ),
			'removed' => array(),
			'kept'    => array(),
		);
		if ( '' !== $result['reason'] ) {
			return $result;
		}
		$result['ok'] = true;

		$marked = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => array_keys( get_post_stati() ),
				'posts_per_page'   => -1,
				'meta_key'         => self::MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- dev-only script: finds our own marked pages, a handful at most.
				'orderby'          => 'ID',
				'order'            => 'DESC', // children (created later) first
				'suppress_filters' => true,
			)
		);
		foreach ( $marked as $post ) {
			if ( ! self::isFixture( $post ) ) {
				continue;
			}
			// wp_delete_post() reparents remaining children to the deleted page's parent, which would change an
			// owner page's URL: keep the marked page while anything unmarked still hangs under it.
			$owner_children = self::remainingChildren( $post->ID );
			if ( array() !== $owner_children ) {
				$result['kept'][] = sprintf( '%s (kept: owner content still under it: %s)', $post->post_name, implode( ', ', $owner_children ) );
				continue;
			}
			if ( false !== wp_delete_post( $post->ID, true ) ) {
				$result['removed'][] = $post->post_name;
			}
		}
		return $result;
	}

	/**
	 * Slugs (with status) of everything still parented to the page, of any post type and status (trash included).
	 *
	 * @return list<string>
	 */
	private static function remainingChildren( int $parent_id ): array {
		$types    = array_values( array_diff( get_post_types(), array( 'revision' ) ) );
		$children = get_posts(
			array(
				'post_type'        => $types,
				'post_status'      => array_keys( get_post_stati() ),
				'post_parent'      => $parent_id,
				'posts_per_page'   => -1,
				'suppress_filters' => true,
			)
		);
		return array_map(
			static fn( \WP_Post $child ): string => sprintf( '%s [%s %s]', $child->post_name, $child->post_type, $child->post_status ),
			$children
		);
	}

	/**
	 * Trashing renames a page's slug to "{slug}__trashed", so find() never sees a trashed parent and children would
	 * hang under a second one. Our own (marked) trashed parent is restored; an owner's trashed page is never touched.
	 */
	private static function restoreTrashedParent(): void {
		$trashed = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'trash',
				'post_parent'      => 0,
				'posts_per_page'   => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'meta_key'         => self::MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- dev-only script: finds our own marked pages, a handful at most.
				'suppress_filters' => true,
			)
		);
		foreach ( $trashed as $post ) {
			if ( self::isFixture( $post ) && 0 === strpos( $post->post_name, self::PARENT ) && null === self::find( self::PARENT, 0 ) ) {
				wp_untrash_post( $post->ID ); // restores as a draft (WordPress 5.6+), but the fixture parent must be public.
				wp_update_post(
					array(
						'ID'          => $post->ID,
						'post_status' => 'publish',
					)
				);
			}
		}
	}

	/** A page with that slug under that parent, whatever its status (trash included). */
	private static function find( string $slug, int $parent_id ): ?\WP_Post {
		$found = get_posts(
			array(
				'name'             => $slug,
				'post_type'        => 'page',
				'post_parent'      => $parent_id,
				'post_status'      => array_keys( get_post_stati() ),
				'posts_per_page'   => 1,
				'suppress_filters' => true,
			)
		);
		return array() === $found ? null : $found[0];
	}

	private static function isFixture( \WP_Post $post ): bool {
		return '1' === (string) get_post_meta( $post->ID, self::MARKER, true );
	}

	private static function create( string $slug, string $title, string $content, int $parent_id ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_parent'  => $parent_id,
				'post_content' => $content,
				'meta_input'   => array(
					'content_status' => Fields::PLACEHOLDER,
					self::MARKER     => 1,
				),
			),
			true
		);
		return is_int( $id ) ? $id : 0;
	}

	private static function title( string $slug ): string {
		return 'Fixture: ' . $slug;
	}

	/**
	 * Replaces every {{image:KEY}} token, or names the first photo that is not imported.
	 *
	 * @return string|array{missing: string}
	 */
	private static function resolve( string $template ): string|array {
		$missing = '';
		$markup  = preg_replace_callback(
			'/\{\{image:([A-Za-z0-9_-]+)\}\}/',
			static function ( array $m ) use ( &$missing ): string {
				$id = self::photo( $m[1] );
				if ( 0 === $id ) {
					$missing = '' === $missing ? $m[1] : $missing;
					return '';
				}
				return self::imageBlock( $id );
			},
			$template
		);
		return '' !== $missing || ! is_string( $markup ) ? array( 'missing' => $missing ) : $markup;
	}

	/** The attachment imported as photo:{key}, or 0. */
	private static function photo( string $key ): int {
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC', // a duplicate import always resolves to the same (oldest) attachment
				'meta_key'         => Importer::KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- dev-only script: attachments are found by their importer key, one lookup per photo.
				'meta_value'       => 'photo:' . $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- same lookup, by value.
				'suppress_filters' => true,
			)
		);
		return array() === $ids ? 0 : (int) $ids[0];
	}

	/** A core/image block as the block editor saves it (WordPress 6.7), decorative (alt ""). */
	private static function imageBlock( int $id ): string {
		$url = (string) wp_get_attachment_image_url( $id, 'large' );
		return sprintf(
			'<!-- wp:image {"id":%1$d,"sizeSlug":"large","linkDestination":"none"} -->' . "\n"
			. '<figure class="wp-block-image size-large"><img src="%2$s" alt="" class="wp-image-%1$d"/></figure>' . "\n"
			. '<!-- /wp:image -->',
			$id,
			esc_url( $url )
		);
	}
}
