<?php
namespace IShot\Booking\Application;

use DateTimeImmutable;
use IShot\Booking\Domain\PolicyViolation;

/** Result of a client booking request. Exactly one of the shapes below. */
final class RequestOutcome {

	public const CREATED  = 'created';
	public const REPLAYED = 'replayed';
	public const REJECTED = 'rejected';
	public const CONFLICT = 'conflict';

	/**
	 * @param DateTimeImmutable[] $alternatives
	 */
	private function __construct(
		public readonly string $kind,
		public readonly ?int $booking_id = null,
		public readonly ?string $token = null,
		public readonly ?PolicyViolation $violation = null,
		public readonly array $alternatives = array()
	) {}

	public static function created( int $id, string $token ): self {
		return new self( self::CREATED, $id, $token );
	}

	public static function replayed( int $id ): self {
		return new self( self::REPLAYED, $id );
	}

	public static function rejected( PolicyViolation $violation ): self {
		return new self( self::REJECTED, violation: $violation );
	}

	/** @param DateTimeImmutable[] $alternatives */
	public static function conflict( array $alternatives ): self {
		return new self( self::CONFLICT, alternatives: $alternatives );
	}
}
