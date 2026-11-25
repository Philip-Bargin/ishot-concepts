<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Persistence\Schema;
use wpdb;

/**
 * The test's own wpdb connection, except that slot-lock INSERTs always come back as an InnoDB
 * deadlock victim. Reproduces, deterministically and in one process, what heavy contention
 * occasionally does in ConcurrencyTest: every retry of a transaction loses a deadlock, so the
 * repository exhausts its attempts. Every other query runs for real.
 *
 * It shares the global connection (same MySQL session) rather than opening a second one: once a
 * WP_UnitTestCase has run, that session has autocommit off, so a second connection would not see
 * the options and posts a test has just written.
 */
final class DeadlockingWpdb extends wpdb {

	public const DEADLOCK = 'Deadlock found when trying to get lock; try restarting transaction';

	/** Slot-lock inserts that were refused. */
	public int $refused = 0;

	private static ?wpdb $original = null;

	public static function connect(): self {
		global $wpdb;
		$wpdb->flush(); // frees the pending result set, so neither copy frees it under the other
		$db = ( new \ReflectionClass( self::class ) )->newInstanceWithoutConstructor();
		foreach ( get_object_vars( $wpdb ) as $name => $value ) { // this scope sees wpdb's protected state, the connection handle included
			$db->$name = $value;
		}
		return $db;
	}

	/** Makes this connection the global $wpdb (for code that reads the global, e.g. REST controllers). */
	public static function swapIn(): self {
		global $wpdb;
		self::$original = $wpdb;
		$wpdb           = self::connect(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		return $wpdb;
	}

	public static function restore(): void {
		global $wpdb;
		if ( null !== self::$original ) {
			$wpdb           = self::$original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			self::$original = null;
		}
	}

	public function query( $query ) {
		if ( is_string( $query ) && 1 === preg_match( '/^\s*INSERT INTO `?' . preg_quote( Schema::locks(), '/' ) . '\b/i', $query ) ) {
			++$this->refused;
			$this->last_error = self::DEADLOCK;
			return false;
		}
		return parent::query( $query );
	}
}
