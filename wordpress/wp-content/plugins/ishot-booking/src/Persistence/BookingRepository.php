<?php
namespace IShot\Booking\Persistence;

use IShot\Booking\Domain\BookingStatus;
use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Domain\SlotUnits;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Notifications\Outbox;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use wpdb;

/**
 * Bookings, slot locks and the audit log.
 *
 * Conflict prevention: creating a booking inserts its row and one lock row per slot unit in a
 * single transaction. The lock table's primary key (resource_id, unit_start_utc) makes MySQL
 * reject any unit that is already held, so two overlapping requests can never both commit,
 * however close together they arrive.
 */
final class BookingRepository {

	private const DATETIME = 'Y-m-d H:i:s';

	private readonly Outbox $outbox;

	/** Emails queued by the transaction in progress; delivery is kicked only after it commits. */
	private int $queued = 0;

	public function __construct(
		private readonly wpdb $db,
		private readonly int $granularity_minutes
	) {
		$this->outbox = new Outbox( $db );
	}

	/**
	 * @return int Booking id. A repeated idempotency key returns the original booking's id.
	 * @throws SlotConflict When any part of the buffered range is already held.
	 */
	public function create( NewBooking $booking ): int {
		if ( null !== $booking->idempotency_key ) {
			$existing = $this->idForKey( $booking->idempotency_key );
			if ( null !== $existing ) {
				return $existing;
			}
		}

		$blocked = $booking->blockedRange();
		$units   = SlotUnits::cover( $blocked, $this->granularity_minutes );
		$now     = gmdate( self::DATETIME );

		return $this->inTransaction(
			function () use ( $booking, $blocked, $units, $now ): int {
				$inserted = $this->quietly(
					fn() => $this->db->insert(
						Schema::bookings(),
						array(
							'resource_id'       => $booking->resource_id,
							'kind'              => $booking->kind,
							'service_id'        => $booking->service_id,
							'status'            => $booking->status->value,
							'start_utc'         => $booking->session->start->format( self::DATETIME ),
							'end_utc'           => $booking->session->end->format( self::DATETIME ),
							'block_start_utc'   => $blocked->start->format( self::DATETIME ),
							'block_end_utc'     => $blocked->end->format( self::DATETIME ),
							'client_user_id'    => $booking->client_user_id,
							'client_name'       => $booking->client_name,
							'client_email'      => $booking->client_email,
							'client_phone'      => $booking->client_phone,
							'location'          => $booking->location,
							'notes'             => $booking->notes,
							'hold_expires_at'   => $booking->hold_expires_at?->format( self::DATETIME ),
							'public_token_hash' => $booking->public_token_hash,
							'idempotency_key'   => $booking->idempotency_key,
							'created_at'        => $now,
							'updated_at'        => $now,
						)
					)
				);
				if ( false === $inserted ) {
					// Same idempotency key committed by a concurrent request a moment ago.
					if ( null !== $booking->idempotency_key && $this->isDuplicateKeyError() ) {
						throw new IdempotentReplay();
					}
					$this->throwIfTransient();
					throw new RuntimeException( 'Could not save the booking: ' . $this->db->last_error );
				}
				$id = (int) $this->db->insert_id;

				$this->lockUnits( $booking->resource_id, $units, $id );
				$this->log( $id, 'created', null, $booking->status->value );
				return $id;
			},
			function ( Throwable $e ) use ( $booking ) {
				if ( $e instanceof IdempotentReplay ) {
					return $this->idForKey( (string) $booking->idempotency_key );
				}
				throw $e;
			}
		);
	}

	/** Columns a status change may also update. Times and identity change only through reschedule(). */
	private const TRANSITION_COLUMNS = array( 'deposit_status', 'deposit_amount', 'hold_expires_at' );

	/**
	 * Moves a booking to a new status. Releasing statuses (cancelled, expired, completed, no-show)
	 * free its slot units in the same transaction.
	 *
	 * @param array<string, scalar|null> $changes Extra columns to set (TRANSITION_COLUMNS only).
	 * @param array<string, mixed>       $payload Context for the audit log (e.g. a reason or payment reference).
	 * @throws InvalidArgumentException Unknown booking, a transition the lifecycle forbids, or a disallowed column.
	 * @throws StaleBooking             The booking changed since $expected_version was read.
	 */
	public function transition( int $id, BookingStatus $to, int $expected_version, ?int $actor_user_id = null, array $changes = array(), array $payload = array() ): void {
		$unknown = array_diff( array_keys( $changes ), self::TRANSITION_COLUMNS );
		if ( array() !== $unknown ) {
			throw new InvalidArgumentException( 'A status change cannot set: ' . implode( ', ', $unknown ) );
		}

		$this->inTransaction(
			function () use ( $id, $to, $expected_version, $actor_user_id, $changes, $payload ): void {
				$row  = $this->lockForUpdate( $id, $expected_version );
				$from = BookingStatus::from( $row['status'] );
				if ( ! $from->canTransitionTo( $to ) ) {
					throw new InvalidArgumentException( "A {$from->value} booking cannot become {$to->value}." );
				}

				$this->db->update(
					Schema::bookings(),
					array_merge(
						$changes,
						array(
							'status'     => $to->value,
							'version'    => $expected_version + 1,
							'updated_at' => gmdate( self::DATETIME ),
						)
					),
					array( 'id' => $id )
				);
				if ( ! $to->holdsSlot() ) {
					$this->db->delete( Schema::locks(), array( 'booking_id' => $id ) );
				}
				$this->log( $id, 'status_changed', $from->value, $to->value, $actor_user_id, $payload );
			}
		);
	}

	/**
	 * Moves a live booking to a new start, keeping its duration and buffers. The old units are
	 * released and the new ones claimed in one transaction: if any new unit is taken, the
	 * rollback restores the original locks and times exactly. (Releasing first lets a booking
	 * move by less than its own length.)
	 *
	 * @throws SlotConflict             Part of the new time is held by another booking.
	 * @throws StaleBooking             The booking changed since $expected_version was read.
	 * @throws InvalidArgumentException Unknown booking, or one that no longer holds a slot.
	 */
	public function reschedule( int $id, DateTimeImmutable $new_start, int $expected_version, ?int $actor_user_id = null ): void {
		$this->inTransaction(
			function () use ( $id, $new_start, $expected_version, $actor_user_id ): void {
				$row    = $this->lockForUpdate( $id, $expected_version );
				$status = BookingStatus::from( $row['status'] );
				if ( ! $status->holdsSlot() ) {
					throw new InvalidArgumentException( "A {$status->value} booking cannot be rescheduled." );
				}

				$utc      = new DateTimeZone( 'UTC' );
				$ts       = static fn( string $column ) => ( new DateTimeImmutable( $row[ $column ], $utc ) )->getTimestamp();
				$duration = $ts( 'end_utc' ) - $ts( 'start_utc' );
				$before   = $ts( 'start_utc' ) - $ts( 'block_start_utc' );
				$after    = $ts( 'block_end_utc' ) - $ts( 'end_utc' );

				$start   = $new_start->setTimezone( $utc );
				$end     = $start->modify( "+{$duration} seconds" );
				$blocked = new TimeRange( $start->modify( "-{$before} seconds" ), $end->modify( "+{$after} seconds" ) );

				$this->db->delete( Schema::locks(), array( 'booking_id' => $id ) );
				$this->lockUnits( (int) $row['resource_id'], SlotUnits::cover( $blocked, $this->granularity_minutes ), $id );

				$this->db->update(
					Schema::bookings(),
					array(
						'start_utc'       => $start->format( self::DATETIME ),
						'end_utc'         => $end->format( self::DATETIME ),
						'block_start_utc' => $blocked->start->format( self::DATETIME ),
						'block_end_utc'   => $blocked->end->format( self::DATETIME ),
						'version'         => $expected_version + 1,
						'updated_at'      => gmdate( self::DATETIME ),
					),
					array( 'id' => $id )
				);
				$this->log(
					$id,
					'rescheduled',
					$status->value,
					$status->value,
					$actor_user_id,
					array(
						'from' => $row['start_utc'],
						'to'   => $start->format( self::DATETIME ),
					)
				);
			}
		);
	}

	/**
	 * Row lock for a change: the booking must exist and still be at the version the caller saw.
	 *
	 * @return array<string, mixed>
	 */
	private function lockForUpdate( int $id, int $expected_version ): array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM ' . Schema::bookings() . ' WHERE id = %d FOR UPDATE', $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		if ( null === $row ) {
			throw new InvalidArgumentException( "Booking {$id} does not exist." );
		}
		if ( (int) $row['version'] !== $expected_version ) {
			throw new StaleBooking( "Booking {$id} was changed by someone else. Reload and try again." );
		}
		return $row;
	}

	/**
	 * Bookings and blocks whose buffered range overlaps [from, to), UTC, in start order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function between( string $from_utc, string $to_utc ): array {
		return $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM ' . Schema::bookings() . ' WHERE block_start_utc < %s AND block_end_utc > %s ORDER BY start_utc, id', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$to_utc,
				$from_utc
			),
			ARRAY_A
		);
	}

	/** @return array<string, mixed>|null The booking whose manage-token hash matches. */
	/** The booking for a manage-link token hash: the one returned at creation, or one issued in an email. */
	public function findByTokenHash( string $hash ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM ' . Schema::bookings() . ' WHERE public_token_hash = %s', $hash ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
		if ( null !== $row ) {
			return $row;
		}
		$id = ( new ManageLinks( $this->db ) )->bookingFor( $hash );
		return null === $id ? null : $this->find( $id );
	}

	/**
	 * Unpaid requests whose hold has run out: requested or pending_deposit, hold_expires_at <= now.
	 *
	 * @return array<int, array{id: string, version: string}>
	 */
	public function lapsedHolds( string $now_utc, int $limit = 200 ): array {
		return $this->db->get_results(
			$this->db->prepare(
				'SELECT id, version FROM ' . Schema::bookings() . ' WHERE status IN (%s, %s) AND hold_expires_at IS NOT NULL AND hold_expires_at <= %s ORDER BY hold_expires_at LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				BookingStatus::Requested->value,
				BookingStatus::PendingDeposit->value,
				$now_utc,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Client bookings (not staff blocks) for the admin list.
	 *
	 * @param array{status?: string, when?: string, now?: string, q?: string} $filters
	 *        when: 'upcoming' (start >= now, soonest first), 'past' (start < now, latest first), or all (latest first).
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( array $filters, int $per_page, int $page ): array {
		$where = array( "kind = 'booking'" );
		$args  = array();
		$order = 'start_utc DESC, id DESC';

		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = $filters['status'];
		}
		$when = $filters['when'] ?? 'all';
		if ( 'upcoming' === $when || 'past' === $when ) {
			$where[] = 'upcoming' === $when ? 'start_utc >= %s' : 'start_utc < %s';
			$args[]  = $filters['now'] ?? gmdate( self::DATETIME );
			$order   = 'upcoming' === $when ? 'start_utc ASC, id ASC' : $order;
		}
		if ( isset( $filters['q'] ) && '' !== trim( $filters['q'] ) ) {
			$like    = '%' . $this->db->esc_like( trim( $filters['q'] ) ) . '%';
			$where[] = '(client_name LIKE %s OR client_email LIKE %s OR client_phone LIKE %s)';
			array_push( $args, $like, $like, $like );
		}

		$sql_where = implode( ' AND ', $where );
		$table     = Schema::bookings();
		$total     = (int) $this->db->get_var( $this->prepareMaybe( "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}", $args ) );
		$rows      = $this->db->get_results(
			$this->db->prepare( "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY {$order} LIMIT %d OFFSET %d", array_merge( $args, array( $per_page, max( 0, ( $page - 1 ) * $per_page ) ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);
		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	public function countWithStatus( BookingStatus $status ): int {
		return (int) $this->db->get_var(
			$this->db->prepare( 'SELECT COUNT(*) FROM ' . Schema::bookings() . " WHERE kind = 'booking' AND status = %s", $status->value ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/** Unpaid holds that lapse in (from, to], UTC. */
	public function countHoldsExpiringBetween( string $from_utc, string $to_utc ): int {
		return (int) $this->db->get_var(
			$this->db->prepare(
				'SELECT COUNT(*) FROM ' . Schema::bookings() . ' WHERE status IN (%s, %s) AND hold_expires_at > %s AND hold_expires_at <= %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				BookingStatus::Requested->value,
				BookingStatus::PendingDeposit->value,
				$from_utc,
				$to_utc
			)
		);
	}

	/** @param array<int, mixed> $args */
	private function prepareMaybe( string $sql, array $args ): string {
		return array() === $args ? $sql : $this->db->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** @return array<int, array<string, mixed>> Audit events for a booking, oldest first. */
	public function events( int $booking_id ): array {
		return $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM ' . Schema::events() . ' WHERE booking_id = %d ORDER BY id', $booking_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
	}

	/** @return array<string, mixed>|null */
	public function find( int $id ): ?array {
		return $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM ' . Schema::bookings() . ' WHERE id = %d', $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);
	}

	/**
	 * Locked unit starts for a resource in [from, to), UTC.
	 *
	 * @return string[]
	 */
	public function lockedUnits( int $resource_id, string $from_utc, string $to_utc ): array {
		return $this->db->get_col(
			$this->db->prepare(
				'SELECT unit_start_utc FROM ' . Schema::locks() . ' WHERE resource_id = %d AND unit_start_utc >= %s AND unit_start_utc < %s ORDER BY unit_start_utc', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$resource_id,
				$from_utc,
				$to_utc
			)
		);
	}

	/**
	 * @param string[] $units
	 * @throws SlotConflict
	 */
	private function lockUnits( int $resource_id, array $units, int $booking_id ): void {
		$placeholders = implode( ', ', array_fill( 0, count( $units ), '(%d, %s, %d)' ) );
		$values       = array();
		foreach ( $units as $unit ) {
			array_push( $values, $resource_id, $unit, $booking_id );
		}
		$sql = $this->db->prepare( 'INSERT INTO ' . Schema::locks() . " (resource_id, unit_start_utc, booking_id) VALUES {$placeholders}", $values ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( false === $this->quietly( fn() => $this->db->query( $sql ) ) ) {
			if ( $this->isDuplicateKeyError() ) {
				throw new SlotConflict( 'That time has just been booked by someone else.' );
			}
			$this->throwIfTransient();
			throw new RuntimeException( 'Could not lock the time slot: ' . $this->db->last_error );
		}
	}

	/** Appends to the audit log and queues the emails the event calls for, in the caller's transaction. */
	private function log( int $booking_id, string $action, ?string $from, ?string $to, ?int $actor_user_id = null, array $payload = array() ): void {
		$this->db->insert(
			Schema::events(),
			array(
				'booking_id'    => $booking_id,
				'actor_user_id' => $actor_user_id,
				'action'        => $action,
				'from_status'   => $from,
				'to_status'     => $to,
				'payload'       => array() === $payload ? null : wp_json_encode( $payload ),
				'created_at'    => gmdate( self::DATETIME ),
			)
		);
		$this->queued += $this->outbox->recordEvent( $booking_id, (int) $this->db->insert_id, $action, $from, $to, $actor_user_id, $payload );
	}

	private function idForKey( string $key ): ?int {
		$id = $this->db->get_var(
			$this->db->prepare( 'SELECT id FROM ' . Schema::bookings() . ' WHERE idempotency_key = %s', $key ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		return null === $id ? null : (int) $id;
	}

	/** Attempts per transaction when InnoDB picks it as a deadlock victim. */
	private const MAX_ATTEMPTS = 3;

	/**
	 * Runs $work inside START TRANSACTION … COMMIT, rolling back on any exception.
	 * $on_error may turn a rolled-back exception into a return value.
	 *
	 * Concurrent inserts into the unique slot-lock index can deadlock; InnoDB then rolls one
	 * transaction back and asks for it to be restarted. $work is re-run from the top (it reads
	 * everything it needs inside the transaction), so the retry sees the winner's committed
	 * locks and ends in a clean SlotConflict instead of a 500.
	 */
	private function inTransaction( callable $work, ?callable $on_error = null ): mixed {
		for ( $attempt = 1; ; $attempt++ ) {
			$this->queued = 0;
			$this->db->query( 'START TRANSACTION' );
			try {
				$result = $work();
				$this->db->query( 'COMMIT' );
				if ( $this->queued > 0 ) {
					do_action( Outbox::QUEUED_HOOK );
				}
				return $result;
			} catch ( TransientLockError $e ) {
				$this->db->query( 'ROLLBACK' );
				if ( $attempt < self::MAX_ATTEMPTS ) {
					usleep( wp_rand( 5000, 25000 ) * $attempt ); // jitter so the victims don't collide again
					continue;
				}
				$e = new RuntimeException( 'The booking could not be saved because the calendar is busy. Please try again.', 0, $e );
				if ( null === $on_error ) {
					throw $e;
				}
				return $on_error( $e );
			} catch ( Throwable $e ) {
				$this->db->query( 'ROLLBACK' );
				if ( null === $on_error ) {
					throw $e;
				}
				return $on_error( $e );
			}
		}
	}

	/** @throws TransientLockError When the last query was a deadlock victim or timed out waiting for a lock. */
	private function throwIfTransient(): void {
		$error = $this->db->last_error;
		if ( str_contains( $error, 'Deadlock found' ) || str_contains( $error, 'Lock wait timeout' ) ) {
			throw new TransientLockError( $error );
		}
	}

	/** Runs a query with wpdb error output suppressed; failures are handled by the caller. */
	private function quietly( callable $query ): mixed {
		$previous = $this->db->suppress_errors( true );
		try {
			return $query();
		} finally {
			$this->db->suppress_errors( $previous );
		}
	}

	private function isDuplicateKeyError(): bool {
		return str_contains( $this->db->last_error, 'Duplicate entry' );
	}
}
