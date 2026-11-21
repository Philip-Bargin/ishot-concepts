<?php
namespace IShot\Booking\Notifications;

/** One email a booking event calls for: its template and, if any, the calendar method to attach. */
final class Planned {

	public function __construct(
		public readonly string $template,
		public readonly ?string $calendar = null
	) {}
}
