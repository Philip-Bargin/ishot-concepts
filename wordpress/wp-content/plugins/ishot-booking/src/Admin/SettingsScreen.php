<?php
namespace IShot\Booking\Admin;

use IShot\Booking\Settings;
use IShot\Booking\Studio;

/** Opening hours and booking-policy screens. Validation lives in SettingsForm. */
final class SettingsScreen {

	public const POST_HOURS  = 'ishot_save_hours';
	public const POST_POLICY = 'ishot_save_policy';
	public const POST_STUDIO = 'ishot_save_studio';

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
				(int) $day,
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
		$rows = array();
		foreach ( (array) $settings['exceptions'] as $date => $window ) {
			$rows[] = array( $date, $window );
		}
		$rows[] = array( '', array( '', '' ) ); // one blank row to add a date
		$rows[] = array( '', array( '', '' ) );
		foreach ( $rows as $i => [ $date, $window ] ) {
			printf(
				'<tr><td><input type="date" name="exceptions[%1$d][date]" value="%2$s" aria-label="%3$s"></td><td><input type="checkbox" name="exceptions[%1$d][closed]" value="1" aria-label="%4$s"%5$s></td><td><input type="time" name="exceptions[%1$d][open]" value="%6$s" aria-label="%7$s"></td><td><input type="time" name="exceptions[%1$d][close]" value="%8$s" aria-label="%9$s"></td></tr>',
				(int) $i,
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
			'retention_months'    => array( __( 'Keep client details', 'ishot-booking' ), __( 'months after a session before the client’s name, contact details and notes are anonymised (0 keeps them)', 'ishot-booking' ) ),
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
				(int) $min,
				(int) $max,
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
		echo '</form>';
		self::studioForm( $errors, $settings );
		echo '</div>';
	}

	/**
	 * A second form on the same screen so booking policy and public contact details save (and fail) independently.
	 *
	 * @param array<string, string> $errors
	 * @param array<string, mixed>  $settings
	 */
	private static function studioForm( array $errors, array $settings ): void {
		$fields = array(
			'studio_phone'         => array( __( 'Phone', 'ishot-booking' ), 'tel', __( 'Shown to visitors. Start with + and the country code to make it a tap-to-call link.', 'ishot-booking' ) ),
			'studio_whatsapp'      => array( __( 'WhatsApp number', 'ishot-booking' ), 'tel', __( 'Include the country code, for example +231 …. Empty hides the WhatsApp link.', 'ishot-booking' ) ),
			'studio_instagram_url' => array( __( 'Instagram address', 'ishot-booking' ), 'url', __( 'The full https:// address of the Instagram page. Empty shows an inactive icon.', 'ishot-booking' ) ),
			'studio_facebook_url'  => array( __( 'Facebook address', 'ishot-booking' ), 'url', __( 'The full https:// address of the Facebook page. Empty shows an inactive icon.', 'ishot-booking' ) ),
			'studio_public_email'  => array( __( 'Public email', 'ishot-booking' ), 'email', __( 'The address shown to visitors. Separate from the notification address on the Emails screen.', 'ishot-booking' ) ),
		);
		$names  = array_flip( Studio::FIELDS );
		$flag   = Studio::unconfirmed();
		$notice = static function ( string $key ) use ( $names, $flag ): void {
			if ( in_array( $names[ $key ], $flag, true ) ) {
				printf(
					'<p class="ishot-field-warning" id="warn-%1$s"><strong>%2$s</strong> <label><input type="checkbox" name="confirm_%1$s" value="1"> %3$s</label></p>',
					esc_attr( $key ),
					esc_html__( 'Not confirmed by the studio yet', 'ishot-booking' ),
					esc_html__( 'This is correct', 'ishot-booking' )
				);
			}
		};
		// The input's accessible description: its error (if any), its help text and its "not confirmed" note (if any).
		$attrs = static function ( string $key ) use ( $errors, $names, $flag ): string {
			$ids = array();
			if ( isset( $errors[ $key ] ) ) {
				$ids[] = 'err-' . $key;
			}
			$ids[] = 'desc-' . $key;
			if ( in_array( $names[ $key ], $flag, true ) ) {
				$ids[] = 'warn-' . $key;
			}
			return ( isset( $errors[ $key ] ) ? ' aria-invalid="true"' : '' ) . ' aria-describedby="' . esc_attr( implode( ' ', $ids ) ) . '"';
		};

		echo '<h2>' . esc_html__( 'Studio details', 'ishot-booking' ) . '</h2><p class="ishot-muted">'
			. esc_html__( 'Shown in the site header, footer, Contact page and booking confirmation. Only enter details the studio has confirmed.', 'ishot-booking' ) . '</p>';
		echo View::formOpen( self::POST_STUDIO, self::POST_STUDIO ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formOpen() escapes.
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $fields as $field => [ $label, $type, $help ] ) {
			printf(
				'<tr><th scope="row"><label for="ishot-%1$s">%2$s</label></th><td><input id="ishot-%1$s" name="%1$s" type="%3$s" value="%4$s" class="regular-text"%5$s><p class="description" id="desc-%1$s">%6$s</p>',
				esc_attr( $field ),
				esc_html( $label ),
				esc_attr( $type ),
				esc_attr( (string) $settings[ $field ] ),
				$attrs( $field ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $attrs.
				esc_html( $help )
			);
			self::fieldError( $errors, $field );
			$notice( $field );
			echo '</td></tr>';
		}
		printf(
			'<tr><th scope="row"><label for="ishot-studio_address">%1$s</label></th><td><textarea id="ishot-studio_address" name="studio_address" rows="3" class="large-text"%2$s>%3$s</textarea><p class="description" id="desc-studio_address">%4$s</p>',
			esc_html__( 'Address', 'ishot-booking' ),
			$attrs( 'studio_address' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $attrs.
			esc_textarea( (string) $settings['studio_address'] ),
			esc_html__( 'Plain text; line breaks are kept.', 'ishot-booking' )
		);
		self::fieldError( $errors, 'studio_address' );
		$notice( 'studio_address' );
		echo '</td></tr></tbody></table>';
		submit_button( __( 'Save studio details', 'ishot-booking' ) );
		echo '</form>';
	}

	/** @param array<string, string> $errors */
	private static function finish( array $errors, string $slug ): never {
		if ( array() !== $errors ) {
			set_transient( self::errorsKey(), $errors, 5 * MINUTE_IN_SECONDS );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                      => $slug,
					ActionHandler::NOTICE_PARAM => array() === $errors ? 'saved' : 'settings_invalid',
					'ishot_type'                => array() === $errors ? 'success' : 'error',
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

	public static function handleStudio(): void {
		if ( ! current_user_can( 'manage_ishot_settings' ) ) {
			wp_die( esc_html__( 'Your role does not allow that action.', 'ishot-booking' ), 403 );
		}
		check_admin_referer( self::POST_STUDIO );
		self::finish( StudioForm::save( wp_unslash( $_POST ) ), Menu::SET_SLUG );
	}
}
