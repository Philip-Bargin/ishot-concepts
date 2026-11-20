<?php
namespace IShot\Booking\Domain;

/**
 * Why a requested session time was refused. Values are stable API error codes.
 */
enum PolicyViolation: string {
	case TooSoon      = 'too_soon';
	case TooFar       = 'too_far';
	case Misaligned   = 'misaligned';
	case OutsideHours = 'outside_hours';

	public function message(): string {
		return match ( $this ) {
			self::TooSoon      => 'That time is too soon to book online.',
			self::TooFar       => 'That date is too far ahead to book yet.',
			self::Misaligned   => 'Sessions start on the half hour.',
			self::OutsideHours => 'That time is outside studio opening hours.',
		};
	}
}
