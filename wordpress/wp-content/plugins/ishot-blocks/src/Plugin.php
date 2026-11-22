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

	public static function boot( string $plugin_file ): void {
		self::$plugin_file = $plugin_file;
		add_action( 'init', array( self::class, 'registerBuiltBlocks' ) );
		add_action( 'admin_notices', array( self::class, 'maybeShowBookingNotice' ) );
	}

	/** `init` callback: registers the blocks built into this plugin. */
	public static function registerBuiltBlocks(): void {
		self::registerBlocks( dirname( self::$plugin_file ) . self::BUILD_BLOCKS );
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

		foreach ( $found['found'] as $folder ) {
			$type = register_block_type( $folder );
			if ( $type instanceof \WP_Block_Type ) {
				$registered[] = $type->name;
			} else {
				$missing[] = $folder;
			}
		}

		foreach ( $missing as $folder ) {
			self::reportProblem( $folder );
		}

		return array(
			'registered' => $registered,
			'missing'    => $missing,
		);
	}

	/** `admin_notices` callback. */
	public static function maybeShowBookingNotice(): void {
		self::renderBookingNotice( class_exists( 'IShot\Booking\Plugin' ) );
	}

	/** Prints a notice when the booking plugin is not active. Prints nothing when it is. */
	public static function renderBookingNotice( bool $booking_active ): void {
		if ( $booking_active ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'iShot Blocks works with the iShot Booking plugin. Activate iShot Booking to use the booking blocks.', 'ishot-blocks' ) . '</p></div>';
	}

	/** Logs (WP_DEBUG_LOG) a block folder that could not be registered, and lets others react. */
	private static function reportProblem( string $folder ): void {
		/**
		 * Fires when a folder under build/blocks has no usable block.json.
		 *
		 * @param string $folder Absolute path of the folder.
		 */
		do_action( 'ishot_blocks_block_skipped', $folder );

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- opt-in debug log, no output.
			error_log( 'iShot Blocks: skipped a block without a usable block.json: ' . $folder );
		}
	}
}
