<?php
namespace IShot\Blocks;

/**
 * Wires the plugin into WordPress: registers every built block and warns when the booking
 * plugin it depends on is not active.
 *
 * Blocks are registered from this plugin's own build/blocks folder only, never from the theme,
 * so page content keeps rendering whichever theme is active.
 */
final class Plugin {

	/** Folder name inside the plugin that holds the compiled blocks. */
	private const BUILD_BLOCKS = '/build/blocks';

	private static string $plugin_file = '';

	/**
	 * Block folders that could not be registered in this request: folder => reason. Registration
	 * runs on `init`, before `admin_notices`, so the notice can read this list.
	 *
	 * @var array<string, string>
	 */
	private static array $skipped = array();

	/**
	 * Names of the blocks this plugin has registered, so a second registration in the same request
	 * (for instance `init` firing again) is not reported as a problem.
	 *
	 * @var array<string, true>
	 */
	private static array $ours = array();

	public static function boot( string $plugin_file ): void {
		self::$plugin_file = $plugin_file;
		add_action( 'init', array( self::class, 'registerBuiltBlocks' ) );
		add_action( 'admin_notices', array( self::class, 'maybeShowBookingNotice' ) );
		add_action( 'admin_notices', array( self::class, 'renderSkippedNotice' ) );
	}

	/** Absolute path of the compiled blocks folder, always inside this plugin (never a theme). */
	public static function buildDirectory(): string {
		return dirname( self::$plugin_file ) . self::BUILD_BLOCKS;
	}

	/** `init` callback: registers the blocks built into this plugin. */
	public static function registerBuiltBlocks(): void {
		self::registerBlocks( self::buildDirectory() );
	}

	/**
	 * Splits the folders under a build/blocks directory into those with a block.json and those
	 * without one (a build that did not finish). Pure filesystem logic, no WordPress needed.
	 *
	 * @param string $blocks_dir Directory holding one sub-folder per built block.
	 * @return array{found: list<string>, missing: list<string>}
	 */
	public static function discover( string $blocks_dir ): array {
		$result  = array(
			'found'   => array(),
			'missing' => array(),
		);
		$folders = glob( rtrim( $blocks_dir, '/\\' ) . '/*', GLOB_ONLYDIR );
		if ( false === $folders ) {
			return $result;
		}
		sort( $folders );
		foreach ( $folders as $folder ) {
			$key              = file_exists( $folder . '/block.json' ) ? 'found' : 'missing';
			$result[ $key ][] = $folder;
		}
		return $result;
	}

	/**
	 * Registers every `<blocks_dir>/<name>/block.json`. A folder without a block.json (or one
	 * WordPress rejects) is skipped and reported, never fatal.
	 *
	 * @param string $blocks_dir Directory holding one sub-folder per built block.
	 * @return array{registered: list<string>, missing: list<string>}
	 */
	public static function registerBlocks( string $blocks_dir ): array {
		$found      = self::discover( $blocks_dir );
		$registered = array();
		$missing    = $found['missing'];

		foreach ( $missing as $folder ) {
			self::reportProblem( $folder, 'it has no block.json (the build is incomplete)' );
		}

		foreach ( $found['found'] as $folder ) {
			if ( self::alreadyRegisteredByUs( $folder ) ) {
				continue;
			}
			$type = register_block_type( $folder );
			if ( $type instanceof \WP_Block_Type ) {
				$registered[]              = $type->name;
				self::$ours[ $type->name ] = true;
			} else {
				$missing[] = $folder;
				self::reportProblem( $folder, 'WordPress rejected its block.json (invalid, or the block name is already registered)' );
			}
		}

		return array(
			'registered' => $registered,
			'missing'    => $missing,
		);
	}

	/** True when the block in this folder was already registered by this plugin and is still registered. */
	private static function alreadyRegisteredByUs( string $folder ): bool {
		$meta = wp_json_file_decode( $folder . '/block.json', array( 'associative' => true ) );
		$name = is_array( $meta ) && isset( $meta['name'] ) && is_string( $meta['name'] ) ? $meta['name'] : '';
		return '' !== $name && isset( self::$ours[ $name ] ) && \WP_Block_Type_Registry::get_instance()->is_registered( $name );
	}

	/** Forgets the skipped-block list (used by tests). */
	public static function forgetSkipped(): void {
		self::$skipped = array();
	}

	/** `admin_notices` callback: tells administrators which blocks were not registered. */
	public static function renderSkippedNotice(): void {
		if ( array() === self::$skipped || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$items = '';
		foreach ( self::$skipped as $folder => $reason ) {
			/* translators: 1: block folder name, 2: reason */
			$items .= '<li>' . esc_html( sprintf( __( '%1$s: could not be registered because %2$s.', 'ishot-blocks' ), basename( $folder ), $reason ) ) . '</li>';
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'iShot Blocks: some blocks could not be registered, so pages using them show "unsupported block". Rebuild the plugin (npm run build:blocks) and redeploy build/.', 'ishot-blocks' ) . '</p><ul>' . $items . '</ul></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $items is built from esc_html() output only.
	}

	/** `admin_notices` callback: shows the booking-plugin notice when ishot-booking is not loaded. */
	public static function maybeShowBookingNotice(): void {
		self::renderBookingNotice( class_exists( 'IShot\Booking\Plugin' ) );
	}

	/** Prints a notice when the booking plugin is not active. Prints nothing when it is. */
	public static function renderBookingNotice( bool $booking_active ): void {
		if ( $booking_active || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'iShot Blocks works with the iShot Booking plugin. Activate iShot Booking to use the booking blocks.', 'ishot-blocks' ) . '</p></div>';
	}

	/** Remembers (for the admin notice), logs (WP_DEBUG_LOG) and announces a block folder that could not be registered. */
	private static function reportProblem( string $folder, string $reason ): void {
		self::$skipped[ $folder ] = $reason;

		/**
		 * Fires when a folder under build/blocks could not be registered.
		 *
		 * @param string $folder Absolute path of the folder.
		 * @param string $reason Why it could not be registered.
		 */
		do_action( 'ishot_blocks_block_skipped', $folder, $reason );

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- opt-in debug log, no output.
			error_log( 'iShot Blocks: the block in ' . $folder . ' could not be registered because ' . $reason . '.' );
		}
	}
}
