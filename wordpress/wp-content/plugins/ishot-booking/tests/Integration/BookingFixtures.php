<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Roles;

/** Shared setup for integration tests that need clean booking tables and staff users. */
trait BookingFixtures {

	private BookingRepository $repo;
	/** @var array<string, int> role => user id */
	private array $users = array();

	private function resetBookings(): void {
		global $wpdb;
		Schema::install();
		Roles::install();
		foreach ( Schema::tables() as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		update_option( 'timezone_string', 'Africa/Monrovia' );
		delete_option( 'ishot_booking_settings' );
		$this->repo = new BookingRepository( $wpdb, 30 );
	}

	private function makeStaff(): void {
		foreach ( array( 'ishot_manager', 'ishot_photographer', 'ishot_client' ) as $role ) {
			$this->users[ $role ] = wp_insert_user(
				array(
					'user_login' => uniqid( $role ),
					'user_pass'  => wp_generate_password(),
					'user_email' => uniqid( $role ) . '@example.com',
					'role'       => $role,
				)
			);
		}
	}

	private function removeStaff(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_set_current_user( 0 );
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		$this->users = array();
	}

	private function actAs( ?string $role ): void {
		wp_set_current_user( null === $role ? 0 : $this->users[ $role ] );
	}

	/** Creates a booking at a UTC time, bypassing client policy. */
	private function bookingAt( string $start_utc, array $o = array() ): int {
		$utc   = new DateTimeZone( 'UTC' );
		$begin = new DateTimeImmutable( $start_utc, $utc );
		return $this->repo->create(
			new NewBooking(
				session: new TimeRange( $begin, $begin->modify( '+' . ( $o['minutes'] ?? 60 ) . ' minutes' ) ),
				buffer_before_min: 0,
				buffer_after_min: $o['after'] ?? 0,
				status: $o['status'] ?? BookingStatus::Requested,
				client_name: $o['name'] ?? 'Ada Test',
				client_email: $o['email'] ?? 'ada@example.com',
				service_id: $o['service'] ?? null,
				kind: $o['kind'] ?? 'booking',
				client_user_id: $o['user'] ?? null,
				hold_expires_at: isset( $o['hold'] ) ? new DateTimeImmutable( $o['hold'], $utc ) : null
			)
		);
	}
}
