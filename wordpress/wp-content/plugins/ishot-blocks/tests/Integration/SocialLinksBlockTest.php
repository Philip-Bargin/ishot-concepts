<?php
namespace IShot\Blocks\Tests\Integration;

use IShot\Blocks\Icons;
use IShot\Blocks\SocialLinks;
use IShot\Blocks\Tests\Support\MarkupNormaliser;
use IShot\Booking\Settings;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/** The `ishot/social-links` block: the prototype's SocialLinks (src/components/SocialLinks.jsx at 50e60f4). */
final class SocialLinksBlockTest extends WP_UnitTestCase {

	private const INSTAGRAM = 'https://www.instagram.com/ishotconcept/';
	private const WHATSAPP  = 'https://wa.me/231881405259';

	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
	}

	/** The prototype's output with its own data (Instagram and WhatsApp set, Facebook not supplied), as React serialises it. */
	private static function prototype(): string {
		return '<a class="fo-icon-btn" href="' . self::INSTAGRAM . '" target="_blank" rel="noopener noreferrer" aria-label="Instagram (opens in a new tab)">' . Icons::svg( 'instagram' ) . '</a>'
			. '<span class="fo-icon-btn ish-social--pending" role="img" aria-label="Facebook: link not added yet" title="Facebook: link not added yet">' . Icons::svg( 'facebook' ) . '</span>'
			. '<a class="fo-icon-btn" href="' . self::WHATSAPP . '" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp (opens in a new tab)">' . Icons::svg( 'whatsapp' ) . '</a>';
	}

	private static function prototypeSocials(): array {
		return array(
			array( 'id' => 'instagram', 'label' => 'Instagram', 'url' => self::INSTAGRAM ),
			array( 'id' => 'facebook', 'label' => 'Facebook', 'url' => null ),
			array( 'id' => 'whatsapp', 'label' => 'WhatsApp', 'url' => self::WHATSAPP ),
		);
	}

	public function test_block_is_registered_from_the_plugin_build(): void {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'ishot/social-links' ) );
	}

	public function test_render_matches_the_prototype(): void {
		$html = SocialLinks::render( self::prototypeSocials() );
		$this->assertSame( MarkupNormaliser::normalise( self::prototype() ), MarkupNormaliser::normalise( $html ) );
		// Byte-exact too: same attribute order as React's output.
		$this->assertSame( self::prototype(), $html );
	}

	public function test_block_reads_the_studio_socials(): void {
		Settings::update(
			array(
				'studio_instagram_url' => self::INSTAGRAM,
				'studio_whatsapp'      => '+231 881 405 259',
			)
		);
		$this->assertSame( self::prototype(), trim( do_blocks( '<!-- wp:ishot/social-links /-->' ) ) );
	}

	public function test_empty_studio_details_give_three_inactive_icons_in_order(): void {
		$html = do_blocks( '<!-- wp:ishot/social-links /-->' );

		$this->assertSame( 3, substr_count( $html, 'ish-social--pending' ) );
		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringNotContainsString( 'tabindex', $html );
		preg_match_all( '/aria-label="(\w+): link not added yet"/', $html, $m );
		$this->assertSame( array( 'Instagram', 'Facebook', 'WhatsApp' ), $m[1] );
	}

	public function test_order_is_always_instagram_facebook_whatsapp(): void {
		$html = SocialLinks::render( array_reverse( self::prototypeSocials() ) );
		$this->assertSame( self::prototype(), $html );
	}

	public function test_unknown_platforms_are_ignored_and_values_escaped(): void {
		$html = SocialLinks::render(
			array(
				array( 'id' => 'tiktok', 'label' => 'TikTok', 'url' => 'https://tiktok.com/x' ),
				array( 'id' => 'instagram', 'label' => '"><script>', 'url' => 'javascript:alert(1)' ),
			)
		);
		$this->assertStringNotContainsString( 'TikTok', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_without_the_booking_plugin_the_block_prints_nothing(): void {
		$this->assertSame( '', SocialLinks::fromStudio( 'IShot\\Booking\\NoSuchStudio' ) );
		$this->assertNotSame( '', SocialLinks::fromStudio() );
	}

	public function test_markup_is_the_same_for_guests_and_logged_in_users(): void {
		$guest = do_blocks( '<!-- wp:ishot/social-links /-->' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( $guest, do_blocks( '<!-- wp:ishot/social-links /-->' ) );
	}
}
