<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Accounts\ClientAccounts;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Privacy\Anonymiser;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/** GET /ishot/v1/me/bookings: a signed-in client sees their own bookings and nobody else's. */
final class RestMyBookingsTest extends WP_UnitTestCase {

	use BookingFixtures;

	private int $other;

	public function set_up(): void {
		parent::set_up();
		$this->resetBookings();
		$this->makeStaff();
		$this->other = self::factory()->user->create( array( 'role' => 'ishot_client', 'user_email' => uniqid( 'other' ) . '@example.com' ) );
		do_action( 'rest_api_init' );
	}

	public function tear_down(): void {
		$this->removeStaff();
		parent::tear_down();
	}

	private function me(): \WP_User {
		return get_userdata( $this->users['ishot_client'] );
	}

	private function get( array $params = array() ): WP_REST_Response {
		$request = new WP_REST_Request( 'GET', '/ishot/v1/me/bookings' );
		$request->set_query_params( $params );
		return rest_do_request( $request );
	}

	/** @return int[] */
	private static function ids( WP_REST_Response $response ): array {
		return array_column( $response->get_data(), 'id' );
	}

	public function test_signed_out_visitors_get_401_and_staff_without_the_capability_get_403(): void {
		$this->assertSame( 401, $this->get()->get_status() );
		$this->actAs( 'ishot_photographer' );
		$this->assertSame( 403, $this->get()->get_status() );
	}

	public function test_a_client_sees_only_bookings_linked_to_their_account(): void {
		$mine   = $this->bookingAt( '2026-12-14 10:00:00', array( 'user' => $this->me()->ID, 'email' => $this->me()->user_email ) );
		$theirs = $this->bookingAt( '2026-12-14 12:00:00', array( 'user' => $this->other, 'email' => get_userdata( $this->other )->user_email ) );
		$this->bookingAt( '2026-12-14 14:00:00', array( 'kind' => 'block' ) );

		$this->actAs( 'ishot_client' );
		$response = $this->get();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $mine ), self::ids( $response ) );
		$this->assertNotContains( $theirs, self::ids( $response ) );
		$this->assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
	}

	public function test_guest_bookings_under_the_same_email_appear_only_once_that_email_is_verified(): void {
		$guest = $this->bookingAt( '2026-12-14 10:00:00', array( 'email' => $this->me()->user_email ) );
		$this->actAs( 'ishot_client' );

		$this->assertSame( array(), self::ids( $this->get() ), 'claiming an address is not owning it' );

		ClientAccounts::markEmailVerified( $this->me()->ID );
		$this->assertSame( array( $guest ), self::ids( $this->get() ) );

		wp_update_user( array( 'ID' => $this->me()->ID, 'user_email' => 'new-address@example.com' ) );
		$this->assertSame( array(), self::ids( $this->get() ), 'verification belongs to the address, not the account' );
	}

	public function test_a_guest_booking_claimed_by_another_account_stays_with_that_account(): void {
		$this->bookingAt( '2026-12-14 10:00:00', array( 'user' => $this->other, 'email' => $this->me()->user_email ) );
		ClientAccounts::markEmailVerified( $this->me()->ID );
		$this->actAs( 'ishot_client' );
		$this->assertSame( array(), self::ids( $this->get() ) );
	}

	public function test_results_are_paginated_with_totals_in_headers(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->bookingAt( sprintf( '2026-12-%02d 10:00:00', 10 + $i ), array( 'user' => $this->me()->ID ) );
		}
		$this->actAs( 'ishot_client' );

		$page2 = $this->get( array( 'per_page' => 2, 'page' => 2 ) );
		$this->assertCount( 2, $page2->get_data() );
		$this->assertSame( 5, (int) $page2->get_headers()['X-WP-Total'] );
		$this->assertSame( 3, (int) $page2->get_headers()['X-WP-TotalPages'] );
		$this->assertSame( 400, $this->get( array( 'per_page' => 51 ) )->get_status() );
	}

	public function test_upcoming_and_past_filters(): void {
		$past     = $this->bookingAt( '2020-01-10 10:00:00', array( 'user' => $this->me()->ID ) );
		$upcoming = $this->bookingAt( gmdate( 'Y-m-d 10:00:00', time() + 10 * DAY_IN_SECONDS ), array( 'user' => $this->me()->ID ) );
		$this->actAs( 'ishot_client' );

		$this->assertSame( array( $upcoming ), self::ids( $this->get( array( 'when' => 'upcoming' ) ) ) );
		$this->assertSame( array( $past ), self::ids( $this->get( array( 'when' => 'past' ) ) ) );
	}

	public function test_the_response_carries_what_the_client_needs_and_no_secrets(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00', array( 'user' => $this->me()->ID ) );
		$this->repo->transition( $id, BookingStatus::Confirmed, 1, 1 );
		$this->actAs( 'ishot_client' );

		$item = $this->get()->get_data()[0];
		$this->assertSame( 'confirmed', $item['status'] );
		$this->assertArrayHasKey( 'start', $item );
		$this->assertArrayHasKey( 'deposit', $item );
		foreach ( array( 'public_token_hash', 'idempotency_key', 'client_user_id', 'version' ) as $secret ) {
			$this->assertArrayNotHasKey( $secret, $item );
		}
	}

	public function test_anonymised_bookings_are_not_listed(): void {
		$id = $this->bookingAt( '2020-01-10 10:00:00', array( 'user' => $this->me()->ID ) );
		( new Anonymiser() )->anonymise( $id, 'test' );
		$this->actAs( 'ishot_client' );
		$this->assertSame( array(), self::ids( $this->get() ) );
	}
}
