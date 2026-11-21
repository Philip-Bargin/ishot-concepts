<?php
namespace IShot\Booking\Rest;

use DateTimeImmutable;
use IShot\Booking\Accounts\ClientAccounts;
use IShot\Booking\Application\BookingRequests;
use IShot\Booking\Application\RequestOutcome;
use IShot\Booking\Catalog\Service;
use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public, unauthenticated routes used by the booking flow: services, availability and new
 * booking requests. Every write is rate-limited, honeypot-checked and policy-checked on the server.
 */
final class PublicController {

	public const NAMESPACE = 'ishot/v1';

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/services',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'services' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/availability',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'availability' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'service' => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'required' => true,
					),
					'date'    => array(
						'type'     => 'string',
						'pattern'  => Format::DATE_PATTERN,
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/bookings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'service'        => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'required' => true,
					),
					'start'          => array(
						'description' => 'Studio-local start, YYYY-MM-DDTHH:MM.',
						'type'        => 'string',
						'pattern'     => Format::DATETIME_PATTERN,
						'required'    => true,
					),
					'name'           => array(
						'type'              => 'string',
						'minLength'         => 1,
						'maxLength'         => 100,
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg', // a custom sanitizer otherwise skips schema checks.
					),
					'email'          => array(
						'type'      => 'string',
						'format'    => 'email',
						'maxLength' => 254,
						'required'  => true,
					),
					'phone'          => array(
						'type'              => 'string',
						'maxLength'         => 30,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg', // a custom sanitizer otherwise skips schema checks.
					),
					'location'       => array(
						'type'              => 'string',
						'maxLength'         => 120,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg', // a custom sanitizer otherwise skips schema checks.
					),
					'notes'          => array(
						'type'              => 'string',
						'maxLength'         => 2000,
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => 'rest_validate_request_arg', // a custom sanitizer otherwise skips schema checks.
					),
					'consent'        => array(
						'description' => 'The client agreed to the privacy notice.',
						'type'        => 'boolean',
						'enum'        => array( true ),
						'required'    => true,
					),
					'create_account' => array(
						'description' => 'Guest asks for a client account (emailed a set-your-password link).',
						'type'        => 'boolean',
						'default'     => false,
					),
					// Honeypot: hidden from people, filled in by bots.
					'website'        => array(
						'type'      => 'string',
						'maxLength' => 500,
						'default'   => '',
					),
				),
			)
		);
	}

	private function requests(): BookingRequests {
		global $wpdb;
		return new BookingRequests(
			new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) ),
			Settings::policy(),
			Settings::int( 'hold_hours' )
		);
	}

	private static function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', wp_timezone() );
	}

	private static function unknownService(): WP_Error {
		return new WP_Error( 'ishot_unknown_service', __( 'That service is not available to book.', 'ishot-booking' ), array( 'status' => 422 ) );
	}

	public function services(): WP_REST_Response {
		return new WP_REST_Response( array_map( static fn( Service $s ) => $s->toPublic(), ServiceCatalog::allBookable() ) );
	}

	public function availability( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$service = ServiceCatalog::bookable( (int) $request['service'] );
		if ( null === $service ) {
			return self::unknownService();
		}
		$date = (string) $request['date'];
		if ( null === Format::localDateTime( "{$date}T00:00" ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'Invalid date.', 'ishot-booking' ), array( 'status' => 400 ) );
		}

		$response = new WP_REST_Response(
			array(
				'date'  => $date,
				'times' => $this->requests()->startTimes( $service, $date, self::now() ),
			)
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limiter = new RateLimiter( 'book', (int) apply_filters( 'ishot_booking_rate_limit', 10 ), 10 * MINUTE_IN_SECONDS );
		if ( ! $limiter->hit() ) {
			return new WP_Error( 'ishot_rate_limited', __( 'Too many requests. Please wait a few minutes and try again.', 'ishot-booking' ), array( 'status' => 429 ) );
		}

		if ( '' !== trim( (string) $request['website'] ) ) {
			// Looks accepted to the bot; nothing is stored or sent.
			return new WP_REST_Response( array( 'status' => 'received' ), 202 );
		}

		$key = $request->get_header( 'idempotency_key' );
		if ( null !== $key && ! preg_match( '/^[A-Za-z0-9_-]{8,64}$/', $key ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'Invalid Idempotency-Key header.', 'ishot-booking' ), array( 'status' => 400 ) );
		}

		$start = Format::localDateTime( (string) $request['start'] );
		if ( null === $start ) {
			return new WP_Error( 'rest_invalid_param', __( 'Invalid start time.', 'ishot-booking' ), array( 'status' => 400 ) );
		}

		$service = ServiceCatalog::bookable( (int) $request['service'] );
		if ( null === $service ) {
			return self::unknownService();
		}

		$requests = $this->requests();
		$outcome  = $requests->submit(
			$service,
			$start,
			array(
				'name'     => (string) $request['name'],
				'email'    => sanitize_email( (string) $request['email'] ),
				'phone'    => (string) $request['phone'],
				'location' => (string) $request['location'],
				'notes'    => (string) $request['notes'],
			),
			$key,
			self::now()
		);

		return match ( $outcome->kind ) {
			RequestOutcome::REJECTED => new WP_Error( 'ishot_' . $outcome->violation->value, $outcome->violation->message(), array( 'status' => 422 ) ),
			RequestOutcome::CONFLICT => new WP_Error(
				'ishot_slot_taken',
				__( 'That time has just been booked. Here are the nearest free times.', 'ishot-booking' ),
				array(
					'status'       => 409,
					'alternatives' => array_map( array( Format::class, 'iso' ), $outcome->alternatives ),
				)
			),
			RequestOutcome::REPLAYED => new WP_REST_Response( $this->summary( $outcome->booking_id ) + array( 'replayed' => true ), 200 ),
			default                  => new WP_REST_Response( $this->summary( $outcome->booking_id ) + array( 'token' => $outcome->token ) + $this->account( $outcome->booking_id, $request ), 201 ),
		};
	}

	/**
	 * Links a new booking to a client: the signed-in client's own account, or (when a guest asks)
	 * a new account that becomes theirs once they set its password from the email.
	 *
	 * @return array{account?: string}
	 */
	private function account( int $booking_id, WP_REST_Request $request ): array {
		global $wpdb;
		$repo = new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
		if ( is_user_logged_in() ) {
			// Only client accounts own bookings; staff booking on someone's behalf stay unlinked.
			if ( current_user_can( 'read_own_ishot_bookings' ) && $repo->linkToUser( $booking_id, get_current_user_id() ) ) {
				return array( 'account' => ClientAccounts::LINKED );
			}
			return array();
		}
		if ( true !== $request['create_account'] ) {
			return array();
		}
		$result = ClientAccounts::requestForBooking( (string) $request['email'], (string) $request['name'] );
		if ( null !== $result['user_id'] ) {
			$repo->linkToUser( $booking_id, $result['user_id'] );
		}
		return array( 'account' => $result['status'] );
	}

	/** @return array<string, string> */
	private function summary( int $booking_id ): array {
		global $wpdb;
		$row = ( new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) ) )->find( $booking_id );
		return array(
			'status' => (string) $row['status'],
			'start'  => Format::iso( $row['start_utc'] ),
			'end'    => Format::iso( $row['end_utc'] ),
		);
	}
}
