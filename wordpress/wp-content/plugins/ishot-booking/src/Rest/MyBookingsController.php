<?php
namespace IShot\Booking\Rest;

use IShot\Booking\Accounts\ClientAccounts;
use IShot\Booking\Catalog\ServiceCatalog;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /me/bookings: the signed-in client's own bookings ("My bookings").
 *
 * Only accounts with read_own_ishot_bookings (the client role) may call it, and the query is
 * always scoped to the caller: there is no parameter that names another user.
 */
final class MyBookingsController {

	public const MAX_PER_PAGE = 50;

	public function register(): void {
		register_rest_route(
			PublicController::NAMESPACE,
			'/me/bookings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( self::class, 'canRead' ),
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => self::MAX_PER_PAGE,
						'default' => 10,
					),
					'when'     => array(
						'type'    => 'string',
						'enum'    => array( 'all', 'upcoming', 'past' ),
						'default' => 'all',
					),
				),
			)
		);
	}

	public static function canRead(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', __( 'Sign in to see your bookings.', 'ishot-booking' ), array( 'status' => 401 ) );
		}
		return current_user_can( 'read_own_ishot_bookings' )
			? true
			: new WP_Error( 'rest_forbidden', __( 'This account has no client bookings.', 'ishot-booking' ), array( 'status' => 403 ) );
	}

	public function index( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$user_id  = get_current_user_id();
		$per_page = (int) $request['per_page'];
		$result   = ( new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) ) )->search(
			array(
				'owner'       => $user_id,
				'owner_email' => ClientAccounts::verifiedEmail( $user_id ),
				'when'        => (string) $request['when'],
				'now'         => gmdate( 'Y-m-d H:i:s' ),
			),
			$per_page,
			(int) $request['page']
		);

		$response = new WP_REST_Response( array_map( array( self::class, 'present' ), $result['rows'] ) );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / $per_page ) );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed> What the client may see; no token hashes, keys or versions.
	 */
	public static function present( array $row ): array {
		$service = null === $row['service_id'] ? null : get_post( (int) $row['service_id'] );
		return array(
			'id'              => (int) $row['id'],
			'status'          => $row['status'],
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
		);
	}
}
