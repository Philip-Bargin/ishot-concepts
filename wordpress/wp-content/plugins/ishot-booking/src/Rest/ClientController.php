<?php
namespace IShot\Booking\Rest;

use DateTimeImmutable;
use IShot\Booking\Application\BookingRequests;
use IShot\Booking\Application\ChangeRefused;
use IShot\Booking\Application\ClientBookings;
use IShot\Booking\Application\RequestOutcome;
use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Frontend\ManageUrl;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\CalendarBusy;
use IShot\Booking\Persistence\StaleBooking;
use IShot\Booking\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The client's manage-booking link: GET and PATCH /bookings/{token}.
 * The token is the credential. Unknown tokens get 404 so a guess reveals nothing, and
 * every request counts against a per-address limit to make guessing pointless.
 */
final class ClientController {

	public function register(): void {
		$change_args = array(
			'action' => array(
				'type'     => 'string',
				'enum'     => array( 'cancel', 'reschedule' ),
				'required' => true,
			),
			'start'  => array(
				'description' => 'New studio-local start for a reschedule, YYYY-MM-DDTHH:MM.',
				'type'        => 'string',
				'pattern'     => Format::DATETIME_PATTERN,
			),
		);

		// The standard route: the token travels in the X-Ishot-Manage-Token header, never in a URL
		// (see Frontend\ManageUrl).
		register_rest_route(
			PublicController::NAMESPACE,
			'/manage',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'change' ),
					'permission_callback' => '__return_true',
					'args'                => $change_args,
				),
			)
		);

		// Deprecated: the token in the path ends up in access logs. Kept for links and clients
		// made during testing; responses point to /manage.
		register_rest_route(
			PublicController::NAMESPACE,
			'/bookings/(?P<token>[0-9a-f]{64})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'change' ),
					'permission_callback' => '__return_true',
					'args'                => $change_args,
				),
			)
		);
	}

	/** The token from the header (standard) or the path (deprecated route); '' if neither. */
	private static function token( WP_REST_Request $request ): string {
		// Only the deprecated route's path segment counts; a ?token= query string never does.
		$path = $request->get_url_params()['token'] ?? null;
		if ( null !== $path ) {
			return (string) $path;
		}
		return ManageUrl::tokenFromHeader( $request->get_header( 'x_ishot_manage_token' ) );
	}

	private static function withDeprecation( WP_REST_Response|WP_Error $response, WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( $response instanceof WP_REST_Response && isset( $request->get_url_params()['token'] ) ) {
			$response->header( 'Deprecation', 'true' );
			$response->header( 'Link', '<' . rest_url( PublicController::NAMESPACE . '/manage' ) . '>; rel="successor-version"' );
		}
		return $response;
	}

	private function bookings(): ClientBookings {
		global $wpdb;
		$repo   = new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
		$policy = Settings::policy();
		return new ClientBookings(
			$repo,
			new BookingRequests( $repo, $policy, Settings::int( 'hold_hours' ) ),
			$policy,
			Settings::int( 'cancel_cutoff_hours' )
		);
	}

	private static function limited(): ?WP_Error {
		$limiter = new RateLimiter( 'manage', (int) apply_filters( 'ishot_booking_manage_rate_limit', 30 ), 10 * MINUTE_IN_SECONDS );
		return $limiter->hit() ? null : new WP_Error( 'ishot_rate_limited', __( 'Too many requests. Please wait a few minutes and try again.', 'ishot-booking' ), array( 'status' => 429 ) );
	}

	private static function notFound(): WP_Error {
		return new WP_Error( 'ishot_not_found', __( 'We could not find that booking. The link may have expired.', 'ishot-booking' ), array( 'status' => 404 ) );
	}

	/**
	 * What the client sees. No email or phone: a forwarded link should not leak contact details.
	 *
	 * @param array<string, mixed> $row
	 */
	private static function present( ClientBookings $bookings, array $row, DateTimeImmutable $now, int $status = 200 ): WP_REST_Response {
		$service    = null === $row['service_id'] ? null : get_post( (int) $row['service_id'] );
		$changeable = $bookings->canChange( $row, $now );
		$response   = new WP_REST_Response(
			array(
				'status'          => $row['status'],
				'name'            => $row['client_name'],
				'service'         => array(
					'id'    => null === $service ? null : $service->ID,
					'title' => null === $service || ServiceCatalog::POST_TYPE !== $service->post_type ? '' : get_the_title( $service ),
				),
				'start'           => Format::iso( $row['start_utc'] ),
				'end'             => Format::iso( $row['end_utc'] ),
				'location'        => $row['location'],
				'deposit'         => array(
					'amount' => null === $row['deposit_amount'] ? null : (float) $row['deposit_amount'],
					'status' => $row['deposit_status'],
				),
				'hold_expires_at' => Format::iso( $row['hold_expires_at'] ),
				'change_deadline' => Format::iso( $bookings->changeDeadline( $row ) ),
				'can_cancel'      => $changeable,
				'can_reschedule'  => $changeable,
			),
			$status
		);
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return self::withDeprecation( $this->doShow( $request, self::token( $request ) ), $request );
	}

	public function change( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return self::withDeprecation( $this->doChange( $request, self::token( $request ) ), $request );
	}

	private function doShow( WP_REST_Request $request, string $token ): WP_REST_Response|WP_Error {
		$limited = self::limited();
		if ( null !== $limited ) {
			return $limited;
		}
		$now      = new DateTimeImmutable( 'now' );
		$bookings = $this->bookings();
		$row      = $bookings->find( $token, $now );
		return null === $row ? self::notFound() : self::present( $bookings, $row, $now );
	}

	private function doChange( WP_REST_Request $request, string $token ): WP_REST_Response|WP_Error {
		$limited = self::limited();
		if ( null !== $limited ) {
			return $limited;
		}
		$now      = new DateTimeImmutable( 'now' );
		$bookings = $this->bookings();
		$row      = $bookings->find( $token, $now );
		if ( null === $row ) {
			return self::notFound();
		}

		try {
			if ( 'cancel' === $request['action'] ) {
				$bookings->cancel( $row, $now );
			} else {
				$start = Format::localDateTime( (string) $request['start'] );
				if ( null === $start ) {
					return new WP_Error( 'rest_invalid_param', __( 'Choose a new time.', 'ishot-booking' ), array( 'status' => 400 ) );
				}
				$outcome = $bookings->reschedule( $row, $start, $now );
				if ( RequestOutcome::REJECTED === $outcome->kind ) {
					return new WP_Error( 'ishot_' . $outcome->violation->value, $outcome->violation->message(), array( 'status' => 422 ) );
				}
				if ( RequestOutcome::CONFLICT === $outcome->kind ) {
					return new WP_Error(
						'ishot_slot_taken',
						__( 'That time has just been booked. Here are the nearest free times.', 'ishot-booking' ),
						array(
							'status'       => 409,
							'alternatives' => array_map( array( Format::class, 'iso' ), $outcome->alternatives ),
						)
					);
				}
			}
		} catch ( ChangeRefused $e ) {
			return new WP_Error( 'ishot_' . $e->reason, $e->getMessage(), array( 'status' => 422 ) );
		} catch ( StaleBooking $e ) {
			return new WP_Error( 'ishot_stale', __( 'This booking was just updated. Reload the page and try again.', 'ishot-booking' ), array( 'status' => 409 ) );
		} catch ( CalendarBusy $e ) {
			return BusyError::create();
		}

		return self::present( $bookings, $bookings->find( $token, $now ), $now );
	}
}
