<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Installer;
use IShot\Booking\Persistence\Schema;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InstallerTest extends TestCase {

	protected function tearDown(): void {
		remove_all_filters( 'ishot_booking_innodb_available' );
		Schema::install();
	}

	public function test_innodb_is_detected_on_the_test_database(): void {
		$this->assertTrue( Installer::innodbAvailable() );
	}

	public function test_activation_refuses_when_innodb_is_unavailable(): void {
		add_filter( 'ishot_booking_innodb_available', '__return_false' );

		$this->expectException( RuntimeException::class );
		Installer::activate();
	}

	public function test_upgrade_installs_tables_when_the_stored_schema_version_differs(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . Schema::events() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		update_option( Schema::VERSION_KEY, '0' );

		Installer::maybeUpgrade();

		$this->assertSame( Schema::events(), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::events() ) ) );
		$this->assertSame( Schema::VERSION, get_option( Schema::VERSION_KEY ) );
	}
}
