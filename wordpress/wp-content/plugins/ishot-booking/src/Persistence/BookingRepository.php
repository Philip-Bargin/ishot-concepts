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

	/**
	 * The connection handle the open transaction started on. wpdb silently reconnects (and re-runs
	 * the statement) when the server goes away; the new session has no transaction, so anything
	 * after that must not be reported as saved.
	 *
	 * @var \WeakReference<object>|null
	 */
	private ?\WeakReference $txn_handle = null;

	/** Seconds a statement may wait for a row lock inside a booking change (filter: ishot_booking_lock_wait_timeout). */
	private const LOCK_WAIT_SECONDS = 5;

	public function __construct(
		private readonly wpdb $db,
		private readonly int $granularity_minutes
	) {
		$this->outbox = new Outbox( $db );
	}

	/**
	 * @return int Booking id. A repeated idempotency key returns the original booking's id.
	 * @throws SlotConflict When any part of the buffered range is already held.
	 * @throws CalendarBusy  Every attempt lost a deadlock and the range is still free.
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
			function ( Throwable $e ) use ( $booking, $units ) {
				if ( $e instanceof IdempotentReplay ) {
					return $this->idForKey( (string) $booking->idempotency_key );
				}
				// A same-key twin may have committed while every attempt here lost: that request succeeded.
				if ( $e instanceof CalendarBusy && null !== $booking->idempotency_key ) {
					$twin = $this->idForKey( $booking->idempotency_key );
					if ( null !== $twin ) {
						return $twin;
					}
				}
				try {
					$this->throwIfHeldByOthers( $e, $booking->resource_id, $units );
				} catch ( SlotConflict $conflict ) {
					// The twin may have committed between the lookup above and the lock count.
					$twin = null === $booking->idempotency_key ? null : $this->idForKey( $booking->idempotency_key );
					if ( null !== $twin ) {
						return $twin;
					}
					throw $conflict;
				}
				throw $e;
			}
		);
	}

	/** Columns a status change may also update. Times and identity change only through reschedule(). */
	private const TRANSITION_COLUMNS = array( 'deposit_status', 'deposit_amount', 'deposit_gateway', 'deposit_reference', 'hold_expires_at' );

	/**
	 * Moves a booking to a new status. Releasing statuses (cancelled, expired, completed, no-show)
	 * free its slot units in the same transaction.
	 *
	 * @throws StaleBooking             The booking changed since $expected_version was read.
	 * @throws InvalidArgumentException Unknown booking, a transition the lifecycle forbids, or a disallowed column.
	 * @throws CalendarBusy             Every attempt lost a deadlock or lock wait; nothing changed.
	 * @throws RuntimeException         Any other database failure (the change was rolled back).
	 *
	 * @param array<string, scalar|null> $changes Extra columns to set (TRANSITION_COLUMNS only).
	 * @param array<string, mixed>       $payload Context for the audit log (e.g. a reason or payment reference).
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

				$this->write(
					fn() => $this->db->update(
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
					),
					'update the booking'
				);
				if ( ! $to->holdsSlot() ) {
					$this->write( fn() => $this->db->delete( Schema::locks(), array( 'booking_id' => $id ) ), 'release the time slot' );
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
	 * @throws CalendarBusy             Every attempt lost a deadlock and the new time is still free.
	 */
	public function reschedule( int $id, DateTimeImmutable $new_start, int $expected_version, ?int $actor_user_id = null ): void {
		$claim = null; // [resource id, units] the last attempt tried to lock
		$this->inTransaction(
			function () use ( $id, $new_start, $expected_version, $actor_user_id, &$claim ): void {
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

				$claim = array( (int) $row['resource_id'], SlotUnits::cover( $blocked, $this->granularity_minutes ) );
				$this->write( fn() => $this->db->delete( Schema::locks(), array( 'booking_id' => $id ) ), 'release the old time' );
				$this->lockUnits( $claim[0], $claim[1], $id );

				$this->write(
					fn() => $this->db->update(
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
					),
					'move the booking'
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
			},
			function ( Throwable $e ) use ( $id, &$claim ) {
				if ( null !== $claim ) { // @phpstan-ignore notIdentical.alwaysFalse ($claim is set by reference inside $work)
					$this->throwIfHeldByOthers( $e, $claim[0], $claim[1], $id );
				}
				throw $e;
			}
		);
	}

	/**
	 * Row lock for a change: the booking must exist and still be at the version the caller saw.
	 *
	 * @return array<string, mixed>
	 */
	private function lockForUpdate( int $id, int $expected_version ): array {
		$row = $this->quietly(
			fn() => $this->db->get_row(
				$this->db->prepare( 'SELECT * FROM ' . Schema::bookings() . ' WHERE id = %d FOR UPDATE', $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			)
		);
		if ( null === $row ) {
			$this->throwIfTransient(); // a lost row lock is "busy", not a missing booking
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
	 * Safety net: frees lock rows whose booking is gone or no longer holds a slot (cancelled,
	 * expired, completed, no-show). Normal changes never leave such rows; this repairs one left by
	 * a failure no transaction can undo (e.g. the server dropping the connection mid-change, after
	 * which wpdb re-runs the statement outside the transaction), so a time never stays unbookable.
	 *
	 * Candidates come from a consistent (non-locking) read, so changes still in flight are
	 * invisible to it; each DELETE re-checks its booking.
	 *
	 * @return int Lock rows released.
	 */
	public function releaseOrphanLocks(): int {
		$live = array();
		foreach ( BookingStatus::cases() as $status ) {
			if ( $status->holdsSlot() ) {
				$live[] = $status->value;
			}
		}
		$placeholders = implode( ', ', array_fill( 0, count( $live ), '%s' ) );
		$ids          = $this->db->get_col(
			$this->db->prepare(
				'SELECT DISTINCT l.booking_id FROM ' . Schema::locks() . ' l LEFT JOIN ' . Schema::bookings() . " b ON b.id = l.booking_id WHERE b.id IS NULL OR b.status NOT IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL
				$live
			)
		);
		if ( array() === $ids ) {
			return 0;
		}
		// Its own checked transaction, so the release is committed whatever the session's autocommit mode.
		return $this->inTransaction(
			function () use ( $ids, $placeholders, $live ): int {
				$released = 0;
				foreach ( $ids as $booking_id ) {
					$sql     = $this->db->prepare(
						'DELETE FROM ' . Schema::locks() . ' WHERE booking_id = %d AND NOT EXISTS ( SELECT 1 FROM ' . Schema::bookings() . " WHERE id = %d AND status IN ({$placeholders}) )", // phpcs:ignore WordPress.DB.PreparedSQL
						array_merge( array( (int) $booking_id, (int) $booking_id ), $live )
					);
					$deleted = $this->quietly( fn() => $this->db->query( $sql ) );
					if ( false === $deleted ) {
						$this->throwIfTransient();
						throw new RuntimeException( 'Could not release a stale time slot: ' . $this->db->last_error );
					}
					$released += (int) $deleted;
				}
				return $released;
			}
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
		if ( isset( $filters['owner'] ) ) {
			// A client's own bookings: linked to their account, or unclaimed guest bookings under
			// an email address the account has verified. Anonymised rows are no longer anyone's.
			$where[] = 'anonymised_at IS NULL';
			if ( null !== ( $filters['owner_email'] ?? null ) ) {
				$where[] = '(client_user_id = %d OR (client_user_id IS NULL AND client_email = %s))';
				array_push( $args, (int) $filters['owner'], (string) $filters['owner_email'] );
			} else {
				$where[] = 'client_user_id = %d';
				$args[]  = (int) $filters['owner'];
			}
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

	/** Links an unclaimed booking to a client account; a booking already linked never moves. */
	public function linkToUser( int $booking_id, int $user_id ): bool {
		return 1 === $this->db->query(
			$this->db->prepare( 'UPDATE ' . Schema::bookings() . " SET client_user_id = %d WHERE id = %d AND kind = 'booking' AND client_user_id IS NULL", $user_id, $booking_id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
	 * After every attempt lost a deadlock, tells a genuine loser from a busy calendar: if another
	 * booking has since committed any of the units, the honest answer is SlotConflict, not "busy".
	 * Runs after the ROLLBACK, as a plain read of committed locks, so it writes and queues nothing.
	 *
	 * @param string[] $units
	 * @throws SlotConflict
	 */
	private function throwIfHeldByOthers( Throwable $e, int $resource_id, array $units, ?int $own_booking_id = null ): void {
		if ( ! $e instanceof CalendarBusy || array() === $units ) {
			return;
		}
		$sql  = 'SELECT COUNT(*) FROM ' . Schema::locks() . ' WHERE resource_id = %d AND unit_start_utc IN (' . implode( ', ', array_fill( 0, count( $units ), '%s' ) ) . ')';
		$args = array_merge( array( $resource_id ), $units );
		if ( null !== $own_booking_id ) {
			$sql   .= ' AND booking_id <> %d'; // a booking's own units never conflict with its move
			$args[] = $own_booking_id;
		}
		if ( (int) $this->db->get_var( $this->db->prepare( $sql, $args ) ) > 0 ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			throw new SlotConflict( 'That time has just been booked by someone else.', 0, $e );
		}
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
		$this->write(
			fn() => $this->db->insert(
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
			),
			'record the change'
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
	 * locks and ends in a clean SlotConflict instead of a 500. If every attempt loses, $on_error
	 * gets a CalendarBusy (create() and reschedule() then re-check the units: see throwIfHeldByOthers()).
	 *
	 * Every statement in $work goes through write()/quietly() with its result checked: after a
	 * deadlock InnoDB has already rolled the whole transaction back, so a statement that ran on
	 * regardless would commit on its own. COMMIT is checked too, and lock waits are bounded
	 * (LOCK_WAIT_SECONDS per statement).
	 *
	 * wpdb's silent reconnect is switched off for the duration: when the server drops the
	 * connection mid-change, re-running the failed statement on a fresh session (autocommit, no
	 * transaction) could commit half a change, e.g. a booking row without its slot locks. With no
	 * retries the request fails instead and the server has already rolled the whole change back.
	 */
	private function inTransaction( callable $work, ?callable $on_error = null ): mixed {
		$restore_lock_wait = $this->boundLockWait();
		$reconnect_retries = $this->db->reconnect_retries; // @phpstan-ignore property.protected (wpdb exposes it through __get/__set)
		try {
			for ( $attempt = 1; ; $attempt++ ) {
				$this->queued = 0;
				try {
					$this->begin();
					$result = $work();
					$this->commit();
				} catch ( TransientLockError $e ) {
					$this->rollback();
					if ( $attempt < self::MAX_ATTEMPTS ) {
						usleep( wp_rand( 5000, 25000 ) * $attempt ); // jitter so the victims don't collide again
						continue;
					}
					$e = new CalendarBusy( 'The booking could not be saved because the calendar is busy. Please try again.', 0, $e );
					if ( null === $on_error ) {
						throw $e;
					}
					return $on_error( $e );
				} catch ( Throwable $e ) {
					$this->rollback();
					if ( null === $on_error ) {
						throw $e;
					}
					return $on_error( $e );
				}
				if ( $this->queued > 0 ) { // incremented inside $work via log()
					do_action( Outbox::QUEUED_HOOK ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the constant is 'ishot_booking_notifications_queued'.
				}
				return $result;
			}
		} finally {
			$this->db->reconnect_retries = $reconnect_retries; // @phpstan-ignore property.protected
			$restore_lock_wait();
		}
	}

	private function begin(): void {
		$this->txn_handle = null;
		$started          = $this->quietly( fn() => $this->db->query( 'START TRANSACTION' ) );
		if ( false === $started ) {
			$this->throwIfTransient();
			throw new RuntimeException( 'Could not start a database transaction: ' . $this->db->last_error );
		}
		// Only now: START TRANSACTION itself may still reconnect a connection that timed out while idle.
		$this->db->reconnect_retries = 0; // @phpstan-ignore property.protected (restored by inTransaction())
		$handle                      = $this->db->dbh; // @phpstan-ignore property.protected (wpdb exposes it through __get)
		$this->txn_handle            = $this->guardsConnection() && is_object( $handle ) ? \WeakReference::create( $handle ) : null;
	}

	/**
	 * Whether quietly() may treat a change of connection handle inside a transaction as a lost
	 * connection. Not for drop-ins that route each query to one of several servers (HyperDB,
	 * LudicrousDB: they keep their handles in $dbhs and swap $dbh per query); on those sites every
	 * booking query must go to the primary (see README). Filter: ishot_booking_connection_guard.
	 */
	private function guardsConnection(): bool {
		return (bool) apply_filters( 'ishot_booking_connection_guard', ! property_exists( $this->db, 'dbhs' ), $this->db );
	}

	private function commit(): void {
		$committed        = $this->quietly( fn() => $this->db->query( 'COMMIT' ) ); // also checks the connection
		$this->txn_handle = null;
		if ( false === $committed ) {
			$this->throwIfTransient(); // e.g. a cluster certification failure surfaces at COMMIT
			throw new RuntimeException( 'Could not save the change: ' . $this->db->last_error );
		}
	}

	private function rollback(): void {
		$this->txn_handle = null;
		$this->quietly( fn() => $this->db->query( 'ROLLBACK' ) );
	}

	/**
	 * Lowers this session's InnoDB lock wait for the duration of one booking change. The server
	 * default (50 s) across MAX_ATTEMPTS would outlast PHP and proxy timeouts and turn the 503
	 * "busy" answer into a 500/504. Returns the callback that puts the previous value back.
	 *
	 * @return callable(): void
	 */
	private function boundLockWait(): callable {
		$noop  = static function (): void {};
		$limit = (int) apply_filters( 'ishot_booking_lock_wait_timeout', self::LOCK_WAIT_SECONDS );
		if ( $limit < 1 ) {
			return $noop;
		}
		$previous = $this->quietly( fn() => $this->db->get_var( 'SELECT @@SESSION.innodb_lock_wait_timeout' ) );
		if ( ! is_numeric( $previous ) || (int) $previous <= $limit ) {
			return $noop; // not InnoDB, or already short enough
		}
		$this->quietly( fn() => $this->db->query( $this->db->prepare( 'SET SESSION innodb_lock_wait_timeout = %d', $limit ) ) );
		return function () use ( $previous ): void {
			$this->quietly( fn() => $this->db->query( $this->db->prepare( 'SET SESSION innodb_lock_wait_timeout = %d', (int) $previous ) ) );
		};
	}

	/**
	 * Runs one write of a transaction and fails the whole transaction if it did not apply
	 * (wpdb returns false on error; an unchecked failure would let the rest commit without it).
	 *
	 * @param callable(): (int|bool|null) $statement
	 * @throws TransientLockError On a deadlock or lock wait timeout (the transaction is retried).
	 * @throws RuntimeException   On any other failure.
	 */
	private function write( callable $statement, string $what ): void {
		if ( false === $this->quietly( $statement ) ) {
			$this->throwIfTransient();
			throw new RuntimeException( "Could not {$what}: " . $this->db->last_error );
		}
	}

	/** @throws TransientLockError When the last query was a deadlock victim or timed out waiting for a lock. */
	private function throwIfTransient(): void {
		if ( DbError::isTransient( $this->db ) ) {
			throw new TransientLockError( $this->db->last_error );
		}
	}

	/**
	 * Runs a query with wpdb error output suppressed; failures are handled by the caller.
	 * Inside a transaction it also refuses a result that came from a different connection than the
	 * one the transaction began on: wpdb reconnected, and the server has rolled the transaction back.
	 */
	private function quietly( callable $query ): mixed {
		$previous = $this->db->suppress_errors( true );
		try {
			$result = $query();
		} finally {
			$this->db->suppress_errors( $previous );
		}
		if ( null !== $this->txn_handle && $this->txn_handle->get() !== $this->db->dbh ) { // @phpstan-ignore property.protected
			$this->txn_handle = null;
			throw new RuntimeException( 'The database connection was lost in the middle of a booking change; it was not saved.' );
		}
		return $result;
	}

	private function isDuplicateKeyError(): bool {
		return DbError::isDuplicateKey( $this->db );
	}
}
