<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Admin\SettingsScreen;
use IShot\Booking\Admin\StudioForm;
use IShot\Booking\Roles;
use IShot\Booking\Settings;
use IShot\Booking\Studio;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Studio details: validation, link building, the unconfirmed flag and the save handler's guards. */
final class StudioDetailsTest extends TestCase {

	protected function setUp(): void {
		delete_option( Settings::OPTION );
		remove_all_filters( 'wp_die_handler' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'wp_die_handler' );
		remove_all_filters( Studio::ENVIRONMENT_FILTER );
		$_POST = $_REQUEST = array();
		wp_set_current_user( 0 );
	}

	/**
	 * @param array<string, string> $over
	 * @return array<string, string>
	 */
	private static function input( array $over = array() ): array {
		return $over + array(
			'studio_phone'         => '',
			'studio_whatsapp'      => '',
			'studio_instagram_url' => '',
			'studio_facebook_url'  => '',
			'studio_address'       => '',
			'studio_public_email'  => '',
		);
	}

	public function test_defaults_are_empty_and_every_social_is_inactive(): void {
		$d = Studio::details();
		$this->assertSame( '', $d['phone'] );
		$this->assertNull( $d['phone_href'] );
		$this->assertNull( $d['whatsapp_url'] );
		$this->assertNull( $d['instagram_url'] );
		$this->assertNull( $d['facebook_url'] );
		$this->assertSame( '', $d['email'] );
		$this->assertSame( '', $d['address'] );
		foreach ( Studio::socials() as $s ) {
			$this->assertNull( $s['url'] );
		}
		$this->assertSame( array(), Studio::unconfirmed() );
	}

	public function test_empty_facebook_gives_a_null_url(): void {
		StudioForm::save( self::input( array( 'studio_instagram_url' => 'https://www.instagram.com/ishotconcept/' ) ) );
		$this->assertNull( Studio::details()['facebook_url'] );
		$this->assertNull( Studio::socials()[1]['url'] );
		$this->assertSame( 'https://www.instagram.com/ishotconcept/', Studio::socials()[0]['url'] );
	}

	public function test_whatsapp_and_phone_are_normalised_to_digits_for_links(): void {
		$errors = StudioForm::save(
			self::input(
				array(
					'studio_whatsapp' => '+231 88 140 5259',
					'studio_phone'    => '+231 (88) 140-5259',
				)
			)
		);
		$this->assertSame( array(), $errors );
		$d = Studio::details();
		$this->assertSame( 'https://wa.me/231881405259', $d['whatsapp_url'] );
		$this->assertSame( 'tel:+231881405259', $d['phone_href'] );
		$this->assertSame( '+231 (88) 140-5259', $d['phone'] );
	}

	public function test_a_local_number_displays_but_has_no_guessed_tel_link(): void {
		$this->assertSame( array(), StudioForm::save( self::input( array( 'studio_phone' => '0881405259' ) ) ) );
		$this->assertSame( '0881405259', Studio::details()['phone'] );
		$this->assertNull( Studio::details()['phone_href'] );
	}

	public function test_phone_rejects_letters_and_overlong_values(): void {
		foreach ( array( 'call me', '+231 88 140 5259 ext 2', str_repeat( '1', 31 ), '12' ) as $bad ) {
			$errors = StudioForm::save( self::input( array( 'studio_phone' => $bad ) ) );
			$this->assertArrayHasKey( 'studio_phone', $errors, $bad );
		}
		$this->assertFalse( get_option( Settings::OPTION ) );
	}

	public function test_whatsapp_needs_a_country_code(): void {
		$errors = StudioForm::save( self::input( array( 'studio_whatsapp' => '0881405259' ) ) );
		$this->assertArrayHasKey( 'studio_whatsapp', $errors );
		$this->assertSame( array(), StudioForm::save( self::input( array( 'studio_whatsapp' => '00231881405259' ) ) ) );
		$this->assertSame( 'https://wa.me/231881405259', Studio::details()['whatsapp_url'] );
	}

	/** @return array<string, array{string}> */
	public static function badSocialUrls(): array {
		return array(
			'http'                 => array( 'http://instagram.com/x' ),
			'path look-alike'      => array( 'https://evil.example/instagram.com' ),
			'subdomain look-alike' => array( 'https://instagram.com.evil.example/x' ),
			'userinfo'             => array( 'https://instagram.com@evil.example/x' ),
			'prefix look-alike'    => array( 'https://notinstagram.com/x' ),
			'suffix look-alike'    => array( 'https://evilinstagram.com/x' ),
			'port'                 => array( 'https://instagram.com:8443/x' ),
			'backslash'            => array( 'https://evil.example\\@instagram.com/x' ),
			'javascript'           => array( 'javascript:alert(1)' ),
			'no scheme'            => array( 'instagram.com/x' ),
			'whitespace'           => array( 'https://instagram.com/x y' ),
		);
	}

	/** @dataProvider badSocialUrls */
	public function test_instagram_rejects_unsafe_urls( string $url ): void {
		$errors = StudioForm::save( self::input( array( 'studio_instagram_url' => $url ) ) );
		$this->assertArrayHasKey( 'studio_instagram_url', $errors );
		$this->assertFalse( get_option( Settings::OPTION ) );
	}

	public function test_facebook_is_checked_against_facebook_com_only(): void {
		$this->assertArrayHasKey( 'studio_facebook_url', StudioForm::save( self::input( array( 'studio_facebook_url' => 'https://www.instagram.com/x' ) ) ) );
		$this->assertArrayHasKey( 'studio_instagram_url', StudioForm::save( self::input( array( 'studio_instagram_url' => 'https://facebook.com/x' ) ) ) );
		foreach ( array( 'https://facebook.com/ishot', 'https://www.facebook.com/ishot', 'https://m.facebook.com/ishot' ) as $ok ) {
			$this->assertSame( array(), StudioForm::save( self::input( array( 'studio_facebook_url' => $ok ) ) ), $ok );
			$this->assertSame( $ok, Studio::details()['facebook_url'] );
		}
		$this->assertSame( array(), StudioForm::save( self::input( array( 'studio_instagram_url' => 'https://m.instagram.com/ishot' ) ) ) );
	}

	public function test_email_must_be_valid(): void {
		$this->assertArrayHasKey( 'studio_public_email', StudioForm::save( self::input( array( 'studio_public_email' => 'not an email' ) ) ) );
		$this->assertSame( array(), StudioForm::save( self::input( array( 'studio_public_email' => 'hello@example.com' ) ) ) );
		$this->assertSame( 'hello@example.com', Studio::details()['email'] );
	}

	public function test_address_is_plain_text_keeps_newlines_and_is_capped(): void {
		$this->assertSame( array(), StudioForm::save( self::input( array( 'studio_address' => "Studio <b>1</b>\nMonrovia" ) ) ) );
		$this->assertSame( "Studio 1\nMonrovia", Studio::details()['address'] );
		$errors = StudioForm::save( self::input( array( 'studio_address' => str_repeat( 'a', 301 ) ) ) );
		$this->assertArrayHasKey( 'studio_address', $errors );
		$this->assertSame( "Studio 1\nMonrovia", Studio::details()['address'] );
	}

	public function test_invalid_input_keeps_every_previous_value(): void {
		StudioForm::save(
			self::input(
				array(
					'studio_public_email' => 'a@example.com',
					'studio_address'      => 'Old street',
				)
			)
		);
		$before = get_option( Settings::OPTION );
		$errors = StudioForm::save(
			self::input(
				array(
					'studio_public_email'  => 'b@example.com',
					'studio_address'       => 'New street',
					'studio_instagram_url' => 'http://instagram.com/x',
				)
			)
		);
		$this->assertSame( array( 'studio_instagram_url' ), array_keys( $errors ) );
		$this->assertSame( $before, get_option( Settings::OPTION ) );
	}

	public function test_saving_does_not_touch_booking_policy(): void {
		Settings::update( array( 'lead_time_hours' => 12 ) );
		StudioForm::save( self::input( array( 'studio_address' => 'Somewhere' ) ) );
		$this->assertSame( 12, Settings::int( 'lead_time_hours' ) );
	}

	public function test_socials_always_has_three_items_in_order(): void {
		$this->assertSame( array( 'instagram', 'facebook', 'whatsapp' ), array_column( Studio::socials(), 'id' ) );
		$this->assertSame( array( 'Instagram', 'Facebook', 'WhatsApp' ), array_column( Studio::socials(), 'label' ) );
		StudioForm::save( self::input( array( 'studio_whatsapp' => '+231 88 140 5259' ) ) );
		$this->assertCount( 3, Studio::socials() );
		$this->assertSame( 'https://wa.me/231881405259', Studio::socials()[2]['url'] );
	}

	public function test_unconfirmed_fields_are_cleared_only_by_changing_or_confirming_them(): void {
		Settings::update(
			array(
				'studio_phone'       => '0881405259',
				'studio_whatsapp'    => '+231 881 405 259',
				'studio_address'     => 'Monrovia, Liberia',
				'studio_unconfirmed' => array( 'phone', 'whatsapp', 'address' ),
			)
		);
		$this->assertSame( array( 'phone', 'whatsapp', 'address' ), Studio::unconfirmed() );

		$same = array(
			'studio_phone'    => '0881405259',
			'studio_whatsapp' => '+231 881 405 259',
			'studio_address'  => 'Monrovia, Liberia',
		);
		// Re-saving unchanged values does not confirm them.
		StudioForm::save( self::input( $same ) );
		$this->assertSame( array( 'phone', 'whatsapp', 'address' ), Studio::unconfirmed() );

		// Ticking confirm, or editing the value, does.
		StudioForm::save(
			self::input(
				array(
					'confirm_studio_phone' => '1',
					'studio_address'       => 'Monrovia, Liberia (Sinkor)',
				) + $same
			)
		);
		$this->assertSame( array( 'whatsapp' ), Studio::unconfirmed() );
	}

	public function test_a_rejected_save_does_not_change_the_unconfirmed_list(): void {
		Settings::update(
			array(
				'studio_address'     => 'Monrovia',
				'studio_unconfirmed' => array( 'address' ),
			)
		);
		StudioForm::save(
			self::input(
				array(
					'studio_address'         => 'Edited',
					'confirm_studio_address' => '1',
					'studio_public_email'    => 'bad',
				)
			)
		);
		$this->assertSame( array( 'address' ), Studio::unconfirmed() );
	}

	public function test_an_empty_field_is_never_reported_unconfirmed(): void {
		Settings::update( array( 'studio_unconfirmed' => array( 'facebook', 'phone' ) ) );
		$this->assertSame( array(), Studio::unconfirmed() );
	}

	private function dieThrows(): void {
		add_filter(
			'wp_die_handler',
			static fn() => static function ( $message, $title = '', $args = array() ) {
				$code = is_array( $args ) ? (string) ( $args['response'] ?? '' ) : (string) $args;
				throw new RuntimeException( ( is_string( $message ) ? $message : 'wp_die' ) . '|' . $code );
			}
		);
	}

	private function actAs( string $role ): void {
		$id = wp_insert_user(
			array(
				'user_login' => uniqid( $role ),
				'user_pass'  => wp_generate_password(),
				'user_email' => uniqid( $role ) . '@example.com',
				'role'       => $role,
			)
		);
		wp_set_current_user( $id );
	}

	public function test_a_user_without_manage_ishot_settings_cannot_save(): void {
		Roles::install();
		$this->dieThrows();
		$this->actAs( 'ishot_photographer' );
		$_POST = $_REQUEST = self::input(
			array(
				'studio_address' => 'Hacked',
				'_wpnonce'       => wp_create_nonce( SettingsScreen::POST_STUDIO ),
			)
		);
		try {
			SettingsScreen::handleStudio();
			$this->fail( 'Expected a 403.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( '403', $e->getMessage() );
		}
		$this->assertSame( '', Studio::details()['address'] );
	}

	public function test_a_bad_nonce_is_rejected(): void {
		Roles::install();
		$this->dieThrows();
		$this->actAs( 'ishot_manager' );
		$_POST = $_REQUEST = self::input(
			array(
				'studio_address' => 'Hacked',
				'_wpnonce'       => 'nope',
			)
		);
		try {
			SettingsScreen::handleStudio();
			$this->fail( 'Expected the nonce check to stop the request.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( '403', $e->getMessage() );
		}
		$this->assertSame( '', Studio::details()['address'] );
	}

	public function test_a_flagged_value_not_in_normalised_form_stays_flagged_on_an_unchanged_resave(): void {
		$raw = array(
			'studio_public_email'  => '  Hello@Example.com ',
			'studio_address'       => "Line one\r\nLine two ",
			'studio_phone'         => ' 0881405259 ',
			'studio_instagram_url' => 'https://www.instagram.com/ishotconcept/',
		);
		Settings::update( $raw + array( 'studio_unconfirmed' => array( 'email', 'address', 'phone', 'instagram' ) ) );
		$this->assertSame( array(), StudioForm::save( self::input( $raw ) ) );
		$this->assertSame( array( 'phone', 'instagram', 'email', 'address' ), Studio::unconfirmed() );
	}

	private function flaggedStudio(): void {
		Settings::update(
			array(
				'studio_phone'         => '+231 881 405 259',
				'studio_whatsapp'      => '+231 881 405 259',
				'studio_instagram_url' => 'https://www.instagram.com/ishotconcept/',
				'studio_facebook_url'  => 'https://www.facebook.com/ishot',
				'studio_public_email'  => 'owner@example.com',
				'studio_address'       => 'Monrovia, Liberia',
				'studio_unconfirmed'   => array( 'whatsapp', 'instagram', 'address', 'phone' ),
			)
		);
	}

	/** @return array<string, array{string}> */
	public static function nonLocalEnvironments(): array {
		return array(
			'production' => array( 'production' ),
			'staging'    => array( 'staging' ),
		);
	}

	/** @dataProvider nonLocalEnvironments */
	public function test_unconfirmed_values_are_withheld_outside_local_environments( string $env ): void {
		$this->flaggedStudio();
		add_filter( Studio::ENVIRONMENT_FILTER, static fn() => $env );
		$d      = Studio::details();
		$social = array_column( Studio::socials(), 'url', 'id' );
		$this->assertSame( '', $d['phone'] );
		$this->assertNull( $d['phone_href'] );
		$this->assertNull( $d['whatsapp_url'] );
		$this->assertNull( $d['instagram_url'] );
		$this->assertSame( '', $d['address'] );
		$this->assertNull( $social['instagram'] );
		$this->assertNull( $social['whatsapp'] );
		// Confirmed fields stay.
		$this->assertSame( 'https://www.facebook.com/ishot', $d['facebook_url'] );
		$this->assertSame( 'owner@example.com', $d['email'] );
		$this->assertSame( 'https://www.facebook.com/ishot', $social['facebook'] );
	}

	/** @return array<string, array{string}> */
	public static function localEnvironments(): array {
		return array(
			'local'       => array( 'local' ),
			'development' => array( 'development' ),
		);
	}

	/** @dataProvider localEnvironments */
	public function test_unconfirmed_values_show_on_local_and_development_sites( string $env ): void {
		$this->flaggedStudio();
		add_filter( Studio::ENVIRONMENT_FILTER, static fn() => $env );
		$d = Studio::details();
		$this->assertSame( 'Monrovia, Liberia', $d['address'] );
		$this->assertSame( 'https://wa.me/231881405259', $d['whatsapp_url'] );
		$this->assertSame( 'tel:+231881405259', $d['phone_href'] );
		$this->assertSame( 'https://www.instagram.com/ishotconcept/', $d['instagram_url'] );
	}

	public function test_the_unconfirmed_note_is_part_of_the_inputs_accessible_description(): void {
		Roles::install();
		Settings::update(
			array(
				'studio_whatsapp'    => '+231 881 405 259',
				'studio_address'     => 'Monrovia',
				'studio_phone'       => '+231 881 405 259',
				'studio_unconfirmed' => array( 'whatsapp', 'address' ),
			)
		);
		$this->actAs( 'ishot_manager' );
		ob_start();
		SettingsScreen::policyPage();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'id="warn-studio_whatsapp"', $html );
		$this->assertStringContainsString( 'name="studio_whatsapp" type="tel" value="+231 881 405 259" class="regular-text" aria-describedby="desc-studio_whatsapp warn-studio_whatsapp"', $html );
		$this->assertStringContainsString( 'aria-describedby="desc-studio_address warn-studio_address"', $html );
		$this->assertStringContainsString( 'name="studio_phone" type="tel" value="+231 881 405 259" class="regular-text" aria-describedby="desc-studio_phone"', $html );
	}

	public function test_the_screen_escapes_what_it_prints_and_flags_unconfirmed_values(): void {
		Roles::install();
		Settings::update(
			array(
				'studio_address'     => '"><script>alert(1)</script>',
				'studio_whatsapp'    => '+231 881 405 259',
				'studio_unconfirmed' => array( 'whatsapp', 'address' ),
			)
		);
		$this->actAs( 'ishot_manager' );
		ob_start();
		SettingsScreen::policyPage();
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( '<script>alert(1)', $html );
		$this->assertStringContainsString( 'Studio details', $html );
		$this->assertSame( 2, substr_count( $html, 'Not confirmed by the studio yet' ) );
		$this->assertStringContainsString( 'name="action" value="' . SettingsScreen::POST_STUDIO . '"', $html );
	}
}
