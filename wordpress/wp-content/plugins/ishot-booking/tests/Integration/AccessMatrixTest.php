<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Admin\ActionHandler;
use IShot\Booking\Admin\Menu;
use IShot\Booking\Admin\SettingsScreen;
use IShot\Booking\Settings;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_REST_Request;

/**
 * Who may do what, end to end: every staff REST route and admin capability, for every role,
 * including signed-out visitors and administrators.
 */
final class AccessMatrixTest extends TestCase {

	use BookingFixtures;

	protected function setUp(): void {
		$this->resetBookings();
		$this->makeStaff();
		$this->users['administrator'] = wp_insert_user(
			array(
				'user_login' => uniqid( 'admin' ),
				'user_pass'  => wp_generate_password(),
				'user_email' => uniqid( 'admin' ) . '@example.com',
				'role'       => 'administrator',
			)
		);
	}

	protected function tearDown(): void {
		remove_all_filters( 'wp_die_handler' );
		$this->removeStaff();
	}

	private static function rest( string $method, string $route, array $body = array() ): int {
		$request = new WP_REST_Request( $method, '/ishot/v1/staff' . $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $body );
		} elseif ( array() !== $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_get_server()->dispatch( $request )->get_status();
	}

	/** @return array<string, array{0: ?string, 1: array<string, int>}> role => expected status per probe */
	public static function roles(): array {
		$staff_ok = array(
			'list'    => 200,
			'detail'  => 200,
			'confirm' => 200,
			'block'   => 201,
		);
		return array(
			'signed out'   => array( null, array( 'list' => 401, 'detail' => 401, 'confirm' => 401, 'cancel' => 401, 'block' => 401, 'unblock' => 401 ) ),
			'client'       => array( 'ishot_client', array( 'list' => 403, 'detail' => 403, 'confirm' => 403, 'cancel' => 403, 'block' => 403, 'unblock' => 403 ) ),
			'photographer' => array( 'ishot_photographer', $staff_ok + array( 'cancel' => 403, 'unblock' => 200 ) ),
			'manager'      => array( 'ishot_manager', $staff_ok + array( 'cancel' => 200, 'unblock' => 200 ) ),
			'admin'        => array( 'administrator', $staff_ok + array( 'cancel' => 200, 'unblock' => 200 ) ),
		);
	}

	/** @dataProvider roles */
	public function test_staff_rest_routes_answer_each_role_correctly( ?string $role, array $expected ): void {
		$a     = $this->bookingAt( '2026-12-14 10:00' );
		$b     = $this->bookingAt( '2026-12-14 14:00' );
		$block = $this->bookingAt( '2026-12-15 10:00', array( 'kind' => 'block', 'status' => \IShot\Booking\Domain\BookingStatus::Confirmed ) );
		$this->actAs( $role );

		$actual = array(
			'list'    => self::rest( 'GET', '/bookings', array( 'from' => '2026-12-14', 'to' => '2026-12-15' ) ),
			'detail'  => self::rest( 'GET', "/bookings/{$a}" ),
			'confirm' => self::rest( 'POST', "/bookings/{$a}/actions", array( 'action' => 'confirm', 'version' => 1 ) ),
			'cancel'  => self::rest( 'POST', "/bookings/{$b}/actions", array( 'action' => 'cancel', 'version' => 1 ) ),
			'block'   => self::rest( 'POST', '/blocks', array( 'start' => '2026-12-16T09:00', 'end' => '2026-12-16T10:00' ) ),
			'unblock' => self::rest( 'DELETE', "/blocks/{$block}" ),
		);

		foreach ( $expected as $probe => $status ) {
			$this->assertSame( $status, $actual[ $probe ], "{$probe} as " . ( $role ?? 'signed out' ) );
		}
		$changed = 200 === $expected['cancel'] ? 'cancelled' : 'requested';
		$this->assertSame( $changed, $this->repo->find( $b )['status'], 'refused cancel must change nothing' );
	}

	/** @dataProvider roles */
	public function test_admin_screens_require_the_right_capability( ?string $role ): void {
		$this->actAs( $role );
		$can = static fn( string $cap ) => current_user_can( $cap );

		$is_staff   = in_array( $role, array( 'ishot_photographer', 'ishot_manager', 'administrator' ), true );
		$is_manager = in_array( $role, array( 'ishot_manager', 'administrator' ), true );

		$this->assertSame( $is_staff, $can( 'view_ishot_calendar' ), 'calendar, list, detail' );
		$this->assertSame( $is_staff, $can( 'block_ishot_time' ), 'block time' );
		$this->assertSame( $is_manager, $can( 'manage_ishot_availability' ), 'opening hours' );
		$this->assertSame( $is_manager, $can( 'manage_ishot_settings' ), 'settings' );
		$this->assertSame( $is_manager, $can( 'manage_ishot_bookings' ), 'cancel, reschedule, deposits' );
	}

	public function test_admin_form_actions_refuse_clients_even_with_a_valid_nonce(): void {
		$id = $this->bookingAt( '2026-12-14 10:00' );
		$this->actAs( 'ishot_client' );

		$result = ActionHandler::process(
			array(
				'booking'        => (string) $id,
				'version'        => '1',
				'booking_action' => 'confirm',
				'_wpnonce'       => wp_create_nonce( ActionHandler::nonceAction( $id ) ),
			)
		);

		$this->assertSame( 'forbidden', $result['code'] );
		$this->assertSame( 'requested', $this->repo->find( $id )['status'] );
	}

	public function test_settings_handlers_stop_a_photographer_before_saving(): void {
		add_filter(
			'wp_die_handler',
			static fn() => static function ( $message ) {
				throw new RuntimeException( is_string( $message ) ? $message : 'wp_die' );
			}
		);
		$this->actAs( 'ishot_photographer' );
		$_POST = $_REQUEST = array(
			'_wpnonce'        => wp_create_nonce( SettingsScreen::POST_POLICY ),
			'lead_time_hours' => '0',
			'horizon_days'    => '1',
			'hold_hours'      => '1',
			'cancel_cutoff_hours' => '0',
		);

		try {
			SettingsScreen::handlePolicy();
			$this->fail( 'Expected the handler to stop.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'not allow', $e->getMessage() );
		} finally {
			$_POST = $_REQUEST = array();
		}
		$this->assertSame( 48, Settings::int( 'lead_time_hours' ) );

		$this->expectException( RuntimeException::class );
		SettingsScreen::handleHours();
	}

	public function test_only_managers_and_admins_can_edit_services_and_their_prices(): void {
		$service = wp_insert_post(
			array(
				'post_type'   => 'ishot_service',
				'post_status' => 'publish',
				'post_title'  => 'Portrait session',
			)
		);
		$this->users['author'] = wp_insert_user(
			array(
				'user_login' => uniqid( 'author' ),
				'user_pass'  => wp_generate_password(),
				'user_email' => uniqid( 'author' ) . '@example.com',
				'role'       => 'author',
			)
		);

		$expect    = array(
			'ishot_manager'      => true,
			'administrator'      => true,
			'ishot_photographer' => false,
			'ishot_client'       => false,
			'author'             => false,
		);
		$post_type = get_post_type_object( 'ishot_service' );
		foreach ( $expect as $role => $allowed ) {
			$this->actAs( $role );
			$this->assertSame( $allowed, current_user_can( 'edit_post', $service ), "{$role} edit service" );
			$this->assertSame( $allowed, current_user_can( $post_type->cap->create_posts ), "{$role} create service" );
			$this->assertSame( $allowed, current_user_can( 'edit_post_meta', $service, 'price_from' ), "{$role} edit price" );
		}
		wp_delete_post( $service, true );
	}

	public function test_menu_pages_are_not_registered_for_a_client(): void {
		global $submenu;
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		unset( $submenu[ Menu::SLUG ] );
		$this->actAs( 'ishot_client' );

		Menu::register();

		$this->assertEmpty( $submenu[ Menu::SLUG ] ?? array() );
	}
}
