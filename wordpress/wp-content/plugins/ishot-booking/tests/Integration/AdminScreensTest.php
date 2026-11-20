<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use IShot\Booking\Admin\BookingScreen;
use IShot\Booking\Admin\CalendarScreen;
use IShot\Booking\Admin\ListScreen;
use IShot\Booking\Admin\Menu;
use PHPUnit\Framework\TestCase;

/** Menu access per role, and that screens render escaped data with the right controls. */
final class AdminScreensTest extends TestCase {

	use BookingFixtures;

	protected function setUp(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		$this->resetBookings();
		$this->makeStaff();
	}

	protected function tearDown(): void {
		$this->removeStaff();
		unset( $GLOBALS['submenu'][ Menu::SLUG ] );
	}

	private static function render( callable $screen ): string {
		ob_start();
		$screen();
		return (string) ob_get_clean();
	}

	public function test_menu_pages_require_the_matching_capability(): void {
		global $submenu;
		$this->actAs( 'ishot_manager' );
		Menu::register();

		$caps = array_column( $submenu[ Menu::SLUG ], 1, 2 );
		$this->assertSame( 'view_ishot_calendar', $caps[ Menu::SLUG ] );
		$this->assertSame( 'view_ishot_calendar', $caps['ishot-bookings-list'] );
		$this->assertSame( 'manage_ishot_availability', $caps['ishot-hours'] );
		$this->assertSame( 'manage_ishot_settings', $caps['ishot-settings'] );
	}

	public function test_detail_escapes_client_input_and_shows_role_appropriate_buttons(): void {
		$id = $this->bookingAt( '2030-01-14 10:00', array( 'name' => '<script>alert(1)</script>Ada' ) );

		$this->actAs( 'ishot_manager' );
		$html = self::render( static fn() => BookingScreen::render( $id, new DateTimeImmutable( '2029-12-01' ) ) );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;Ada', $html );
		$this->assertStringContainsString( 'value="request_deposit"', $html );
		$this->assertStringContainsString( 'name="version" value="1"', $html );
		$this->assertStringContainsString( 'Created', $html ); // timeline

		$this->actAs( 'ishot_photographer' );
		$html = self::render( static fn() => BookingScreen::render( $id, new DateTimeImmutable( '2029-12-01' ) ) );
		$this->assertStringContainsString( 'value="confirm"', $html );
		$this->assertStringNotContainsString( 'value="cancel"', $html );
	}

	public function test_detail_of_an_unknown_booking_says_so(): void {
		$this->actAs( 'ishot_manager' );
		$this->assertStringContainsString( 'not found', self::render( static fn() => BookingScreen::render( 404, new DateTimeImmutable() ) ) );
	}

	public function test_list_and_calendar_render_bookings_with_links_to_the_detail(): void {
		$id = $this->bookingAt( '2026-12-14 10:00', array( 'name' => 'Bea Kollie' ) );
		$this->actAs( 'ishot_photographer' );
		set_current_screen( 'toplevel_page_' . Menu::SLUG );

		$list = self::render( static fn() => ListScreen::render( array( 'when' => 'all' ) ) );
		$this->assertStringContainsString( 'Bea Kollie', $list );
		$this->assertStringContainsString( 'booking=' . $id, $list );

		$week = self::render( static fn() => CalendarScreen::render( '2026-12-16' ) );
		$this->assertStringContainsString( 'Bea Kollie', $week );
		$this->assertStringContainsString( 'booking=' . $id, $week );
		$this->assertStringContainsString( 'week=2026-12-21', $week );
	}

	public function test_opening_hours_rows_are_labelled_with_the_right_weekday(): void {
		$this->actAs( 'ishot_manager' );

		$html = self::render( static fn() => \IShot\Booking\Admin\SettingsScreen::hoursPage() );

		$this->assertMatchesRegularExpression( '#<th scope="row">Monday</th><td><input type="checkbox" name="weekly\[1\]\[closed\]#', $html );
		$this->assertMatchesRegularExpression( '#<th scope="row">Sunday</th><td><input type="checkbox" name="weekly\[7\]\[closed\]" value="1" aria-label="Sunday closed" checked#', $html );
	}

	public function test_admin_styles_use_the_design_system_tokens_scoped_to_the_plugin(): void {
		$tokens = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/tokens.css' );

		$this->assertStringContainsString( '.ishot-admin {', $tokens );
		$this->assertStringContainsString( '--accent:', $tokens );
		$this->assertStringNotContainsString( ':root', $tokens );
	}
}
