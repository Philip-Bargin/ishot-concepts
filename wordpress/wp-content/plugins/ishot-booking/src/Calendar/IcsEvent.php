<?php
namespace IShot\Booking\Calendar;

use DateTimeImmutable;

/**
 * One VEVENT. The UID identifies the booking for its whole life; SEQUENCE must grow with every
 * update so calendar apps apply a reschedule or cancellation instead of ignoring it.
 */
final class IcsEvent {

	public const CONFIRMED = 'CONFIRMED';
	public const TENTATIVE = 'TENTATIVE';
	public const CANCELLED = 'CANCELLED';

	public function __construct(
		public readonly string $uid,
		public readonly int $sequence,
		public readonly DateTimeImmutable $start,
		public readonly DateTimeImmutable $end,
		public readonly string $summary,
		public readonly string $location,
		public readonly string $description,
		public readonly string $organizer_email,
		public readonly string $organizer_name,
		public readonly ?string $attendee_email,
		public readonly string $attendee_name,
		public readonly string $status,
		public readonly string $url = ''
	) {}
}
