<?php
namespace IShot\Booking\Persistence;

use DateInterval;
use DateTimeImmutable;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;

/**
 * A booking (or staff time block) about to be created. Validation of the time against studio
 * policy happens before this point; the repository only guarantees exclusivity.
 */
final class NewBooking {

	public function __construct(
		public readonly TimeRange $session,
		public readonly int $buffer_before_min,
		public readonly int $buffer_after_min,
		public readonly BookingStatus $status,
		public readonly string $client_name = '',
		public readonly string $client_email = '',
		public readonly ?string $idempotency_key = null,
		public readonly ?int $service_id = null,
		public readonly int $resource_id = 1,
		public readonly string $kind = 'booking',
		public readonly string $client_phone = '',
		public readonly string $location = '',
		public readonly string $notes = '',
		public readonly ?int $client_user_id = null,
		public readonly ?DateTimeImmutable $hold_expires_at = null,
		public readonly ?string $public_token_hash = null
	) {}

	/** The session widened by its buffers: everything this booking keeps others out of. */
	public function blockedRange(): TimeRange {
		return new TimeRange(
			$this->session->start->sub( new DateInterval( "PT{$this->buffer_before_min}M" ) ),
			$this->session->end->add( new DateInterval( "PT{$this->buffer_after_min}M" ) )
		);
	}
}
