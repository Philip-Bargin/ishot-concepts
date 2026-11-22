<?php
namespace IShot\Booking\Dev;

use IShot\Booking\Content\Fields;

/**
 * DEV ONLY. Creates published, title-only page shells for the prototype's page routes and one
 * journal post shell, so the local site's routes render through the theme templates (Task 1.4,
 * ruling R-1.4-5) until Stage 3 builds the real pages. Used by wordpress/dev/page-shells-dev.php
 * and nothing else.
 *
 * It fails safe: every DevGuard guard must pass before anything is written, an existing page or
 * post with the same slug (any status) is never touched, and every shell is marked placeholder so
 * the theme shows the "Sample content." notice. Titles are the prototype's own; bodies are empty.
 */
final class PageShellFixtures {

	/**
	 * Slug => title (usePageTitle / navItems at 50e60f4). The booking plugin owns no route among
	 * these (it registers no rewrite rules for /book or /account; /manage-booking/ is not a shell).
	 */
	public const PAGES = array(
		'about'              => 'About',
		'services'           => 'Services',
		'portfolio'          => 'Portfolio',
		'creative-direction' => 'Creative direction',
		'journal'            => 'Journal',
		'contact'            => 'Contact',
		'book'               => 'Book a session',
		'account'            => 'My bookings',
	);

	/** The journal-article state's post (tests/visual/routes.mjs: posts[0] of the prototype's sample content). */
	public const POST = array(
		'slug'  => 'what-to-wear-for-your-portrait-session',
		'title' => 'What to wear for your portrait session',
	);

	/** Journal posts live at /journal/{post} (plan §1 URLs; set for real by Task 3.1). */
	public const PERMALINKS = '/journal/%postname%/';

	/** The wp-env default structure, the only one this fixture replaces. */
	private const DEFAULT_PERMALINKS = '/%postname%/';

	/** Marks a shell as fixture-made, so it can be told apart from owner content later. */
	public const MARKER = '_ishot_dev_shell';

	/**
	 * The only entry point. It reads the guard inputs itself; callers cannot supply them.
	 *
	 * @return array{ok: bool, reason: string, created: list<string>, skipped: list<string>, permalinks: string}
	 */
	public static function run(): array {
		return self::execute(
			wp_get_environment_type(),
			defined( 'ISHOT_DEV_FIXTURES' ) ? constant( 'ISHOT_DEV_FIXTURES' ) : null,
			home_url()
		);
	}

	/**
	 * Private on purpose: the guard values must come from run(). Tests reach it by reflection.
	 *
	 * @param string $environment_type wp_get_environment_type().
	 * @param mixed  $opt_in           The value of ISHOT_DEV_FIXTURES, or null when undefined.
	 * @param string $home_url         home_url().
	 * @return array{ok: bool, reason: string, created: list<string>, skipped: list<string>, permalinks: string}
	 */
	private static function execute( string $environment_type, mixed $opt_in, string $home_url ): array {
		$result = array(
			'ok'         => false,
			'reason'     => DevGuard::blocked( $environment_type, $opt_in, $home_url ),
			'created'    => array(),
			'skipped'    => array(),
			'permalinks' => '',
		);
		if ( '' !== $result['reason'] ) {
			return $result;
		}
		$result['ok'] = true;

		foreach ( self::PAGES as $slug => $title ) {
			if ( self::exists( $slug, 'page' ) ) {
				$result['skipped'][] = $slug;
			} elseif ( self::create( 'page', $slug, $title ) ) {
				$result['created'][] = $slug;
			}
		}
		if ( self::exists( self::POST['slug'], 'post' ) ) {
			$result['skipped'][] = self::POST['slug'];
		} elseif ( self::create( 'post', self::POST['slug'], self::POST['title'] ) ) {
			$result['created'][] = self::POST['slug'];
		}

		$result['permalinks'] = self::permalinks();
		return $result;
	}

	/** Any post of the type with that slug, whatever its status (trash included). */
	private static function exists( string $slug, string $type ): bool {
		$found = get_posts(
			array(
				'name'             => $slug,
				'post_type'        => $type,
				'post_status'      => array_keys( get_post_stati() ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		return array() !== $found;
	}

	private static function create( string $type, string $slug, string $title ): bool {
		$id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => '',
				'meta_input'   => array(
					'content_status' => Fields::PLACEHOLDER,
					self::MARKER     => 1,
				),
			),
			true
		);
		return is_int( $id ) && $id > 0;
	}

	/** Sets /journal/%postname%/ over the wp-env default only; any other structure is the owner's. */
	private static function permalinks(): string {
		global $wp_rewrite;
		$current = (string) get_option( 'permalink_structure' );
		if ( self::PERMALINKS === $current ) {
			return 'unchanged (' . self::PERMALINKS . ')';
		}
		if ( self::DEFAULT_PERMALINKS !== $current ) {
			return sprintf( 'kept the existing structure "%s"; journal posts are not under /journal/', $current );
		}
		$wp_rewrite->set_permalink_structure( self::PERMALINKS );
		flush_rewrite_rules( false );
		return 'set to ' . self::PERMALINKS;
	}
}
