<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Notifications\Outbox;
use IShot\Booking\Persistence\BookingRepository;
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
		} catch ( RuntimeException $e ) {
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
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'calendar is busy', $e->getMessage() );
		}

		$this->assertSame( 3, $this->deadlocking->refused );
		$this->assertSame( $before, self::snapshot() );
	}
}
