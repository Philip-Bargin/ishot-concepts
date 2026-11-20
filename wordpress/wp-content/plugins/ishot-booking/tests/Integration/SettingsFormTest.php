<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Admin\SettingsForm;
use IShot\Booking\Settings;
use PHPUnit\Framework\TestCase;

/** Validation for the Settings and Opening hours screens. Invalid input saves nothing. */
final class SettingsFormTest extends TestCase {

	protected function setUp(): void {
		delete_option( Settings::OPTION );
	}

	private static function week( array $overrides = array() ): array {
		$week = array();
		for ( $day = 1; $day <= 7; $day++ ) {
			$week[ $day ] = array(
				'open'  => '09:00',
				'close' => '18:00',
			);
		}
		$week[7] = array( 'closed' => '1' );
		return array_replace( $week, $overrides );
	}

	public function test_valid_policy_is_saved_as_integers(): void {
		$errors = SettingsForm::savePolicy(
			array(
				'lead_time_hours'     => '24',
				'horizon_days'        => '90',
				'hold_hours'          => '72',
				'cancel_cutoff_hours' => '48',
			)
		);

		$this->assertSame( array(), $errors );
		$this->assertSame( 24, Settings::all()['lead_time_hours'] );
		$this->assertSame( 90, Settings::all()['horizon_days'] );
		$this->assertSame( 72, Settings::all()['hold_hours'] );
		$this->assertSame( 48, Settings::all()['cancel_cutoff_hours'] );
	}

	public function test_out_of_range_policy_is_rejected_and_nothing_changes(): void {
		$errors = SettingsForm::savePolicy(
			array(
				'lead_time_hours'     => '-1',
				'horizon_days'        => '0',
				'hold_hours'          => 'abc',
				'cancel_cutoff_hours' => '48',
			)
		);

		$this->assertSame( array( 'lead_time_hours', 'horizon_days', 'hold_hours' ), array_keys( $errors ) );
		$this->assertFalse( get_option( Settings::OPTION ) );
	}

	public function test_slot_length_cannot_be_changed_from_the_form(): void {
		SettingsForm::savePolicy(
			array(
				'lead_time_hours'     => '24',
				'horizon_days'        => '90',
				'hold_hours'          => '72',
				'cancel_cutoff_hours' => '48',
				'granularity_minutes' => '15',
			)
		);

		$this->assertSame( 30, Settings::int( 'granularity_minutes' ) );
	}

	public function test_weekly_hours_and_exceptions_are_saved(): void {
		$errors = SettingsForm::saveHours(
			array(
				'weekly'     => self::week( array( 6 => array( 'open' => '10:00', 'close' => '15:00' ) ) ),
				'exceptions' => array(
					array( 'date' => '2026-12-25', 'closed' => '1' ),
					array( 'date' => '2026-12-31', 'open' => '09:00', 'close' => '13:00' ),
					array( 'date' => '' ), // blank row from the form
				),
			)
		);

		$this->assertSame( array(), $errors );
		$saved = Settings::all();
		$this->assertSame( array( '10:00', '15:00' ), $saved['weekly_hours'][6] );
		$this->assertArrayNotHasKey( 7, $saved['weekly_hours'] );
		$this->assertSame(
			array(
				'2026-12-25' => null,
				'2026-12-31' => array( '09:00', '13:00' ),
			),
			$saved['exceptions']
		);
		$this->assertNull( Settings::policy()->hours->windowFor( '2026-12-25' ) );
	}

	public function test_impossible_hours_are_rejected(): void {
		$errors = SettingsForm::saveHours(
			array(
				'weekly'     => self::week(
					array(
						1 => array( 'open' => '18:00', 'close' => '09:00' ),
						2 => array( 'open' => '9am', 'close' => '17:00' ),
						3 => array( 'open' => '09:15', 'close' => '17:00' ), // not on a 30-minute boundary
					)
				),
				'exceptions' => array(
					array( 'date' => '2026-02-30', 'closed' => '1' ),
				),
			)
		);

		$this->assertSame( array( 'weekly_1', 'weekly_2', 'weekly_3', 'exception_0' ), array_keys( $errors ) );
		$this->assertFalse( get_option( Settings::OPTION ) );
	}

	public function test_saving_hours_keeps_the_policy_and_vice_versa(): void {
		SettingsForm::savePolicy(
			array(
				'lead_time_hours'     => '24',
				'horizon_days'        => '90',
				'hold_hours'          => '72',
				'cancel_cutoff_hours' => '48',
			)
		);
		SettingsForm::saveHours( array( 'weekly' => self::week() ) );

		$this->assertSame( 24, Settings::all()['lead_time_hours'] );
		$this->assertSame( array(), Settings::all()['exceptions'] );
	}
}
