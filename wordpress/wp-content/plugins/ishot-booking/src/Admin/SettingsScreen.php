<?php
namespace IShot\Booking\Admin;

use IShot\Booking\Settings;

/** Opening hours and booking-policy screens. Validation lives in SettingsForm. */
final class SettingsScreen {

	public const POST_HOURS  = 'ishot_save_hours';
	public const POST_POLICY = 'ishot_save_policy';

	private static function errorsKey(): string {
		return 'ishot_settings_errors_' . get_current_user_id();
	}

	/** @return array<string, string> Errors from the last failed save, shown once. */
	private static function takeErrors(): array {
		$errors = get_transient( self::errorsKey() );
		delete_transient( self::errorsKey() );
		return is_array( $errors ) ? $errors : array();
	}

	private static function fieldError( array $errors, string $key ): void {
		if ( isset( $errors[ $key ] ) ) {
			printf( '<p class="ishot-field-error" id="err-%1$s">%2$s</p>', esc_attr( $key ), esc_html( $errors[ $key ] ) );
		}
	}

	private static function weekdayName( int $iso ): string {
		// 2026-11-16 is a Monday; add (ISO weekday - 1) days. Noon UTC keeps the date stable in any site timezone.
		return (string) wp_date( 'l', gmmktime( 12, 0, 0, 11, 15 + $iso, 2026 ), new \DateTimeZone( 'UTC' ) );
	}

	public static function hoursPage(): void {
		$errors   = self::takeErrors();
		$settings = Settings::all();
		echo '<div class="wrap ishot-admin"><h1>' . esc_html__( 'Opening hours', 'ishot-booking' ) . '</h1>';
		ActionHandler::notice();
		echo '<p>' . esc_html( sprintf( /* translators: %s: timezone */ __( 'Times are in studio time (%s). Clients can only book inside these hours.', 'ishot-booking' ), wp_timezone_string() ) ) . '</p>';
		echo View::formOpen( self::POST_HOURS, self::POST_HOURS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formOpen() escapes.

		echo '<h2>' . esc_html__( 'Weekly hours', 'ishot-booking' ) . '</h2><table class="widefat striped ishot-hours"><thead><tr><th scope="col">' . esc_html__( 'Day', 'ishot-booking' ) . '</th><th scope="col">' . esc_html__( 'Closed', 'ishot-booking' ) . '</th><th scope="col">' . esc_html__( 'Opens', 'ishot-booking' ) . '</th><th scope="col">' . esc_html__( 'Closes', 'ishot-booking' ) . '</th></tr></thead><tbody>';
		for ( $day = 1; $day <= 7; $day++ ) {
			$window = $settings['weekly_hours'][ $day ] ?? null;
			$name   = self::weekdayName( $day );
			printf(
				'<tr><th scope="row">%1$s</th><td><input type="checkbox" name="weekly[%2$d][closed]" value="1" aria-label="%3$s"%4$s></td><td><input type="time" name="weekly[%2$d][open]" value="%5$s" aria-label="%6$s"></td><td><input type="time" name="weekly[%2$d][close]" value="%7$s" aria-label="%8$s"></td></tr>',
				esc_html( $name ),
				$day,
				esc_attr( sprintf( /* translators: %s: weekday */ __( '%s closed', 'ishot-booking' ), $name ) ),
				checked( null === $window, true, false ),
				esc_attr( $window[0] ?? '09:00' ),
				esc_attr( sprintf( /* translators: %s: weekday */ __( '%s opens', 'ishot-booking' ), $name ) ),
				esc_attr( $window[1] ?? '18:00' ),
				esc_attr( sprintf( /* translators: %s: weekday */ __( '%s closes', 'ishot-booking' ), $name ) )
			);
			if ( isset( $errors[ "weekly_{$day}" ] ) ) {
				echo '<tr><td colspan="4">';
				self::fieldError( $errors, "weekly_{$day}" );
				echo '</td></tr>';
			}
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Holidays and special days', 'ishot-booking' ) . '</h2><p class="ishot-muted">' . esc_html__( 'Override the weekly hours for a date. Clear a date to remove the row.', 'ishot-booking' ) . '</p>';
		echo '<table class="widefat striped ishot-hours"><thead><tr><th scope="col">' . esc_html__( 'Date', 'ishot-booking' ) . '</th><th scope="col">' . esc_html__( 'Closed', 'ishot-booking' ) . '</th><th scope="col">' . esc_html__( 'Opens', 'ishot-booking' ) . '</th><th scope="col">' . esc_html__( 'Closes', 'ishot-booking' ) . '</th></tr></thead><tbody>';
		$rows   = array();
		foreach ( (array) $settings['exceptions'] as $date => $window ) {
			$rows[] = array( $date, $window );
		}
		$rows[] = array( '', array( '', '' ) ); // one blank row to add a date
		$rows[] = array( '', array( '', '' ) );
		foreach ( $rows as $i => [ $date, $window ] ) {
			printf(
				'<tr><td><input type="date" name="exceptions[%1$d][date]" value="%2$s" aria-label="%3$s"></td><td><input type="checkbox" name="exceptions[%1$d][closed]" value="1" aria-label="%4$s"%5$s></td><td><input type="time" name="exceptions[%1$d][open]" value="%6$s" aria-label="%7$s"></td><td><input type="time" name="exceptions[%1$d][close]" value="%8$s" aria-label="%9$s"></td></tr>',
				$i,
				esc_attr( $date ),
				esc_attr__( 'Date', 'ishot-booking' ),
				esc_attr__( 'Closed all day', 'ishot-booking' ),
				checked( '' !== $date && null === $window, true, false ),
				esc_attr( $window[0] ?? '' ),
				esc_attr__( 'Opens', 'ishot-booking' ),
				esc_attr( $window[1] ?? '' ),
				esc_attr__( 'Closes', 'ishot-booking' )
			);
			if ( isset( $errors[ "exception_{$i}" ] ) ) {
				echo '<tr><td colspan="4">';
				self::fieldError( $errors, "exception_{$i}" );
				echo '</td></tr>';
			}
		}
		echo '</tbody></table>';
		submit_button( __( 'Save opening hours', 'ishot-booking' ) );
		echo '</form></div>';
	}

	public static function policyPage(): void {
		$errors   = self::takeErrors();
		$settings = Settings::all();
		$labels   = array(
			'lead_time_hours'     => array( __( 'Minimum notice', 'ishot-booking' ), __( 'hours before a session that clients can still book', 'ishot-booking' ) ),
			'horizon_days'        => array( __( 'Booking window', 'ishot-booking' ), __( 'days ahead that clients can book', 'ishot-booking' ) ),
			'hold_hours'          => array( __( 'Hold time', 'ishot-booking' ), __( 'hours an unconfirmed request keeps its slot', 'ishot-booking' ) ),
			'cancel_cutoff_hours' => array( __( 'Change cut-off', 'ishot-booking' ), __( 'hours before a session after which clients must contact the studio to cancel or move it', 'ishot-booking' ) ),
		);
		echo '<div class="wrap ishot-admin"><h1>' . esc_html__( 'Booking settings', 'ishot-booking' ) . '</h1>';
		ActionHandler::notice();
		echo View::formOpen( self::POST_POLICY, self::POST_POLICY ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formOpen() escapes.
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( SettingsForm::POLICY_FIELDS as $field => [ $min, $max ] ) {
			printf(
				'<tr><th scope="row"><label for="ishot-%1$s">%2$s</label></th><td><input id="ishot-%1$s" name="%1$s" type="number" min="%3$d" max="%4$d" value="%5$d" class="small-text"%6$s> <span class="description" id="desc-%1$s">%7$s</span>',
				esc_attr( $field ),
				esc_html( $labels[ $field ][0] ),
				$min,
				$max,
				(int) $settings[ $field ],
				isset( $errors[ $field ] ) ? ' aria-invalid="true" aria-describedby="err-' . esc_attr( $field ) . ' desc-' . esc_attr( $field ) . '"' : ' aria-describedby="desc-' . esc_attr( $field ) . '"',
				esc_html( $labels[ $field ][1] )
			);
			self::fieldError( $errors, $field );
			echo '</td></tr>';
		}
		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s <p class="description">%3$s</p></td></tr>',
			esc_html__( 'Slot length', 'ishot-booking' ),
			esc_html( sprintf( /* translators: %d: minutes */ __( '%d minutes', 'ishot-booking' ), Settings::int( 'granularity_minutes' ) ) ),
			esc_html__( 'Fixed: existing bookings are locked in slots of this length, so changing it would allow overlaps.', 'ishot-booking' )
		);
		echo '</tbody></table>';
		submit_button( __( 'Save settings', 'ishot-booking' ) );
		echo '</form></div>';
	}

	/** @param array<string, string> $errors */
	private static function finish( array $errors, string $slug ): never {
		if ( array() !== $errors ) {
			set_transient( self::errorsKey(), $errors, 5 * MINUTE_IN_SECONDS );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => $slug,
					ActionHandler::NOTICE_PARAM => array() === $errors ? 'saved' : 'settings_invalid',
					'ishot_type'                 => array() === $errors ? 'success' : 'error',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handleHours(): void {
		if ( ! current_user_can( 'manage_ishot_availability' ) ) {
			wp_die( esc_html__( 'Your role does not allow that action.', 'ishot-booking' ), 403 );
		}
		check_admin_referer( self::POST_HOURS );
		self::finish( SettingsForm::saveHours( wp_unslash( $_POST ) ), Menu::HOURS_SLUG );
	}

	public static function handlePolicy(): void {
		if ( ! current_user_can( 'manage_ishot_settings' ) ) {
			wp_die( esc_html__( 'Your role does not allow that action.', 'ishot-booking' ), 403 );
		}
		check_admin_referer( self::POST_POLICY );
		self::finish( SettingsForm::savePolicy( wp_unslash( $_POST ) ), Menu::SET_SLUG );
	}
}
