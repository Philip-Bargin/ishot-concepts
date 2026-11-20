<?php
namespace IShot\Booking\Application;

use RuntimeException;

/** A client change the booking's state or the cancellation cut-off does not allow. */
final class ChangeRefused extends RuntimeException {

	public const NOT_CHANGEABLE = 'not_changeable';
	public const PAST_CUTOFF    = 'past_cutoff';

	public function __construct( public readonly string $reason, string $message ) {
		parent::__construct( $message );
	}
}
