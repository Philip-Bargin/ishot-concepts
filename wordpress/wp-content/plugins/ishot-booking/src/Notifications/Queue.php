<?php
namespace IShot\Booking\Notifications;

use DateTimeImmutable;

/**
 * Action Scheduler jobs for notifications.
 *
 * - SEND_HOOK: enqueued (unique) after a transaction commits with emails in the outbox; drains it.
 * - TICK_HOOK: every five minutes; requeues abandoned sends, queues timed reminders and sends
 *   anything due, including retries whose back-off has passed. It is the safety net: even if a
 *   SEND_HOOK enqueue were lost, nothing in the outbox waits longer than one tick.
 */
final class Queue {

	public const SEND_HOOK = 'ishot_booking_send_notifications';
	public const TICK_HOOK = 'ishot_booking_notifications_tick';
	public const GROUP     = 'ishot-booking';
	public const INTERVAL  = 5 * MINUTE_IN_SECONDS;

	/** Listener for Outbox::QUEUED_HOOK (fires only after COMMIT). */
	public static function kick(): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::SEND_HOOK, array(), self::GROUP, true );
		}
	}

	public static function ensureScheduled(): void {
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::TICK_HOOK, array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, self::INTERVAL, self::TICK_HOOK, array(), self::GROUP, true );
		}
	}

	public static function unschedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::TICK_HOOK, array(), self::GROUP );
			as_unschedule_all_actions( self::SEND_HOOK, array(), self::GROUP );
		}
	}

	public static function send(): void {
		( new Dispatcher() )->sendDue( new DateTimeImmutable( 'now' ) );
	}

	public static function tick(): void {
		$now        = new DateTimeImmutable( 'now' );
		$dispatcher = new Dispatcher();
		$dispatcher->recoverStale( $now );
		( new Reminders() )->queueDue( $now );
		$dispatcher->sendDue( $now );
		( new \IShot\Booking\Contact\Enquiries() )->sendDue( $now ); // safety net for contact enquiries
	}
}
