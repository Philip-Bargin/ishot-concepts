<?php
namespace IShot\Booking\Rest;

use WP_REST_Response;

/**
 * The REST answer to CalendarBusy: every attempt lost a database deadlock while the time was
 * still free, so nothing changed and the same request (same Idempotency-Key) can simply be sent
 * again. A response rather than a WP_Error so it can carry Retry-After (WordPress drops headers
 * from WP_Error); the body has the same {code, message, data} shape as every other REST error.
 * Never includes the database's own error text.
 */
final class BusyError {

	/** Seconds a client should wait before sending the request again. */
	public const RETRY_AFTER = 2;

	public static function create(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'code'    => 'ishot_busy',
				'message' => __( 'The calendar is busy right now. Nothing was changed. Please try again in a moment.', 'ishot-booking' ),
				'data'    => array(
					'status'      => 503,
					'retry_after' => self::RETRY_AFTER,
				),
			),
			503,
			array( 'Retry-After' => (string) self::RETRY_AFTER )
		);
	}
}
