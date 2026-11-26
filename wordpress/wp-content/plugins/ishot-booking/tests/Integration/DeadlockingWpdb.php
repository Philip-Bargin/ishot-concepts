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

	/**
	 * Puts the global session back in autocommit mode, as in production (switching it on also
	 * commits anything left open). After a WP_UnitTestCase has run, that session has autocommit
	 * off, so a plain TestCase's direct writes and reads would stay in an open transaction; if the
	 * connection then outlives the next class's reconnect, its row and metadata locks block that
	 * class (seen 2026-11-26: TRUNCATE in NotificationOutboxTest waited on a transaction left by
	 * HoldExpiryTest). Call from setUp and tearDown of every plain TestCase that writes here.
	 */
	public static function settle(): void {
		global $wpdb;
		$wpdb->query( 'SET autocommit = 1' );
	}

	public static function restore(): void {
		global $wpdb;
		if ( null !== self::$original ) {
			$wpdb           = self::$original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			self::$original = null;
		}
	}

	/** Also make every `SELECT … FOR UPDATE` (the row lock a status change or move takes first) a deadlock victim. */
	public bool $deadlock_row_locks = false;

	/**
	 * Runs once, straight after the ROLLBACK that follows refusal number $after_refusal: stands in
	 * for another request that commits in the gap before the repository's final re-check.
	 *
	 * @var array{0: int, 1: callable}|null
	 */
	public ?array $after_rollback = null;

	/** When set, slot-lock INSERTs run for real (so other statements can be the victims instead). */
	public bool $allow_lock_inserts = false;

	/** Any statement matching this regex is a deadlock victim too (e.g. a DELETE, an UPDATE or COMMIT). */
	public ?string $deadlock_pattern = null;

	/** Any statement matching this regex fails with a non-transient error ($fail_error). */
	public ?string $fail_pattern = null;

	public string $fail_error = 'Simulated failure: table is full';

	/**
	 * Before the first statement matching this regex, the connection is replaced, as wpdb does when
	 * the server has gone away: the statement then runs on a fresh session (autocommit, no transaction).
	 */
	public ?string $reconnect_pattern = null;

	/**
	 * Runs once, straight after the $nth statement matching the regex: stands in for another
	 * request committing at exactly that point.
	 *
	 * @var array{0: string, 1: int, 2: callable}|null
	 */
	public ?array $after_query = null;

	private int $after_query_seen = 0;

	public function query( $query ) {
		if ( ! $this->allow_lock_inserts && is_string( $query ) && 1 === preg_match( '/^\s*INSERT INTO `?' . preg_quote( Schema::locks(), '/' ) . '\b/i', $query ) ) {
			++$this->refused;
			$this->last_error = self::DEADLOCK;
			return false;
		}
		if ( $this->deadlock_row_locks && is_string( $query ) && 1 === preg_match( '/^\s*SELECT\b.*\bFOR UPDATE\s*$/is', $query ) ) {
			++$this->refused;
			$this->last_error = self::DEADLOCK;
			return false;
		}
		if ( null !== $this->deadlock_pattern && is_string( $query ) && 1 === preg_match( $this->deadlock_pattern, $query ) ) {
			++$this->refused;
			$this->last_error = self::DEADLOCK;
			return false;
		}
		if ( null !== $this->fail_pattern && is_string( $query ) && 1 === preg_match( $this->fail_pattern, $query ) ) {
			$this->last_error = $this->fail_error;
			return false;
		}
		if ( null !== $this->reconnect_pattern && is_string( $query ) && 1 === preg_match( $this->reconnect_pattern, $query ) ) {
			$this->reconnect_pattern = null;
			$this->dbh               = null; // drop this copy's handle (the global keeps the old session)
			$this->db_connect( false );
		}
		$result = parent::query( $query );
		if ( null !== $this->after_query && is_string( $query ) && 1 === preg_match( $this->after_query[0], $query ) && ++$this->after_query_seen === $this->after_query[1] ) {
			$callback          = $this->after_query[2];
			$this->after_query = null;
			$callback();
		}
		if ( null !== $this->after_rollback && is_string( $query ) && 'ROLLBACK' === trim( $query ) && $this->refused === $this->after_rollback[0] ) {
			$callback             = $this->after_rollback[1];
			$this->after_rollback = null;
			$callback();
		}
		return $result;
	}
}
