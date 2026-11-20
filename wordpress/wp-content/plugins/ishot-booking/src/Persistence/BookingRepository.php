<?php
namespace IShot\Booking\Persistence;

use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\SlotUnits;
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

	public function __construct(
		private readonly wpdb $db,
		private readonly int $granularity_minutes
	) {}

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

	/**
	 * Moves a booking to a new status. Releasing statuses (cancelled, expired, completed, no-show)
	 * free its slot units in the same transaction.
	 *
	 * @throws InvalidArgumentException Unknown booking or a transition the lifecycle forbids.
	 * @throws StaleBooking             The booking changed since $expected_version was read.
	 */
	public function transition( int $id, BookingStatus $to, int $expected_version, ?int $actor_user_id = null ): void {
		$this->inTransaction(
			function () use ( $id, $to, $expected_version, $actor_user_id ): void {
				$row = $this->db->get_row(
					$this->db->prepare( 'SELECT status, version FROM ' . Schema::bookings() . ' WHERE id = %d FOR UPDATE', $id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					ARRAY_A
				);
				if ( null === $row ) {
					throw new InvalidArgumentException( "Booking {$id} does not exist." );
				}
				if ( (int) $row['version'] !== $expected_version ) {
					throw new StaleBooking( "Booking {$id} was changed by someone else. Reload and try again." );
				}
				$from = BookingStatus::from( $row['status'] );
				if ( ! $from->canTransitionTo( $to ) ) {
					throw new InvalidArgumentException( "A {$from->value} booking cannot become {$to->value}." );
				}

				$this->db->update(
					Schema::bookings(),
					array(
						'status'     => $to->value,
						'version'    => $expected_version + 1,
						'updated_at' => gmdate( self::DATETIME ),
					),
					array( 'id' => $id )
				);
				if ( ! $to->holdsSlot() ) {
					$this->db->delete( Schema::locks(), array( 'booking_id' => $id ) );
				}
				$this->log( $id, 'status_changed', $from->value, $to->value, $actor_user_id );
			}
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
			throw new RuntimeException( 'Could not lock the time slot: ' . $this->db->last_error );
		}
	}

	private function log( int $booking_id, string $action, ?string $from, ?string $to, ?int $actor_user_id = null ): void {
		$this->db->insert(
			Schema::events(),
			array(
				'booking_id'    => $booking_id,
				'actor_user_id' => $actor_user_id,
				'action'        => $action,
				'from_status'   => $from,
				'to_status'     => $to,
				'created_at'    => gmdate( self::DATETIME ),
			)
		);
	}

	private function idForKey( string $key ): ?int {
		$id = $this->db->get_var(
			$this->db->prepare( 'SELECT id FROM ' . Schema::bookings() . ' WHERE idempotency_key = %s', $key ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		return null === $id ? null : (int) $id;
	}

	/**
	 * Runs $work inside START TRANSACTION … COMMIT, rolling back on any exception.
	 * $on_error may turn a rolled-back exception into a return value.
	 */
	private function inTransaction( callable $work, ?callable $on_error = null ): mixed {
		$this->db->query( 'START TRANSACTION' );
		try {
			$result = $work();
			$this->db->query( 'COMMIT' );
			return $result;
		} catch ( Throwable $e ) {
			$this->db->query( 'ROLLBACK' );
			if ( null === $on_error ) {
				throw $e;
			}
			return $on_error( $e );
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
