<?php
namespace IShot\Booking;

use IShot\Booking\Admin\ActionHandler;
use IShot\Booking\Admin\DashboardWidget;
use IShot\Booking\Admin\EmailSettingsScreen;
use IShot\Booking\Calendar\CalendarFeed;
use IShot\Booking\Admin\Menu;
use IShot\Booking\Admin\SettingsScreen;
use IShot\Booking\Application\HoldExpiry;
use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Contact\Enquiries;
use IShot\Booking\Content\ContentAdmin;
use IShot\Booking\Content\ContentTypes;
use IShot\Booking\Content\Fields;
use IShot\Booking\Content\ImportCommand;
use IShot\Booking\Notifications\Outbox;
use IShot\Booking\Notifications\Queue;
use IShot\Booking\Privacy\PersonalData;
use IShot\Booking\Privacy\Retention;
use IShot\Booking\Rest\ClientController;
use IShot\Booking\Rest\ContactController;
use IShot\Booking\Rest\MyBookingsController;
use IShot\Booking\Rest\PublicController;
use IShot\Booking\Rest\StaffController;

/** Wires the plugin into WordPress. */
final class Plugin {

	public static function boot( string $plugin_file ): void {
		register_activation_hook( $plugin_file, array( Installer::class, 'onActivate' ) );
		add_action( 'plugins_loaded', array( Installer::class, 'maybeUpgrade' ) );
		register_deactivation_hook( $plugin_file, array( HoldExpiry::class, 'unschedule' ) );
		register_deactivation_hook( $plugin_file, array( Queue::class, 'unschedule' ) );
		register_deactivation_hook( $plugin_file, array( Retention::class, 'unschedule' ) );

		// Notifications: the outbox is filled inside booking transactions; these deliver it.
		add_action( Outbox::QUEUED_HOOK, array( Queue::class, 'kick' ) );
		add_action( Queue::SEND_HOOK, array( Queue::class, 'send' ) );
		add_action( Queue::TICK_HOOK, array( Queue::class, 'tick' ) );
		add_action( 'action_scheduler_init', array( Queue::class, 'ensureScheduled' ) );
		add_action( Enquiries::SEND_HOOK, array( Enquiries::class, 'run' ) );
		add_action( 'after_password_reset', array( Accounts\ClientAccounts::class, 'onPasswordReset' ) );

		// Manage-booking links: /manage-booking/#token=… (see Frontend\ManageUrl).
		add_action( 'template_redirect', array( Frontend\ManageUrl::class, 'redirectLegacy' ), 1 );
		add_action( 'send_headers', array( Frontend\ManageUrl::class, 'protectManagePage' ) );
		add_action( 'init', array( ServiceCatalog::class, 'register' ) );

		// Content: projects, testimonials, categories (and every content field, services included).
		add_action( 'init', array( ContentTypes::class, 'register' ) );
		add_action( 'init', array( self::class, 'maybeFlushRewrites' ), 20 );
		add_action( 'wp_after_insert_post', array( ContentTypes::class, 'afterInsert' ), 10, 2 );
		add_filter( 'is_protected_meta', array( self::class, 'protectContentMeta' ), 10, 2 );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'ishot import-prototype', ImportCommand::class );
		}

		// Privacy: Tools → Export/Erase Personal Data, and the daily retention purge.
		PersonalData::register();
		add_action( 'action_scheduler_init', array( Retention::class, 'ensureScheduled' ) );
		add_action( Retention::HOOK, array( Retention::class, 'run' ) );

		add_filter( 'cron_schedules', array( HoldExpiry::class, 'addInterval' ) ); // phpcs:ignore WordPress.WP.CronInterval -- five minutes is intended.
		add_action( 'init', array( HoldExpiry::class, 'ensureScheduled' ) );
		add_action( HoldExpiry::HOOK, array( HoldExpiry::class, 'sweep' ) );
		add_action(
			'rest_api_init',
			static function () {
				( new PublicController() )->register();
				( new StaffController() )->register();
				( new ClientController() )->register();
				( new MyBookingsController() )->register();
				( new ContactController() )->register();
				( new CalendarFeed() )->register();
			}
		);
		add_filter( 'rest_pre_serve_request', array( CalendarFeed::class, 'serveRaw' ), 10, 3 );

		if ( is_admin() ) {
			ContentAdmin::register();
			add_action( 'admin_init', array( Admin\ListScreen::class, 'handleBulk' ) );
			add_action( 'admin_post_' . Admin\CsvExport::POST, array( Admin\CsvExport::class, 'handle' ) );
			add_action( 'admin_menu', array( Menu::class, 'register' ) );
			add_action( 'admin_enqueue_scripts', array( Menu::class, 'enqueue' ) );
			add_action( 'wp_dashboard_setup', array( DashboardWidget::class, 'register' ) );
			add_action( 'admin_post_' . ActionHandler::POST_ACTION, array( ActionHandler::class, 'handleAction' ) );
			add_action( 'admin_post_' . ActionHandler::POST_BLOCK, array( ActionHandler::class, 'handleBlock' ) );
			add_action( 'admin_post_' . ActionHandler::POST_UNBLOCK, array( ActionHandler::class, 'handleUnblock' ) );
			add_action( 'admin_post_' . SettingsScreen::POST_HOURS, array( SettingsScreen::class, 'handleHours' ) );
			add_action( 'admin_post_' . SettingsScreen::POST_POLICY, array( SettingsScreen::class, 'handlePolicy' ) );
			add_action( 'admin_post_' . EmailSettingsScreen::POST_SAVE, array( EmailSettingsScreen::class, 'handleSave' ) );
			add_action( 'admin_post_' . EmailSettingsScreen::POST_FEED, array( EmailSettingsScreen::class, 'handleFeedKey' ) );
		}
	}

	/** Bump when a rewrite slug changes; the rules are rebuilt once, after every type is registered. */
	private const REWRITE_VERSION = '1';

	public static function maybeFlushRewrites(): void {
		if ( get_option( 'ishot_booking_rewrite_version' ) !== self::REWRITE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'ishot_booking_rewrite_version', self::REWRITE_VERSION, false );
		}
	}

	/** Content fields are edited in the Details box, not the raw Custom Fields panel (which skips validation). */
	public static function protectContentMeta( bool $protected, string $meta_key ): bool {
		static $keys = null;
		$keys      ??= array_keys( array_merge( ...array_map( array( Fields::class, 'for' ), ContentAdmin::TYPES ) ) );
		return $protected || in_array( $meta_key, $keys, true );
	}
}
