<?php
namespace IShot\Booking\Tests\Unit;

use IShot\Booking\Calendar\Ics;
use IShot\Booking\Notifications\Rules;
use PHPUnit\Framework\TestCase;

/** Which emails a committed booking event produces (the plan's notification list). */
final class NotificationRulesTest extends TestCase {

	/** @return array<string, ?string> template => calendar method */
	private static function plan( string $action, ?string $from, ?string $to, array $payload = array(), bool $by_staff = true, string $status_now = '' ): array {
		$out = array();
		foreach ( Rules::forEvent( $action, $from, $to, $payload, $by_staff, $status_now ?: (string) $to ) as $planned ) {
			$out[ $planned->template ] = $planned->calendar;
		}
		return $out;
	}

	public function test_a_new_request_tells_the_client_and_the_studio(): void {
		$this->assertSame( array( 'request_received' => null, 'new_request' => null ), self::plan( 'created', null, 'requested', array(), false ) );
	}

	public function test_asking_for_a_deposit_sends_the_instructions(): void {
		$this->assertSame( array( 'deposit_requested' => null ), self::plan( 'status_changed', 'requested', 'pending_deposit' ) );
	}

	public function test_confirmation_carries_a_calendar_request(): void {
		$this->assertSame( array( 'confirmed' => Ics::REQUEST ), self::plan( 'status_changed', 'pending_deposit', 'confirmed' ) );
		$this->assertSame( array( 'confirmed' => Ics::REQUEST ), self::plan( 'status_changed', 'requested', 'confirmed' ) );
	}

	public function test_cancelling_a_confirmed_session_withdraws_the_calendar_event(): void {
		$this->assertSame( array( 'cancelled' => Ics::CANCEL ), self::plan( 'status_changed', 'confirmed', 'cancelled', array( 'by' => 'studio' ) ) );
	}

	public function test_cancelling_an_unconfirmed_request_sends_no_calendar_file(): void {
		$this->assertSame( array( 'cancelled' => null ), self::plan( 'status_changed', 'pending_deposit', 'cancelled', array( 'by' => 'studio' ) ) );
	}

	public function test_a_client_cancellation_also_tells_the_studio(): void {
		$this->assertSame(
			array( 'cancelled' => Ics::CANCEL, 'client_cancelled' => null ),
			self::plan( 'status_changed', 'confirmed', 'cancelled', array( 'by' => 'client' ), false )
		);
	}

	public function test_an_expired_hold_tells_both_sides(): void {
		$this->assertSame( array( 'hold_expired' => null, 'hold_expired_studio' => null ), self::plan( 'status_changed', 'pending_deposit', 'expired', array(), false ) );
	}

	public function test_a_completed_session_sends_a_thank_you(): void {
		$this->assertSame( array( 'thank_you' => null ), self::plan( 'status_changed', 'confirmed', 'completed' ) );
	}

	public function test_a_no_show_sends_nothing(): void {
		$this->assertSame( array(), self::plan( 'status_changed', 'confirmed', 'no_show' ) );
	}

	public function test_rescheduling_a_confirmed_session_updates_the_calendar_event(): void {
		$this->assertSame( array( 'rescheduled' => Ics::REQUEST ), self::plan( 'rescheduled', 'confirmed', 'confirmed', array(), true, 'confirmed' ) );
	}

	public function test_rescheduling_an_unconfirmed_request_sends_no_calendar_file(): void {
		$this->assertSame( array( 'rescheduled' => null ), self::plan( 'rescheduled', 'requested', 'requested', array(), true, 'requested' ) );
	}

	public function test_a_client_reschedule_also_tells_the_studio(): void {
		$this->assertSame(
			array( 'rescheduled' => Ics::REQUEST, 'client_rescheduled' => null ),
			self::plan( 'rescheduled', 'confirmed', 'confirmed', array(), false, 'confirmed' )
		);
	}

	public function test_unknown_actions_send_nothing(): void {
		$this->assertSame( array(), self::plan( 'note_added', null, null ) );
	}

	public function test_every_planned_template_is_registered_with_a_recipient(): void {
		foreach ( Rules::TEMPLATES as $template => $recipient ) {
			$this->assertContains( $recipient, array( Rules::CLIENT, Rules::STUDIO ), $template );
		}
		foreach ( array( 'reminder', 'deposit_reminder', 'hold_expiring' ) as $timed ) {
			$this->assertArrayHasKey( $timed, Rules::TEMPLATES );
		}
	}
}
