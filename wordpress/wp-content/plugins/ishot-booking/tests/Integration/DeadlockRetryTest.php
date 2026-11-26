<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Notifications\Outbox;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\CalendarBusy;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Persistence\SlotConflict;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * What a request ends in when every attempt of its transaction loses an InnoDB deadlock
 * (the repository's retries are exhausted). CI run 45545691308 hit this in ConcurrencyTest:
 * a racer whose slot was already taken got a "calendar is busy" fatal instead of SlotConflict.
 *
 * A loser whose units are held by a committed booking must always get SlotConflict; the
 * "busy" error is only for a slot that is genuinely still free. Plain TestCase for the same
 * reason as BookingRepositoryTest: real InnoDB transactions are under test.
 */
final class DeadlockRetryTest extends TestCase {

	private BookingRepository $repo;
	private DeadlockingWpdb $deadlocking;
	private BookingRepository $contended;

	protected function setUp(): void {
		DeadlockingWpdb::settle();
		global $wpdb;
		Schema::install();
		foreach ( Schema::tables() as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$this->repo        = new BookingRepository( $wpdb, 30 );
		$this->deadlocking = DeadlockingWpdb::connect();
		$this->contended   = new BookingRepository( $this->deadlocking, 30 );
	}

	/**
	 * PHPUnit keeps finished test objects alive. The double shares the global mysqli handle, so
	 * holding it would keep that connection (and any transaction left open on it) alive after a
	 * later WP_UnitTestCase reconnects $wpdb, blocking that test's inserts on lock waits.
	 */
	protected function tearDown(): void {
		unset( $this->contended, $this->deadlocking );
		DeadlockingWpdb::settle();
	}

	private static function utc( string $time ): DateTimeImmutable {
		return new DateTimeImmutable( $time, new DateTimeZone( 'UTC' ) );
	}

	private static function draft( string $start, string $end, int $after = 0 ): NewBooking {
		return new NewBooking(
			session: new TimeRange( self::utc( $start ), self::utc( $end ) ),
			buffer_before_min: 0,
			buffer_after_min: $after,
			status: BookingStatus::Requested,
			client_name: 'Ada Test',
			client_email: 'ada@example.com',
			service_id: 1
		);
	}

	private static function rows( string $table ): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** @return array{bookings: int, locks: int, events: int, notifications: int, queued_hook: int} */
	private static function snapshot(): array {
		return array(
			'bookings'      => self::rows( Schema::bookings() ),
			'locks'         => self::rows( Schema::locks() ),
			'events'        => self::rows( Schema::events() ),
			'notifications' => self::rows( Schema::notifications() ),
			'queued_hook'   => did_action( Outbox::QUEUED_HOOK ),
		);
	}

	public function test_exhausted_retries_on_a_taken_slot_end_in_slot_conflict(): void {
		$this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', 30 ) ); // holds 10:00, 10:30, 11:00
		$before = self::snapshot();

		try {
			$this->contended->create( self::draft( '2026-12-14 11:00', '2026-12-14 12:00' ) ); // overlaps 11:00 only
			$this->fail( 'Expected a SlotConflict.' );
		} catch ( SlotConflict $e ) {
			// expected
		}

		$this->assertSame( 3, $this->deadlocking->refused, 'every attempt must have lost its deadlock' );
		$this->assertSame( $before, self::snapshot(), 'rolled-back attempts leave no rows and fire no queued-email hook' );
	}

	public function test_exhausted_retries_on_a_free_slot_stay_a_busy_error(): void {
		$this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', 30 ) );
		$before = self::snapshot();

		try {
			$this->contended->create( self::draft( '2026-12-14 14:00', '2026-12-14 15:00' ) );
			$this->fail( 'Expected the busy error.' );
		} catch ( SlotConflict $e ) {
			$this->fail( 'A free slot must not be reported as taken.' );
		} catch ( CalendarBusy $e ) {
			$this->assertStringContainsString( 'calendar is busy', $e->getMessage() );
		}

		$this->assertSame( 3, $this->deadlocking->refused );
		$this->assertSame( $before, self::snapshot() );
	}

	public function test_exhausted_retries_rescheduling_onto_a_taken_time_end_in_slot_conflict(): void {
		$a = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', 30 ) );
		$this->repo->create( self::draft( '2026-12-14 14:00', '2026-12-14 15:00' ) );
		$before = self::snapshot();

		try {
			$this->contended->reschedule( $a, self::utc( '2026-12-14 13:30' ), 1 ); // needs 13:30–15:00
			$this->fail( 'Expected a SlotConflict.' );
		} catch ( SlotConflict $e ) {
			// expected
		}

		$this->assertSame( 3, $this->deadlocking->refused );
		$this->assertSame( $before, self::snapshot() );
		$this->assertSame( '2026-12-14 10:00:00', $this->repo->find( $a )['start_utc'] );
		$this->assertSame( '1', $this->repo->find( $a )['version'] );
	}

	public function test_exhausted_retries_rescheduling_over_its_own_old_time_stay_a_busy_error(): void {
		$a      = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', 30 ) ); // holds 10:00–11:30
		$before = self::snapshot();

		try {
			$this->contended->reschedule( $a, self::utc( '2026-12-14 10:30' ), 1 ); // 10:30–12:00: only its own units are held
			$this->fail( 'Expected the busy error.' );
		} catch ( SlotConflict $e ) {
			$this->fail( 'A booking\'s own units must not count as taken.' );
		} catch ( CalendarBusy $e ) {
			$this->assertStringContainsString( 'calendar is busy', $e->getMessage() );
		}

		$this->assertSame( 3, $this->deadlocking->refused );
		$this->assertSame( $before, self::snapshot() );
	}

	/**
	 * A double-submitted request (same idempotency key) whose twin commits while this one is
	 * losing deadlocks must get the twin's booking back, not a 409 for a booking that succeeded.
	 */
	public function test_exhausted_retries_return_the_booking_a_same_key_twin_committed_meanwhile(): void {
		$draft                         = self::draft( '2026-12-14 10:00', '2026-12-14 11:00' );
		$keyed                         = new NewBooking(
			session: $draft->session,
			buffer_before_min: 0,
			buffer_after_min: 0,
			status: BookingStatus::Requested,
			client_name: 'Ada Test',
			client_email: 'ada@example.com',
			service_id: 1,
			idempotency_key: 'twin-key-0001'
		);
		$twin                          = null;
		$this->deadlocking->after_rollback = array(
			3,
			function () use ( $keyed, &$twin ): void {
				$twin = $this->repo->create( $keyed ); // commits on the shared session after the last rollback
			},
		);

		$id = $this->contended->create( $keyed );

		$this->assertNotNull( $twin, 'the twin must have committed during the test' );
		$this->assertSame( $twin, $id, 'the retry gets the twin\'s booking' );
		$this->assertSame( 1, self::rows( Schema::bookings() ), 'still exactly one booking' );
	}

	/**
	 * The row lock a reschedule takes first can itself lose a deadlock. That is "busy", not
	 * "booking does not exist", and nothing may change.
	 */
	public function test_a_row_lock_that_keeps_losing_deadlocks_is_a_busy_error_not_a_missing_booking(): void {
		$a                                     = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
		$before                                = self::snapshot();
		$this->deadlocking->deadlock_row_locks = true;

		try {
			$this->contended->reschedule( $a, self::utc( '2026-12-14 15:00' ), 1 );
			$this->fail( 'Expected the busy error.' );
		} catch ( CalendarBusy $e ) {
			$this->assertSame( 3, $this->deadlocking->refused, 'the row lock is retried like any other deadlock' );
		}

		$this->assertSame( $before, self::snapshot() );
		$this->assertSame( '2026-12-14 10:00:00', $this->repo->find( $a )['start_utc'] );
	}

	private static function keyed( string $key ): NewBooking {
		$draft = self::draft( '2026-12-14 10:00', '2026-12-14 11:00' );
		return new NewBooking(
			session: $draft->session,
			buffer_before_min: 0,
			buffer_after_min: 0,
			status: BookingStatus::Requested,
			client_name: 'Ada Test',
			client_email: 'ada@example.com',
			service_id: 1,
			idempotency_key: $key
		);
	}

	/**
	 * The RuntimeException $call throws, or null if it returns. (Not a try/catch around fail():
	 * PHPUnit's own assertion failures are RuntimeExceptions too.)
	 */
	private static function errorFrom( callable $call ): ?RuntimeException {
		try {
			$call();
		} catch ( RuntimeException $e ) {
			return $e;
		}
		return null;
	}

	private static function table( string $table ): string {
		return '/^\s*(INSERT INTO|UPDATE|DELETE FROM) `?' . preg_quote( $table, '/' ) . '\b/i';
	}

	/**
	 * After a deadlock InnoDB has already rolled the transaction back; a write that is not checked
	 * lets the rest commit on its own. Here the lock release of a cancel is the victim: the cancel
	 * must not be saved (the booking would read "cancelled" while its time stayed locked for good).
	 */
	public function test_a_cancel_whose_lock_release_keeps_losing_deadlocks_changes_nothing(): void {
		$a      = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
		$before = self::snapshot();
		$this->deadlocking->deadlock_pattern = '/^\s*DELETE FROM `?' . preg_quote( Schema::locks(), '/' ) . '\b/i';

		try {
			$this->contended->transition( $a, BookingStatus::Cancelled, 1 );
			$this->fail( 'Expected the busy error.' );
		} catch ( CalendarBusy $e ) {
			$this->assertSame( 3, $this->deadlocking->refused, 'each attempt was retried' );
		}

		$this->assertSame( $before, self::snapshot() );
		$this->assertSame( array( 'requested', '1' ), array( $this->repo->find( $a )['status'], $this->repo->find( $a )['version'] ) );
	}

	/** The same for a move: the booking row update is the victim, after the new locks were taken. */
	public function test_a_move_whose_booking_update_keeps_losing_deadlocks_changes_nothing(): void {
		$a                                     = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
		$before                                = self::snapshot();
		$this->deadlocking->allow_lock_inserts = true;
		$this->deadlocking->deadlock_pattern   = '/^\s*UPDATE `?' . preg_quote( Schema::bookings(), '/' ) . '\b/i';

		try {
			$this->contended->reschedule( $a, self::utc( '2026-12-14 15:00' ), 1 );
			$this->fail( 'Expected the busy error.' );
		} catch ( CalendarBusy $e ) {
			$this->assertSame( 3, $this->deadlocking->refused );
		}

		$this->assertSame( $before, self::snapshot() );
		$this->assertSame( '2026-12-14 10:00:00', $this->repo->find( $a )['start_utc'] );
	}

	/** A COMMIT that fails (e.g. a cluster certification failure) is never reported as saved. */
	public function test_a_commit_that_keeps_failing_is_never_reported_as_saved(): void {
		$before                                = self::snapshot();
		$this->deadlocking->allow_lock_inserts = true;
		$this->deadlocking->deadlock_pattern   = '/^\s*COMMIT\s*$/i';

		try {
			$this->contended->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
			$this->fail( 'Expected the busy error.' );
		} catch ( CalendarBusy $e ) {
			$this->assertSame( 3, $this->deadlocking->refused );
		}

		$this->assertSame( $before, self::snapshot() );
	}

	/** Any other write failure is not retried and not dressed up as "busy": it is a real error, and nothing is kept. */
	public function test_a_non_transient_write_failure_is_an_error_and_nothing_is_kept(): void {
		$before                                = self::snapshot();
		$this->deadlocking->allow_lock_inserts = true;
		$this->deadlocking->fail_pattern       = self::table( Schema::events() );

		$error = self::errorFrom( fn() => $this->contended->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) ) );

		$this->assertNotNull( $error, 'expected an error, not a saved booking' );
		$this->assertNotInstanceOf( CalendarBusy::class, $error, 'a non-transient failure is not "busy"' );
		$this->assertNotInstanceOf( SlotConflict::class, $error );
		$this->assertStringContainsString( 'Could not record the change', $error->getMessage() );
		$this->assertSame( 0, $this->deadlocking->refused );
		$this->assertSame( $before, self::snapshot() );
	}

	/**
	 * wpdb reconnects when the server goes away and re-runs the statement on the new session, which
	 * has no transaction. The booking must not be reported as saved, and the lock rows that the
	 * re-run statement committed on its own are freed by the sweep's safety net.
	 */
	public function test_a_connection_lost_mid_booking_is_never_reported_as_saved(): void {
		global $wpdb;
		$this->deadlocking->allow_lock_inserts = true;
		$this->deadlocking->reconnect_pattern  = self::table( Schema::locks() );

		$error = self::errorFrom( fn() => $this->contended->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) ) );
		$wpdb->query( 'ROLLBACK' ); // what the server does to the abandoned session

		$this->assertNotNull( $error, 'expected an error, not a saved booking' );
		$this->assertNotInstanceOf( CalendarBusy::class, $error, 'a lost connection is neither busy…' );
		$this->assertNotInstanceOf( SlotConflict::class, $error, '…nor taken' );
		$this->assertStringContainsString( 'connection was lost', $error->getMessage() );
		$this->assertSame( 0, self::rows( Schema::bookings() ), 'nothing was saved' );
		$this->assertSame( 2, self::rows( Schema::locks() ), 'the re-run lock insert committed on its own' );
		$this->assertSame( 2, $this->repo->releaseOrphanLocks() );
		$this->assertSame( 0, self::rows( Schema::locks() ), 'the time is bookable again' );
	}

	/**
	 * A same-key twin that commits after this request's twin lookup but before its lock count must
	 * still be returned: a 409 here would tell a client whose booking succeeded to pick another time.
	 */
	public function test_a_twin_committing_between_the_lookup_and_the_lock_count_is_returned(): void {
		$keyed                          = self::keyed( 'twin-key-0002' );
		$twin                           = null;
		$this->deadlocking->after_query = array(
			'/WHERE idempotency_key = /',
			2, // 1: create()'s own pre-check; 2: the lookup after the last rollback
			function () use ( $keyed, &$twin ): void {
				$twin = $this->repo->create( $keyed );
			},
		);

		$id = $this->contended->create( $keyed );

		$this->assertNotNull( $twin, 'the twin must have committed during the test' );
		$this->assertSame( $twin, $id );
		$this->assertSame( 1, self::rows( Schema::bookings() ) );
	}

	/**
	 * wpdb's silent reconnect would re-run a failed statement outside the transaction (a booking row
	 * without its locks). It is off while a change runs and put back afterwards, also after a failure.
	 */
	public function test_wpdb_does_not_reconnect_in_the_middle_of_a_change(): void {
		$before = $this->deadlocking->reconnect_retries;
		$during = null;
		$this->deadlocking->allow_lock_inserts = true;
		$this->deadlocking->after_query        = array(
			self::table( Schema::bookings() ), // the first statement after START TRANSACTION
			1,
			function () use ( &$during ): void {
				$during = $this->deadlocking->reconnect_retries;
			},
		);

		$this->contended->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );

		$this->assertGreaterThan( 0, $before );
		$this->assertSame( 0, $during, 'no reconnect while the transaction is open' );
		$this->assertSame( $before, $this->deadlocking->reconnect_retries, 'restored after a success' );

		$this->deadlocking->deadlock_pattern = '/^\s*COMMIT\s*$/i';
		try {
			$this->contended->create( self::draft( '2026-12-14 14:00', '2026-12-14 15:00' ) );
			$this->fail( 'Expected the busy error.' );
		} catch ( CalendarBusy $e ) {
			$this->assertSame( $before, $this->deadlocking->reconnect_retries, 'restored after a failure' );
		}
	}

	/** The connection guard is on for core wpdb and off for drop-ins that route queries across servers. */
	public function test_the_connection_guard_is_off_for_multi_server_drop_ins(): void {
		$guard = new \ReflectionMethod( BookingRepository::class, 'guardsConnection' );
		$guard->setAccessible( true );
		$multi = ( new \ReflectionClass( MultiServerWpdb::class ) )->newInstanceWithoutConstructor();

		$this->assertTrue( $guard->invoke( $this->repo ), 'core wpdb' );
		$this->assertFalse( $guard->invoke( new BookingRepository( $multi, 30 ) ), 'HyperDB / LudicrousDB style ($dbhs)' );

		$off = static fn() => false;
		add_filter( 'ishot_booking_connection_guard', $off );
		try {
			$this->assertFalse( $guard->invoke( $this->repo ), 'the filter decides' );
		} finally {
			remove_filter( 'ishot_booking_connection_guard', $off );
		}
	}

	/** The safety net only frees rows of bookings that are gone or released; live bookings keep theirs. */
	public function test_releasing_orphan_locks_never_touches_a_live_booking(): void {
		global $wpdb;
		$live      = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
		$cancelled = $this->repo->create( self::draft( '2026-12-14 14:00', '2026-12-14 15:00' ) );
		$wpdb->update( Schema::bookings(), array( 'status' => 'cancelled' ), array( 'id' => $cancelled ) ); // left locked, as an unchecked write once could
		$wpdb->insert(
			Schema::locks(),
			array(
				'resource_id'    => 1,
				'unit_start_utc' => '2026-12-15 09:00:00',
				'booking_id'     => 999999,
			)
		);

		$this->assertSame( 3, $this->repo->releaseOrphanLocks(), "the cancelled booking's two units and the missing booking's one" );
		$this->assertSame( array( '2026-12-14 10:00:00', '2026-12-14 10:30:00' ), $this->repo->lockedUnits( 1, '2026-12-14 00:00:00', '2026-12-16 00:00:00' ) );
		$this->assertSame( 'requested', $this->repo->find( $live )['status'] );
		$this->assertSame( 0, $this->repo->releaseOrphanLocks(), 'nothing left to free' );
	}
}
