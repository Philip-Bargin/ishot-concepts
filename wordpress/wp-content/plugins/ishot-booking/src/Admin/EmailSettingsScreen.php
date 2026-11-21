<?php
namespace IShot\Booking\Admin;

use IShot\Booking\Calendar\CalendarFeed;
use IShot\Booking\Notifications\Rules;
use IShot\Booking\Notifications\Templates;
use IShot\Booking\Settings;

/** Emails and calendar: sender details, deposit instructions, template wording, staff feed. Validation lives in EmailSettingsForm. */
final class EmailSettingsScreen {

	public const POST_SAVE = 'ishot_save_emails';
	public const POST_FEED = 'ishot_feed_key';

	private static function key( string $what ): string {
		return 'ishot_emails_' . $what . '_' . get_current_user_id();
	}

	/** Reads and deletes a one-time value for the current user. */
	private static function take( string $what ): mixed {
		$value = get_transient( self::key( $what ) );
		delete_transient( self::key( $what ) );
		return $value;
	}

	private static function error( array $errors, string $field ): string {
		return isset( $errors[ $field ] ) ? sprintf( '<p class="ishot-field-error" id="err-%1$s">%2$s</p>', esc_attr( $field ), esc_html( $errors[ $field ] ) ) : '';
	}

	private static function invalid( array $errors, string $field ): string {
		return isset( $errors[ $field ] ) ? ' aria-invalid="true" aria-describedby="err-' . esc_attr( $field ) . '"' : '';
	}

	public static function page(): void {
		$errors = self::take( 'errors' );
		$errors = is_array( $errors ) ? $errors : array();
		$s      = Settings::all();

		echo '<div class="wrap ishot-admin"><h1>' . esc_html__( 'Emails and calendar', 'ishot-booking' ) . '</h1>';
		ActionHandler::notice();
		echo View::formOpen( self::POST_SAVE, self::POST_SAVE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formOpen() escapes.

		echo '<h2>' . esc_html__( 'Sender', 'ishot-booking' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		$fields = array(
			'email_from_name'      => array( __( 'Studio name', 'ishot-booking' ), 'text', __( 'Shown as the sender and in every email. Empty uses the site title.', 'ishot-booking' ) ),
			'email_from_address'   => array( __( 'Send from', 'ishot-booking' ), 'email', __( 'An address on your own domain, so SPF and DKIM pass. Empty uses WordPress\'s default.', 'ishot-booking' ) ),
			'email_reply_to'       => array( __( 'Replies go to', 'ishot-booking' ), 'email', __( 'Where client replies land. Empty replies to the sender.', 'ishot-booking' ) ),
			'email_studio_address' => array( __( 'Studio notifications', 'ishot-booking' ), 'email', __( 'Receives new requests, client changes and lapsed holds. Empty uses the site admin email.', 'ishot-booking' ) ),
			'currency'             => array( __( 'Currency', 'ishot-booking' ), 'text', __( 'Three-letter code shown with deposit amounts, such as USD or LRD.', 'ishot-booking' ) ),
			'manage_url'           => array( __( 'Manage-booking page', 'ishot-booking' ), 'url', __( 'The page clients open from their emails. Empty uses /manage-booking/ on this site.', 'ishot-booking' ) ),
		);
		foreach ( $fields as $field => [ $label, $type, $help ] ) {
			printf(
				'<tr><th scope="row"><label for="ishot-%1$s">%2$s</label></th><td><input id="ishot-%1$s" name="%1$s" type="%3$s" value="%4$s" class="regular-text"%5$s><p class="description">%6$s</p>%7$s</td></tr>',
				esc_attr( $field ),
				esc_html( $label ),
				esc_attr( $type ),
				esc_attr( (string) $s[ $field ] ),
				self::invalid( $errors, $field ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in invalid().
				esc_html( $help ),
				self::error( $errors, $field ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in error().
			);
		}
		printf(
			'<tr><th scope="row"><label for="ishot-deposit_instructions">%1$s</label></th><td><textarea id="ishot-deposit_instructions" name="deposit_instructions" rows="4" class="large-text"%2$s>%3$s</textarea><p class="description">%4$s</p>%5$s</td></tr>',
			esc_html__( 'Deposit instructions', 'ishot-booking' ),
			self::invalid( $errors, 'deposit_instructions' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_textarea( (string) $s['deposit_instructions'] ),
			esc_html__( 'How to pay (for example your mobile money number). Sent with every deposit request and reminder. Deposits are only marked paid when staff record them.', 'ishot-booking' ),
			self::error( $errors, 'deposit_instructions' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Email wording', 'ishot-booking' ) . '</h2><p class="ishot-muted">'
			. esc_html( sprintf( /* translators: %s: placeholder list */ __( 'Leave a field empty to use the standard wording. You can use: %s. Booking details and the client\'s link are always added.', 'ishot-booking' ), implode( ' ', Templates::PLACEHOLDERS ) ) )
			. '</p>';
		$overrides = (array) $s['templates'];
		foreach ( Templates::defaults() as $key => $default ) {
			$current = (array) ( $overrides[ $key ] ?? array() );
			$enabled = (bool) ( $current['enabled'] ?? true );
			$open    = isset( $errors[ "template_{$key}_subject" ] ) || isset( $errors[ "template_{$key}_intro" ] );
			printf(
				'<details class="ishot-panel"%1$s><summary><strong>%2$s</strong> <span class="ishot-muted">%3$s</span></summary>'
				. '<p><label><input type="checkbox" name="templates[%4$s][enabled]" value="1"%5$s> %6$s</label></p>'
				. '<p><label for="tpl-%4$s-subject">%7$s</label><br><input id="tpl-%4$s-subject" name="templates[%4$s][subject]" type="text" class="large-text" maxlength="%8$d" value="%9$s" placeholder="%10$s"%11$s></p>%12$s'
				. '<p><label for="tpl-%4$s-intro">%13$s</label><br><textarea id="tpl-%4$s-intro" name="templates[%4$s][intro]" rows="3" class="large-text" placeholder="%14$s"%15$s>%16$s</textarea></p>%17$s</details>',
				$open ? ' open' : '',
				esc_html( $default['label'] ),
				esc_html( Rules::STUDIO === Rules::TEMPLATES[ $key ] ? __( 'to the studio', 'ishot-booking' ) : __( 'to the client', 'ishot-booking' ) ),
				esc_attr( $key ),
				checked( $enabled, true, false ),
				esc_html__( 'Send this email', 'ishot-booking' ),
				esc_html__( 'Subject', 'ishot-booking' ),
				(int) EmailSettingsForm::SUBJECT_MAX,
				esc_attr( (string) ( $current['subject'] ?? '' ) ),
				esc_attr( $default['subject'] ),
				self::invalid( $errors, "template_{$key}_subject" ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				self::error( $errors, "template_{$key}_subject" ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html__( 'Opening paragraph', 'ishot-booking' ),
				esc_attr( $default['intro'] ),
				self::invalid( $errors, "template_{$key}_intro" ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_textarea( (string) ( $current['intro'] ?? '' ) ),
				self::error( $errors, "template_{$key}_intro" ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}
		submit_button( __( 'Save email settings', 'ishot-booking' ) );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Staff calendar feed', 'ishot-booking' ) . '</h2><p>'
			. esc_html__( 'Subscribe to this address in Google Calendar, Apple Calendar or Outlook to see bookings and blocked time. Anyone with the address can read client names and times, so share it only with staff. Creating a new address stops the old one working.', 'ishot-booking' )
			. '</p>';
		$new_key = self::take( 'feed' );
		if ( is_string( $new_key ) && '' !== $new_key ) {
			printf(
				'<p><label for="ishot-feed-url"><strong>%1$s</strong></label><br><input id="ishot-feed-url" type="text" class="large-text code" readonly value="%2$s"></p><p class="ishot-muted">%3$s</p>',
				esc_html__( 'Your calendar address (copy it now: it will not be shown again)', 'ishot-booking' ),
				esc_attr( CalendarFeed::url( $new_key ) ),
				esc_html__( 'Only a fingerprint of this address is stored.', 'ishot-booking' )
			);
		} elseif ( CalendarFeed::hasKey() ) {
			echo '<p class="ishot-muted">' . esc_html__( 'A calendar address exists. It cannot be shown again; create a new one if you have lost it.', 'ishot-booking' ) . '</p>';
		}
		echo View::formOpen( self::POST_FEED, self::POST_FEED ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formOpen() escapes.
		submit_button( CalendarFeed::hasKey() ? __( 'Create a new calendar address', 'ishot-booking' ) : __( 'Create a calendar address', 'ishot-booking' ), 'secondary' );
		echo '</form></div>';
	}

	private static function redirect( string $notice, string $type ): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                      => Menu::EMAIL_SLUG,
					ActionHandler::NOTICE_PARAM => $notice,
					'ishot_type'                => $type,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function guard( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_ishot_settings' ) ) {
			wp_die( esc_html__( 'Your role does not allow that action.', 'ishot-booking' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	public static function handleSave(): void {
		self::guard( self::POST_SAVE );
		$errors = EmailSettingsForm::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() above runs check_admin_referer().
		if ( array() !== $errors ) {
			set_transient( self::key( 'errors' ), $errors, 5 * MINUTE_IN_SECONDS );
		}
		self::redirect( array() === $errors ? 'saved' : 'settings_invalid', array() === $errors ? 'success' : 'error' );
	}

	public static function handleFeedKey(): void {
		self::guard( self::POST_FEED );
		// Held for one page view so the address can be copied; never stored in the settings.
		set_transient( self::key( 'feed' ), CalendarFeed::regenerateKey(), 5 * MINUTE_IN_SECONDS );
		self::redirect( 'feed_key', 'success' );
	}
}
