<?php
namespace IShot\Blocks\Tests\Integration;

use IShot\Blocks\ContactDetails;
use IShot\Blocks\Tests\Support\MarkupNormaliser;
use IShot\Booking\Settings;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/** The `ishot/contact-details` block, footer variant: the Visit column lines of Footer.jsx at 50e60f4. */
final class ContactDetailsBlockTest extends WP_UnitTestCase {

	private const ADDRESS = 'Monrovia, Liberia';
	private const PHONE   = '0881405259';
	private const EMAIL   = 'ishotconcept01@gmail.com';

	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
	}

	/** The prototype's Visit lines with its own data (src/data.js `contact`), as React serialises them. */
	private static function prototype(): string {
		return '<span class="small">' . self::ADDRESS . '</span><a href="tel:' . self::PHONE . '">' . self::PHONE . '</a><a href="mailto:' . self::EMAIL . '">' . self::EMAIL . '</a>';
	}

	/** @return array{phone: string, phone_href: ?string, email: string, address: string} */
	private static function details( array $overrides = array() ): array {
		return array_merge(
			array(
				'phone'      => self::PHONE,
				'phone_href' => 'tel:' . self::PHONE,
				'email'      => self::EMAIL,
				'address'    => self::ADDRESS,
			),
			$overrides
		);
	}

	private static function fillStudio(): void {
		Settings::update(
			array(
				'studio_phone'        => self::PHONE,
				'studio_public_email' => self::EMAIL,
				'studio_address'      => self::ADDRESS,
			)
		);
	}

	public function test_block_is_registered_from_the_plugin_build_with_a_footer_variant(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'ishot/contact-details' );
		$this->assertNotNull( $type );
		$this->assertSame( 'footer', $type->attributes['variant']['default'] );
		$this->assertNotEmpty( $type->editor_script_handles, 'the Site Editor needs an editorScript' );
	}

	public function test_footer_variant_matches_the_prototype(): void {
		$html = ContactDetails::render( self::details(), 'footer' );
		$this->assertSame( MarkupNormaliser::normalise( self::prototype() ), MarkupNormaliser::normalise( $html ) );
		$this->assertSame( self::prototype(), $html );
	}

	public function test_block_reads_the_studio_details(): void {
		self::fillStudio();
		$this->assertSame( self::prototype(), trim( do_blocks( '<!-- wp:ishot/contact-details {"variant":"footer"} /-->' ) ) );
		$this->assertSame( self::prototype(), trim( do_blocks( '<!-- wp:ishot/contact-details /-->' ) ), 'footer is the default variant' );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public static function emptyFields(): array {
		return array(
			'no address' => array( 'address', '<a href="tel:' . self::PHONE . '">' . self::PHONE . '</a><a href="mailto:' . self::EMAIL . '">' . self::EMAIL . '</a>' ),
			'no phone'   => array( 'phone', '<span class="small">' . self::ADDRESS . '</span><a href="mailto:' . self::EMAIL . '">' . self::EMAIL . '</a>' ),
			'no email'   => array( 'email', '<span class="small">' . self::ADDRESS . '</span><a href="tel:' . self::PHONE . '">' . self::PHONE . '</a>' ),
		);
	}

	/** @dataProvider emptyFields */
	public function test_an_empty_field_omits_its_line( string $field, string $expected ): void {
		$overrides = array( $field => '' );
		if ( 'phone' === $field ) {
			$overrides['phone_href'] = null;
		}
		$this->assertSame( $expected, ContactDetails::render( self::details( $overrides ), 'footer' ) );
	}

	public function test_all_fields_empty_prints_nothing(): void {
		$this->assertSame( '', ContactDetails::render( array( 'phone' => '', 'phone_href' => null, 'email' => '', 'address' => '' ), 'footer' ) );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:ishot/contact-details /-->' ) ), 'empty Studio details' );
	}

	public function test_a_phone_without_a_dialable_href_prints_as_text(): void {
		$html = ContactDetails::render( self::details( array( 'phone' => 'Ask in the studio', 'phone_href' => null ) ), 'footer' );
		$this->assertStringContainsString( '<span>Ask in the studio</span>', $html );
		$this->assertStringNotContainsString( 'tel:', $html );
	}

	public function test_values_are_escaped(): void {
		$html = ContactDetails::render(
			array(
				'phone'      => '<b>1</b>',
				'phone_href' => 'javascript:alert(1)',
				'email'      => 'a"@b.co',
				'address'    => '<script>x</script>',
			),
			'footer'
		);
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringNotContainsString( 'a"@', $html );
	}

	public function test_an_unknown_variant_falls_back_to_footer(): void {
		$this->assertSame( self::prototype(), ContactDetails::render( self::details(), 'nope' ) );
	}

	public function test_without_the_booking_plugin_the_block_prints_nothing(): void {
		self::fillStudio();
		$this->assertSame( '', ContactDetails::fromStudio( 'footer', 'IShot\\Booking\\NoSuchStudio' ) );
		$this->assertSame( self::prototype(), ContactDetails::fromStudio( 'footer' ) );
	}

	public function test_markup_is_the_same_for_guests_and_logged_in_users(): void {
		self::fillStudio();
		$guest = do_blocks( '<!-- wp:ishot/contact-details /-->' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( $guest, do_blocks( '<!-- wp:ishot/contact-details /-->' ) );
	}
}
