<?php
namespace IShot\Booking\Persistence;

use Throwable;
use wpdb;

/**
 * Classifies the error of the statement a wpdb connection just ran. Uses the MySQL error number
 * first (it does not depend on the server's lc_messages language), then the English message text
 * (what wpdb drop-ins without a mysqli handle, and the test doubles, set).
 */
final class DbError {

	private const LOCK_WAIT_TIMEOUT = 1205;
	private const DEADLOCK          = 1213;
	private const DUPLICATE_KEY     = 1062;

	/** A deadlock victim or a lock wait timeout: the whole transaction is safe to run again. */
	public static function isTransient( wpdb $db ): bool {
		$errno = self::errno( $db );
		if ( self::LOCK_WAIT_TIMEOUT === $errno || self::DEADLOCK === $errno ) {
			return true;
		}
		$error = (string) $db->last_error;
		return str_contains( $error, 'Deadlock found' ) || str_contains( $error, 'Lock wait timeout' );
	}

	/** A unique-key violation (a taken slot unit, or a repeated idempotency key). */
	public static function isDuplicateKey( wpdb $db ): bool {
		return self::DUPLICATE_KEY === self::errno( $db ) || str_contains( (string) $db->last_error, 'Duplicate entry' );
	}

	private static function errno( wpdb $db ): int {
		try {
			$dbh = $db->dbh; // @phpstan-ignore property.protected (wpdb exposes it through __get)
			return $dbh instanceof \mysqli ? (int) $dbh->errno : 0;
		} catch ( Throwable $e ) { // @phpstan-ignore catch.neverThrown (a closed mysqli handle throws on property access)
			return 0; // fall back on the message text
		}
	}
}
