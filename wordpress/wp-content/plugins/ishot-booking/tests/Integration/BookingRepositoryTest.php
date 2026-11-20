<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Persistence\SlotConflict;
use IShot\Booking\Persistence\StaleBooking;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the real WordPress test database (InnoDB). Deliberately not WP_UnitTestCase:
 * its per-test transaction and temporary-table rewriting would mask the repository's own
 * transactions and unique-key behaviour, which are exactly what is under test here.
 */
final class BookingRepositoryTest extends TestCase {

	private BookingRepository $repo;

	protected function setUp(): void {
		global $wpdb;
		Schema::install();
		foreach ( Schema::tables() as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$this->repo = new BookingRepository( $wpdb, 30 );
	}

	private static function draft( string $start, string $end, array $overrides = array() ): NewBooking {
		$utc = new DateTimeZone( 'UTC' );
		return new NewBooking(
			session: new TimeRange( new DateTimeImmutable( $start, $utc ), new DateTimeImmutable( $end, $utc ) ),
			buffer_before_min: $overrides['before'] ?? 0,
			buffer_after_min: $overrides['after'] ?? 0,
			status: BookingStatus::Requested,
			client_name: 'Ada Test',
			client_email: 'ada@example.com',
			idempotency_key: $overrides['key'] ?? null,
			service_id: 1
		);
	}

	private static function rowCount( string $table ): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function test_booking_locks_every_unit_of_its_buffered_range(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', array( 'after' => 30 ) ) );

		$this->assertSame(
			array( '2026-12-14 10:00:00', '2026-12-14 10:30:00', '2026-12-14 11:00:00' ),
			$this->repo->lockedUnits( 1, '2026-12-14 00:00:00', '2026-12-15 00:00:00' )
		);
		$this->assertSame( 'requested', $this->repo->find( $id )['status'] );
	}

	public function test_overlapping_booking_is_rejected_and_leaves_no_trace(): void {
		$this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );

		try {
			$this->repo->create( self::draft( '2026-12-14 10:30', '2026-12-14 11:30' ) );
			$this->fail( 'Expected a SlotConflict.' );
		} catch ( SlotConflict $e ) {
			$this->assertSame( 1, self::rowCount( Schema::bookings() ), 'the rejected booking row must be rolled back' );
			$this->assertSame( 2, self::rowCount( Schema::locks() ), 'no extra lock rows may survive the rollback' );
		}
	}

	public function test_back_to_back_bookings_both_succeed(): void {
		$this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
		$this->repo->create( self::draft( '2026-12-14 11:00', '2026-12-14 12:00' ) );

		$this->assertSame( 2, self::rowCount( Schema::bookings() ) );
	}

	public function test_buffer_overlap_is_a_conflict(): void {
		$this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', array( 'after' => 30 ) ) );

		$this->expectException( SlotConflict::class );
		$this->repo->create( self::draft( '2026-12-14 11:00', '2026-12-14 12:00' ) );
	}

	public function test_retry_with_same_idempotency_key_returns_the_original_booking(): void {
		$first  = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', array( 'key' => 'req-abc' ) ) );
		$second = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', array( 'key' => 'req-abc' ) ) );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, self::rowCount( Schema::bookings() ) );
	}

	public function test_cancelling_releases_the_slot_for_someone_else(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );

		$this->repo->transition( $id, BookingStatus::Cancelled, 1 );
		$again = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );

		$this->assertNotSame( $id, $again );
		$this->assertSame( 'cancelled', $this->repo->find( $id )['status'] );
	}

	public function test_confirming_keeps_the_slot_locked(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1 );

		$this->expectException( SlotConflict::class );
		$this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
	}

	public function test_transition_with_a_stale_version_is_refused(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1 ); // version 1 → 2

		$this->expectException( StaleBooking::class );
		$this->repo->transition( $id, BookingStatus::Cancelled, 1 ); // a second admin acting on the old version
	}

	public function test_illegal_transition_is_refused_and_changes_nothing(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );

		try {
			$this->repo->transition( $id, BookingStatus::Completed, 1 );
			$this->fail( 'Expected an InvalidArgumentException.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertSame( 'requested', $this->repo->find( $id )['status'] );
		}
	}

	public function test_every_change_is_written_to_the_audit_log(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
		$this->repo->transition( $id, BookingStatus::Cancelled, 1 );

		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT action, from_status, to_status FROM ' . Schema::events() . ' WHERE booking_id = %d ORDER BY id', $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		$this->assertSame(
			array(
				array( 'action' => 'created', 'from_status' => null, 'to_status' => 'requested' ),
				array( 'action' => 'status_changed', 'from_status' => 'requested', 'to_status' => 'cancelled' ),
			),
			$rows
		);
	}
	private static function utc( string $utc ): DateTimeImmutable {
		return new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) );
	}

	private function locksOn( string $day = '2026-12-14' ): array {
		return $this->repo->lockedUnits( 1, "{$day} 00:00:00", "{$day} 23:59:59" );
	}

	public function test_reschedule_moves_the_booking_and_its_locks_together(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', array( 'after' => 30 ) ) );

		$this->repo->reschedule( $id, self::utc( '2026-12-14 14:00' ), 1, 7 );

		$this->assertSame( array( '2026-12-14 14:00:00', '2026-12-14 14:30:00', '2026-12-14 15:00:00' ), $this->locksOn() );
		$row = $this->repo->find( $id );
		$this->assertSame( '2026-12-14 14:00:00', $row['start_utc'] );
		$this->assertSame( '2026-12-14 15:00:00', $row['end_utc'] );
		$this->assertSame( '2026-12-14 15:30:00', $row['block_end_utc'] );
		$this->assertSame( '2', $row['version'] );
		$this->assertSame( 'rescheduled', $this->repo->events( $id )[1]['action'] );
	}

	public function test_reschedule_may_overlap_the_bookings_own_old_time(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', array( 'after' => 30 ) ) );

		$this->repo->reschedule( $id, self::utc( '2026-12-14 10:30' ), 1 );

		$this->assertSame( array( '2026-12-14 10:30:00', '2026-12-14 11:00:00', '2026-12-14 11:30:00' ), $this->locksOn() );
	}

	public function test_failed_reschedule_leaves_the_original_slot_untouched(): void {
		$a = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00', array( 'after' => 30 ) ) );
		$this->repo->create( self::draft( '2026-12-14 14:00', '2026-12-14 15:00' ) );

		try {
			$this->repo->reschedule( $a, self::utc( '2026-12-14 13:30' ), 1 ); // needs 13:30–15:00
			$this->fail( 'Expected SlotConflict' );
		} catch ( SlotConflict $e ) {
			// expected
		}

		$this->assertSame(
			array( '2026-12-14 10:00:00', '2026-12-14 10:30:00', '2026-12-14 11:00:00', '2026-12-14 14:00:00', '2026-12-14 14:30:00' ),
			$this->locksOn()
		);
		$row = $this->repo->find( $a );
		$this->assertSame( '2026-12-14 10:00:00', $row['start_utc'] );
		$this->assertSame( '1', $row['version'] );
		$this->assertCount( 1, $this->repo->events( $a ) );
	}

	public function test_reschedule_with_a_stale_version_is_refused(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );

		$this->expectException( StaleBooking::class );
		$this->repo->reschedule( $id, self::utc( '2026-12-14 14:00' ), 5 );
	}

	public function test_a_released_booking_cannot_be_rescheduled(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );
		$this->repo->transition( $id, BookingStatus::Cancelled, 1 );

		$this->expectException( \InvalidArgumentException::class );
		$this->repo->reschedule( $id, self::utc( '2026-12-14 14:00' ), 2 );
	}

	public function test_transition_can_update_deposit_fields_and_record_a_payload(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );

		$this->repo->transition( $id, BookingStatus::PendingDeposit, 1, 3, array( 'deposit_status' => 'awaiting', 'deposit_amount' => '50.00' ), array( 'note' => 'MoMo' ) );

		$row = $this->repo->find( $id );
		$this->assertSame( 'awaiting', $row['deposit_status'] );
		$this->assertSame( '50.00', $row['deposit_amount'] );
		$event = $this->repo->events( $id )[1];
		$this->assertSame( '3', $event['actor_user_id'] );
		$this->assertSame( array( 'note' => 'MoMo' ), json_decode( $event['payload'], true ) );
	}

	public function test_transition_refuses_columns_outside_the_allow_list(): void {
		$id = $this->repo->create( self::draft( '2026-12-14 10:00', '2026-12-14 11:00' ) );

		$this->expectException( \InvalidArgumentException::class );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1, null, array( 'start_utc' => '2026-12-15 10:00:00' ) );
	}

	public function test_between_lists_bookings_overlapping_a_window_in_start_order(): void {
		$late  = $this->repo->create( self::draft( '2026-12-14 15:00', '2026-12-14 16:00' ) );
		$early = $this->repo->create( self::draft( '2026-12-14 09:00', '2026-12-14 10:00' ) );
		$this->repo->create( self::draft( '2026-12-16 09:00', '2026-12-16 10:00' ) );

		$rows = $this->repo->between( '2026-12-14 09:30:00', '2026-12-15 00:00:00' );

		$this->assertSame( array( $early, $late ), array_map( 'intval', array_column( $rows, 'id' ) ) );
	}
}
