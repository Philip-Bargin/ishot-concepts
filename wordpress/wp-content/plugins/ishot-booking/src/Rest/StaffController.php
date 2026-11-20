<?php
namespace IShot\Booking\Rest;

use IShot\Booking\Application\StaffActions;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\SlotConflict;
use IShot\Booking\Persistence\StaleBooking;
use IShot\Booking\Settings;
use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Staff routes under ishot/v1/staff. Cookie-authenticated (wp-admin sends the X-WP-Nonce
 * header); every route checks a booking capability from Roles.
 */
final class StaffController {

	private const BASE = '/staff';

	/** Longest calendar window one request may load. */
	private const MAX_RANGE_DAYS = 62;

	public function register(): void {
		$id = '(?P<id>\d+)';

		register_rest_route(
			PublicController::NAMESPACE,
			self::BASE . '/bookings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index' ),
				'permission_callback' => self::requires( 'view_ishot_calendar' ),
				'args'                => array(
					'from' => self::dateArg(),
					'to'   => self::dateArg(),
				),
			)
		);

		register_rest_route(
			PublicController::NAMESPACE,
			self::BASE . "/bookings/{$id}",
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show' ),
				'permission_callback' => self::requires( 'view_ishot_calendar' ),
			)
		);

		register_rest_route(
			PublicController::NAMESPACE,
			self::BASE . "/bookings/{$id}/actions",
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'act' ),
				'permission_callback' => static fn( WP_REST_Request $r ) => self::check( StaffActions::ACTIONS[ $r['action'] ] ?? 'do_not_allow' ),
				'args'                => array(
					'action'         => array(
						'type'     => 'string',
						'enum'     => array_keys( StaffActions::ACTIONS ),
						'required' => true,
					),
					'version'        => array(
						'description' => 'The version you last saw; a newer one means someone else acted first.',
						'type'        => 'integer',
						'minimum'     => 1,
						'required'    => true,
					),
					'start'          => array(
						'type'    => 'string',
						'pattern' => Format::DATETIME_PATTERN,
					),
					'deposit_amount' => array(
						'type'    => 'number',
						'minimum' => 0,
					),
					'note'           => self::textArg( 500 ),
					'reason'         => self::textArg( 500 ),
					'reference'      => self::textArg( 100 ),
				),
			)
		);

		register_rest_route(
			PublicController::NAMESPACE,
			self::BASE . '/blocks',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'block' ),
				'permission_callback' => self::requires( 'block_ishot_time' ),
				'args'                => array(
					'start'  => array(
						'type'     => 'string',
						'pattern'  => Format::DATETIME_PATTERN,
						'required' => true,
					),
					'end'    => array(
						'type'     => 'string',
						'pattern'  => Format::DATETIME_PATTERN,
						'required' => true,
					),
					'reason' => self::textArg( 500 ),
				),
			)
		);

		register_rest_route(
			PublicController::NAMESPACE,
			self::BASE . "/blocks/{$id}",
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'unblock' ),
				'permission_callback' => self::requires( 'block_ishot_time' ),
			)
		);
	}

	/** 401 when signed out, 403 when signed in without the capability. */
	private static function check( string $cap ): bool|WP_Error {
		if ( current_user_can( $cap ) ) {
			return true;
		}
		return new WP_Error( 'rest_forbidden', __( 'You are not allowed to do that.', 'ishot-booking' ), array( 'status' => rest_authorization_required_code() ) );
	}

	private static function requires( string $cap ): callable {
		return static fn() => self::check( $cap );
	}

	/** @return array<string, mixed> */
	private static function dateArg(): array {
		return array(
			'type'     => 'string',
			'pattern'  => Format::DATE_PATTERN,
			'required' => true,
		);
	}

	/** @return array<string, mixed> */
	private static function textArg( int $max ): array {
		return array(
			'type'              => 'string',
			'maxLength'         => $max,
			'default'           => '',
			'sanitize_callback' => 'sanitize_textarea_field',
			'validate_callback' => 'rest_validate_request_arg', // a custom sanitizer otherwise skips schema checks.
		);
	}

	private function repo(): BookingRepository {
		global $wpdb;
		return new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
	}

	private static function notFound(): WP_Error {
		return new WP_Error( 'ishot_not_found', __( 'No such booking.', 'ishot-booking' ), array( 'status' => 404 ) );
	}

	private static function invalid( string $message ): WP_Error {
		return new WP_Error( 'rest_invalid_param', $message, array( 'status' => 400 ) );
	}

	private static function slotTaken(): WP_Error {
		return new WP_Error( 'ishot_slot_taken', __( 'That time overlaps another booking or block.', 'ishot-booking' ), array( 'status' => 409 ) );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	public static function present( array $row ): array {
		return array(
			'id'              => (int) $row['id'],
			'kind'            => $row['kind'],
			'status'          => $row['status'],
			'service_id'      => null === $row['service_id'] ? null : (int) $row['service_id'],
			'start'           => Format::iso( $row['start_utc'] ),
			'end'             => Format::iso( $row['end_utc'] ),
			'client'          => array(
				'name'  => $row['client_name'],
				'email' => $row['client_email'],
				'phone' => $row['client_phone'],
			),
			'location'        => $row['location'],
			'notes'           => (string) $row['notes'],
			'deposit'         => array(
				'amount' => null === $row['deposit_amount'] ? null : (float) $row['deposit_amount'],
				'status'    => $row['deposit_status'],
				'gateway'   => $row['deposit_gateway'] ?? null,
				'reference' => (string) ( $row['deposit_reference'] ?? '' ),
			),
			'hold_expires_at' => Format::iso( $row['hold_expires_at'] ),
			'version'         => (int) $row['version'],
		);
	}

	public function index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$from = Format::localDate( (string) $request['from'] );
		$to   = Format::localDate( (string) $request['to'] );
		if ( null === $from || null === $to || $to <= $from ) {
			return self::invalid( __( 'Give a valid date range: from must be before to.', 'ishot-booking' ) );
		}
		if ( $from->diff( $to )->days > self::MAX_RANGE_DAYS ) {
			return self::invalid( __( 'That date range is too long.', 'ishot-booking' ) );
		}
		$rows = $this->repo()->between( Format::utc( $from ), Format::utc( $to ) );
		return new WP_REST_Response( array_map( array( self::class, 'present' ), $rows ) );
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$repo = $this->repo();
		$row  = $repo->find( (int) $request['id'] );
		if ( null === $row ) {
			return self::notFound();
		}
		$events = array_map(
			static fn( array $e ) => array(
				'action'  => $e['action'],
				'from'    => $e['from_status'],
				'to'      => $e['to_status'],
				'actor'   => null === $e['actor_user_id'] ? null : (int) $e['actor_user_id'],
				'at'      => Format::iso( $e['created_at'] ),
				'payload' => null === $e['payload'] ? null : json_decode( $e['payload'], true ),
			),
			$repo->events( (int) $row['id'] )
		);
		return new WP_REST_Response( self::present( $row ) + array( 'events' => $events ) );
	}

	public function act( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$repo = $this->repo();
		$id   = (int) $request['id'];
		if ( null === $repo->find( $id ) ) {
			return self::notFound();
		}

		$args = array(
			'note'      => (string) $request['note'],
			'reason'    => (string) $request['reason'],
			'reference' => (string) $request['reference'],
		);
		if ( null !== $request['deposit_amount'] ) {
			$args['deposit_amount'] = (float) $request['deposit_amount'];
		}
		if ( 'reschedule' === $request['action'] ) {
			$start = Format::localDateTime( (string) $request['start'] );
			if ( null === $start ) {
				return self::invalid( __( 'Give a valid new start time.', 'ishot-booking' ) );
			}
			$args['start'] = $start;
		}

		try {
			( new StaffActions( $repo ) )->perform( $id, (string) $request['action'], (int) $request['version'], get_current_user_id(), $args );
		} catch ( StaleBooking $e ) {
			return new WP_Error( 'ishot_stale', __( 'Someone else changed this booking. Reload it and try again.', 'ishot-booking' ), array( 'status' => 409 ) );
		} catch ( SlotConflict $e ) {
			return self::slotTaken();
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'ishot_invalid_transition', $e->getMessage(), array( 'status' => 422 ) );
		}

		return new WP_REST_Response( self::present( $repo->find( $id ) ) );
	}

	public function block( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$start = Format::localDateTime( (string) $request['start'] );
		$end   = Format::localDateTime( (string) $request['end'] );
		if ( null === $start || null === $end || $end <= $start ) {
			return self::invalid( __( 'Give a valid time range: start must be before end.', 'ishot-booking' ) );
		}

		$repo = $this->repo();
		try {
			$id = $repo->create(
				new NewBooking(
					session: new TimeRange( $start, $end ),
					buffer_before_min: 0,
					buffer_after_min: 0,
					status: BookingStatus::Confirmed,
					kind: 'block',
					notes: (string) $request['reason'],
					client_user_id: get_current_user_id()
				)
			);
		} catch ( SlotConflict $e ) {
			return self::slotTaken();
		}
		return new WP_REST_Response( self::present( $repo->find( $id ) ), 201 );
	}

	public function unblock( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$repo = $this->repo();
		$row  = $repo->find( (int) $request['id'] );
		if ( null === $row || 'block' !== $row['kind'] ) {
			return self::notFound();
		}
		if ( BookingStatus::from( $row['status'] )->holdsSlot() ) {
			try {
				$repo->transition( (int) $row['id'], BookingStatus::Cancelled, (int) $row['version'], get_current_user_id() );
			} catch ( StaleBooking $e ) {
				return new WP_Error( 'ishot_stale', __( 'This block was just changed. Reload and try again.', 'ishot-booking' ), array( 'status' => 409 ) );
			}
		}
		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => (int) $row['id'],
			)
		);
	}
}
