<?php
namespace IShot\Booking\Rest;

use WP_Error;

/**
 * The REST answer to CalendarBusy: every attempt lost a database deadlock while the time was
 * still free, so nothing changed and the same request can simply be sent again.
 */
final class BusyError {

	public static function create(): WP_Error {
		return new WP_Error(
			'ishot_busy',
			__( 'The calendar is busy right now. Nothing was changed. Please try again in a moment.', 'ishot-booking' ),
			array( 'status' => 503 )
		);
	}
}
