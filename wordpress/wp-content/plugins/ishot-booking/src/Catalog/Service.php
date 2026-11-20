<?php
namespace IShot\Booking\Catalog;

/** A bookable studio service, read from the ishot_service post type. */
final class Service {

	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly int $duration_min,
		public readonly int $buffer_before_min,
		public readonly int $buffer_after_min,
		public readonly float $price_from,
		public readonly float $deposit_amount,
		public readonly bool $bookable
	) {}

	/** @return array<string, mixed> Public fields for the booking flow. */
	public function toPublic(): array {
		return array(
			'id'             => $this->id,
			'title'          => $this->title,
			'duration_min'   => $this->duration_min,
			'price_from'     => $this->price_from,
			'deposit_amount' => $this->deposit_amount,
		);
	}
}
