<?php
namespace IShot\Booking\Notifications;

use IShot\Booking\Calendar\Ics;

/**
 * Which emails each committed booking event produces. Pure: no WordPress, no database.
 *
 * A calendar file goes with the emails that create or move a confirmed session (REQUEST) and
 * with the cancellation of a session the client already had in their calendar (CANCEL).
 */
final class Rules {

	public const CLIENT = 'client';
	public const STUDIO = 'studio';

	/** Every template and who receives it. Timed templates are queued by Reminders, not by events. */
	public const TEMPLATES = array(
		'request_received'    => self::CLIENT,
		'new_request'         => self::STUDIO,
		'deposit_requested'   => self::CLIENT,
		'deposit_reminder'    => self::CLIENT,
		'hold_expiring'       => self::STUDIO,
		'confirmed'           => self::CLIENT,
		'rescheduled'         => self::CLIENT,
		'client_rescheduled'  => self::STUDIO,
		'cancelled'           => self::CLIENT,
		'client_cancelled'    => self::STUDIO,
		'hold_expired'        => self::CLIENT,
		'hold_expired_studio' => self::STUDIO,
		'reminder'            => self::CLIENT,
		'thank_you'           => self::CLIENT,
	);

	/**
	 * @param array<string, mixed> $payload    The audit event's payload (e.g. ['by' => 'client']).
	 * @param bool                 $by_staff   Whether a staff user made the change.
	 * @param string               $status_now The booking's status after the event.
	 * @return Planned[]
	 */
	public static function forEvent( string $action, ?string $from, ?string $to, array $payload, bool $by_staff, string $status_now ): array {
		if ( 'created' === $action ) {
			return 'requested' === $to
				? array( new Planned( 'request_received' ), new Planned( 'new_request' ) )
				: self::statusChanged( null, (string) $to, $payload, $by_staff );
		}
		if ( 'status_changed' === $action ) {
			return self::statusChanged( $from, (string) $to, $payload, $by_staff );
		}
		if ( 'rescheduled' === $action ) {
			$planned = array( new Planned( 'rescheduled', 'confirmed' === $status_now ? Ics::REQUEST : null ) );
			if ( ! $by_staff ) {
				$planned[] = new Planned( 'client_rescheduled' );
			}
			return $planned;
		}
		return array();
	}

	/** @return Planned[] */
	private static function statusChanged( ?string $from, string $to, array $payload, bool $by_staff ): array {
		return match ( $to ) {
			'pending_deposit' => array( new Planned( 'deposit_requested' ) ),
			'confirmed'       => array( new Planned( 'confirmed', Ics::REQUEST ) ),
			'cancelled'       => array_merge(
				array( new Planned( 'cancelled', 'confirmed' === $from ? Ics::CANCEL : null ) ),
				'client' === ( $payload['by'] ?? '' ) && ! $by_staff ? array( new Planned( 'client_cancelled' ) ) : array()
			),
			'expired'         => array( new Planned( 'hold_expired' ), new Planned( 'hold_expired_studio' ) ),
			'completed'       => array( new Planned( 'thank_you' ) ),
			default           => array(),
		};
	}
}
