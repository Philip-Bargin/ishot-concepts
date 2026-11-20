<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Roles;
use PHPUnit\Framework\TestCase;

final class RolesTest extends TestCase {

	protected function setUp(): void {
		Roles::install();
	}

	public function test_manager_can_do_everything_booking_related(): void {
		$role = get_role( 'ishot_manager' );
		foreach ( Roles::ALL_CAPS as $cap ) {
			$this->assertTrue( $role->has_cap( $cap ), $cap );
		}
	}

	public function test_administrators_get_every_booking_capability(): void {
		$role = get_role( 'administrator' );
		foreach ( Roles::ALL_CAPS as $cap ) {
			$this->assertTrue( $role->has_cap( $cap ), $cap );
		}
	}

	public function test_photographer_works_bookings_but_cannot_cancel_reschedule_or_change_settings(): void {
		$role = get_role( 'ishot_photographer' );
		$this->assertTrue( $role->has_cap( 'view_ishot_calendar' ) );
		$this->assertTrue( $role->has_cap( 'edit_ishot_bookings' ) );
		$this->assertTrue( $role->has_cap( 'block_ishot_time' ) );
		$this->assertFalse( $role->has_cap( 'manage_ishot_bookings' ) );
		$this->assertFalse( $role->has_cap( 'manage_ishot_settings' ) );
		$this->assertFalse( $role->has_cap( 'manage_ishot_availability' ) );
	}

	public function test_client_can_only_read_their_own_bookings(): void {
		$caps = array_keys( array_filter( get_role( 'ishot_client' )->capabilities ) );
		sort( $caps );
		$this->assertSame( array( 'read', 'read_own_ishot_bookings' ), $caps );
	}
}
