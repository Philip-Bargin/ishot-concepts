<?php
namespace IShot\Blocks\Tests\Unit;

use IShot\Blocks\Icons;
use PHPUnit\Framework\TestCase;

final class IconsTest extends TestCase {

	private const PROTOTYPE_ICONS = array( 'arrow-right', 'briefcase', 'calendar-plus', 'camera', 'chevron-left', 'chevron-right', 'circle', 'circle-alert', 'circle-check', 'circle-x', 'clock', 'heart', 'info', 'link-2', 'mail', 'menu', 'pause', 'play', 'sparkles', 'sun', 'video', 'x' );

	public function test_every_prototype_icon_renders_an_svg(): void {
		foreach ( self::PROTOTYPE_ICONS as $name ) {
			$this->assertStringStartsWith( '<svg', Icons::svg( $name ), $name );
		}
	}

	public function test_an_unknown_icon_gives_an_empty_string_without_a_warning(): void {
		$this->assertSame( '', Icons::svg( 'no-such-icon' ) );
		$this->assertSame( '', Icons::svg( '' ) );
		$this->assertSame( '', Icons::svg( '../etc/passwd' ) );
	}

	public function test_an_unlabelled_icon_is_hidden_from_assistive_tech(): void {
		$svg = Icons::svg( 'info' );
		$this->assertStringContainsString( 'aria-hidden="true"', $svg );
		$this->assertStringContainsString( 'focusable="false"', $svg );
		$this->assertStringNotContainsString( 'role=', $svg );
	}

	public function test_a_labelled_icon_is_an_image_with_a_name(): void {
		$svg = Icons::svg( 'info', 'More "info"' );
		$this->assertStringContainsString( 'role="img"', $svg );
		$this->assertStringContainsString( 'aria-label="More &quot;info&quot;"', $svg );
		$this->assertStringNotContainsString( 'aria-hidden', $svg );
		$this->assertStringContainsString( 'focusable="false"', $svg );
	}

	public function test_prototype_settings_are_applied(): void {
		$svg = Icons::svg( 'arrow-right' );
		foreach ( array( 'width="16"', 'height="16"', 'stroke-width="1.5"', 'stroke-linecap="square"', 'stroke="currentColor"' ) as $needle ) {
			$this->assertStringContainsString( $needle, $svg );
		}
	}

	public function test_brand_glyphs_match_the_prototype_social_links(): void {
		// Source: src/components/SocialLinks.jsx (GLYPHS + BrandIcon), as React serialises it.
		$head = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">';
		$this->assertSame( $head . '<rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="0.6" fill="currentColor"></circle></svg>', Icons::svg( 'instagram' ) );
		$this->assertSame( $head . '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v7h4v-7h3l1-4h-4V8z"></path></svg>', Icons::svg( 'facebook' ) );
		$this->assertSame( $head . '<path d="M3.5 20.5l1.3-4.3A8.5 8.5 0 1 1 7.9 19.3z"></path><path d="M9 8.2c-.2 3.3 3.4 6.9 6.8 6.8l.9-1.7-2.1-1-1 .9a5 5 0 0 1-2.6-2.6l.9-1-1-2.1z"></path></svg>', Icons::svg( 'whatsapp' ) );
	}

	public function test_size_sets_width_and_height_like_lucide_react(): void {
		// Source: lucide-react Menu rendered with size 20 (header menu icon), same other settings.
		$expected = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" stroke-linejoin="round" class="lucide lucide-menu" aria-hidden="true" focusable="false"><line x1="4" x2="20" y1="12" y2="12"></line><line x1="4" x2="20" y1="6" y2="6"></line><line x1="4" x2="20" y1="18" y2="18"></line></svg>';
		$this->assertSame( $expected, Icons::svg( 'menu', '', 20 ) );
		$this->assertStringContainsString( 'width="12" height="12"', Icons::svg( 'instagram', '', 12 ) );
	}

	public function test_every_icon_can_be_labelled(): void {
		$all = array_merge( self::PROTOTYPE_ICONS, array( 'image', 'users', 'film', 'star', 'instagram', 'facebook', 'whatsapp' ) );
		foreach ( $all as $name ) {
			$svg = Icons::svg( $name, 'Name "q"', 24 );
			$this->assertSame( 1, substr_count( $svg, 'role="img"' ), $name );
			$this->assertSame( 1, substr_count( $svg, 'aria-label="Name &quot;q&quot;"' ), $name );
			$this->assertStringNotContainsString( 'aria-hidden', $svg, $name );
			$this->assertSame( 1, substr_count( $svg, 'focusable="false"' ), $name );
			$this->assertStringContainsString( 'width="24" height="24"', $svg, $name );
			$this->assertStringNotContainsString( 'stroke-width="24"', $svg, $name );
		}
	}
}
