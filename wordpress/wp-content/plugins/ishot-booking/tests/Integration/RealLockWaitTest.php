<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\CalendarBusy;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\Schema;
use mysqli;
use PHPUnit\Framework\TestCase;

/**
 * Real InnoDB and two real connections, nothing simulated. A second session holds the slot-lock
 * rows a cancel must delete, so the cancel's DELETE genuinely times out (ER_LOCK_WAIT_TIMEOUT).
 * With innodb_rollback_on_timeout OFF (the MySQL/MariaDB default) the server undoes only that
 * statement: if the repository did not check it, the status change would commit without the
 * release, leaving a "cancelled" booking whose time stays locked for good.
 *
 * Plain TestCase: real transactions are under test (as in BookingRepositoryTest).
 */
final class RealLockWaitTest extends TestCase {

	private BookingRepository $repo;
	private ?mysqli $other = null;
	private string $session_timeout = '50';

	protected function setUp(): void {
		DeadlockingWpdb::settle();
		global $wpdb;
		Schema::install();
		foreach ( Schema::tables() as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$this->repo            = new BookingRepository( $wpdb, 30 );
		$this->session_timeout = (string) $wpdb->get_var( 'SELECT @@SESSION.innodb_lock_wait_timeout' );
		$this->other           = self::secondConnection();
	}

	protected function tearDown(): void {
		global $wpdb;
		if ( null !== $this->other ) {
			$this->other->query( 'ROLLBACK' );
			$this->other->close();
			$this->other = null;
		}
		$wpdb->query( $wpdb->prepare( 'SET SESSION innodb_lock_wait_timeout = %d', (int) $this->session_timeout ) );
		DeadlockingWpdb::settle();
	}

	/** Another client of the same database, as a concurrent web request would be. */
	private static function secondConnection(): mysqli {
		global $wpdb;
		$parsed = $wpdb->parse_db_host( DB_HOST );
		$host   = false === $parsed ? DB_HOST : (string) $parsed[0];
		$port   = false === $parsed || null === $parsed[1] ? 3306 : (int) $parsed[1];
		$socket = false === $parsed ? null : $parsed[2];
		$db     = mysqli_init();
		if ( false === $db || ! $db->real_connect( $host, DB_USER, DB_PASSWORD, DB_NAME, $port, $socket ) ) {
			self::fail( 'Could not open a second database connection.' );
		}
		return $db;
	}

	private function booking(): int {
		$utc   = new DateTimeZone( 'UTC' );
		$start = new DateTimeImmutable( '2026-12-14 10:00', $utc );
		return $this->repo->create(
			new NewBooking(
				session: new TimeRange( $start, $start->modify( '+60 minutes' ) ),
				buffer_before_min: 0,
				buffer_after_min: 0,
				status: BookingStatus::Requested,
				client_name: 'Ada Test',
				client_email: 'ada@example.com',
				service_id: 1
			)
		);
	}

	public function test_a_cancel_whose_lock_release_times_out_changes_nothing_and_is_busy(): void {
		global $wpdb;
		$id     = $this->booking();
		$locks  = $this->repo->lockedUnits( 1, '2026-12-14 00:00:00', '2026-12-15 00:00:00' );
		$events = count( $this->repo->events( $id ) );

		// The other session takes the booking's slot rows and keeps them.
		$this->other->query( 'START TRANSACTION' );
		$held = $this->other->query( 'SELECT * FROM ' . Schema::locks() . ' WHERE booking_id = ' . $id . ' FOR UPDATE' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->assertInstanceOf( \mysqli_result::class, $held );
		$this->assertSame( 2, $held->num_rows );

		$wpdb->query( 'SET SESSION innodb_lock_wait_timeout = 50' ); // the server default, as on a real host
		$one = static fn() => 1;
		add_filter( 'ishot_booking_lock_wait_timeout', $one );
		$started = microtime( true );
		try {
			$this->repo->transition( $id, BookingStatus::Cancelled, 1 );
			$this->fail( 'Expected the busy error.' );
		} catch ( CalendarBusy $e ) {
			$this->assertStringContainsString( 'Lock wait timeout', (string) $e->getPrevious()?->getMessage(), 'a genuine 1205 from the server' );
		} finally {
			remove_filter( 'ishot_booking_lock_wait_timeout', $one );
		}
		$elapsed = microtime( true ) - $started;

		$this->assertLessThan( 15, $elapsed, 'three bounded waits, not three 50-second ones' );
		$this->assertSame( '50', (string) $wpdb->get_var( 'SELECT @@SESSION.innodb_lock_wait_timeout' ), 'the session setting is put back' );
		$row = $this->repo->find( $id );
		$this->assertSame( array( 'requested', '1' ), array( $row['status'], $row['version'] ), 'the status change was not kept' );
		$this->assertSame( $locks, $this->repo->lockedUnits( 1, '2026-12-14 00:00:00', '2026-12-15 00:00:00' ) );
		$this->assertCount( $events, $this->repo->events( $id ), 'no audit entry for a change that did not happen' );

		// Once the other session lets go, the same cancel goes through and frees the time.
		$this->other->query( 'ROLLBACK' );
		$this->repo->transition( $id, BookingStatus::Cancelled, 1 );
		$this->assertSame( 'cancelled', $this->repo->find( $id )['status'] );
		$this->assertSame( array(), $this->repo->lockedUnits( 1, '2026-12-14 00:00:00', '2026-12-15 00:00:00' ) );
	}
}
