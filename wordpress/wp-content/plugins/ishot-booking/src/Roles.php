<?php
namespace IShot\Booking;

/**
 * Studio roles and booking capabilities.
 *
 * | Capability                  | Grants                                                         |
 * |-----------------------------|----------------------------------------------------------------|
 * | view_ishot_calendar         | See the calendar, booking list and booking detail              |
 * | edit_ishot_bookings         | Confirm, complete, mark no-show                                |
 * | block_ishot_time            | Add and remove staff time blocks                               |
 * | manage_ishot_bookings       | Deposits, cancel, reschedule                                   |
 * | manage_ishot_availability   | Opening hours and exceptions                                   |
 * | manage_ishot_settings       | Booking policy settings                                        |
 * | edit_ishot_services         | Services and prices                                            |
 * | edit_ishot_content          | Projects, testimonials and portfolio categories                |
 * | read_own_ishot_bookings     | A client's own bookings ("My bookings")                        |
 */
final class Roles {

	public const VERSION     = '2';
	public const VERSION_KEY = 'ishot_booking_roles_version';

	public const STAFF_CAPS = array( 'view_ishot_calendar', 'edit_ishot_bookings', 'block_ishot_time' );

	public const MANAGER_CAPS = array( 'manage_ishot_bookings', 'manage_ishot_availability', 'manage_ishot_settings', 'edit_ishot_services', 'edit_ishot_content' );

	public const ALL_CAPS = array( ...self::STAFF_CAPS, ...self::MANAGER_CAPS );

	/** Creates or refreshes the roles. Safe to run repeatedly. */
	public static function install(): void {
		$grant = static fn( array $caps ) => array_fill_keys( $caps, true );

		self::replace( 'ishot_manager', __( 'Studio manager', 'ishot-booking' ), $grant( array( 'read', 'upload_files', ...self::ALL_CAPS ) ) );
		self::replace( 'ishot_photographer', __( 'Photographer', 'ishot-booking' ), $grant( array( 'read', ...self::STAFF_CAPS ) ) );
		self::replace( 'ishot_client', __( 'Client', 'ishot-booking' ), $grant( array( 'read', 'read_own_ishot_bookings' ) ) );

		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			foreach ( self::ALL_CAPS as $cap ) {
				$admin->add_cap( $cap );
			}
		}
		update_option( self::VERSION_KEY, self::VERSION, false );
	}

	public static function maybeUpgrade(): void {
		if ( get_option( self::VERSION_KEY ) !== self::VERSION ) {
			self::install();
		}
	}

	/** @param array<string, bool> $caps */
	private static function replace( string $role, string $label, array $caps ): void {
		remove_role( $role );
		add_role( $role, $label, $caps );
	}
}
