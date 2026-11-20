<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Application\StaffActions;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Notifications\Outbox;
use IShot\Booking\Persistence\ManageLinks;
use IShot\Booking\Persistence\Schema;
use IShot\Booking\Privacy\Anonymiser;
use IShot\Booking\Privacy\PersonalData;
use IShot\Booking\Privacy\Retention;
use IShot\Booking\Settings;
use WP_UnitTestCase;

/** Personal-data export/erasure and the retention purge leave no client data behind anywhere. */
final class PrivacyTest extends WP_UnitTestCase {

	use BookingFixtures;

	public function set_up(): void {
		parent::set_up();
		$this->resetBookings();
	}

	public function tear_down(): void {
		$this->removeStaff();
		parent::tear_down();
	}

	/** A past, completed booking with every kind of personal data filled in. */
	private function pastBooking( string $start_utc = '2025-03-10 10:00:00', string $email = 'ada@example.com' ): int {
		global $wpdb;
		$id = $this->bookingAt( $start_utc, array( 'email' => $email ) );
		$wpdb->update(
			Schema::bookings(),
			array(
				'client_phone'   => '+231 770 000 000',
				'location'       => '12 Broad Street',
				'notes'          => 'Bring the red dress',
				'public_token_hash' => hash( 'sha256', "token-{$id}" ),
			),
			array( 'id' => $id )
		);
		( new StaffActions( $this->repo ) )->perform( $id, 'request_deposit', 1, 1, array( 'deposit_amount' => 50.0, 'note' => 'Asked by phone' ) );
		( new StaffActions( $this->repo ) )->perform( $id, 'deposit_received', 2, 1, array( 'reference' => 'MoMo 991' ) );
		$this->repo->transition( $id, BookingStatus::Completed, 3, 1, array(), array( 'note' => 'Client said she was 20 min late' ) );
		( new ManageLinks() )->issue( $id );
		return $id;
	}

	private function rowsFor( string $table, int $id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE booking_id = %d", $id ) ); // phpcs:ignore
	}

	/** Every stored byte about the booking, to grep for leftovers. */
	private function everything( int $id ): string {
		global $wpdb;
		$parts = array( wp_json_encode( $this->repo->find( $id ) ) );
		foreach ( array( Schema::events(), Schema::notifications(), Schema::links() ) as $table ) {
			$parts[] = wp_json_encode( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE booking_id = %d", $id ), ARRAY_A ) ); // phpcs:ignore
		}
		return implode( "\n", $parts );
	}

	public function test_the_exporter_and_eraser_are_registered_with_wordpress(): void {
		$this->assertArrayHasKey( PersonalData::ID, apply_filters( 'wp_privacy_personal_data_exporters', array() ) );
		$this->assertArrayHasKey( PersonalData::ID, apply_filters( 'wp_privacy_personal_data_erasers', array() ) );
	}

	public function test_export_lists_the_clients_bookings_with_their_details(): void {
		$id = $this->pastBooking();
		$this->pastBooking( '2025-03-11 10:00:00', 'someone-else@example.com' );

		$export = ( new PersonalData() )->export( 'ada@example.com' );
		$this->assertTrue( $export['done'] );
		$this->assertCount( 1, $export['data'] );
		$this->assertSame( PersonalData::ID . '-' . $id, $export['data'][0]['item_id'] );
		$values = array_column( $export['data'][0]['data'], 'value', 'name' );
		$this->assertSame( '+231 770 000 000', $values['Phone'] );
		$this->assertSame( 'Bring the red dress', $values['Notes'] );
		$this->assertSame( 'MoMo 991', $values['Payment reference'] );
	}

	public function test_export_also_finds_bookings_made_from_the_clients_account_under_another_email(): void {
		global $wpdb;
		$this->makeStaff();
		$client = get_userdata( $this->users['ishot_client'] );
		$id     = $this->bookingAt( '2025-03-10 10:00:00', array( 'email' => 'old-address@example.com' ) );
		$wpdb->update( Schema::bookings(), array( 'client_user_id' => $client->ID ), array( 'id' => $id ) );

		$this->assertCount( 1, ( new PersonalData() )->export( $client->user_email )['data'] );
	}

	public function test_erasure_anonymises_past_bookings_everywhere_they_are_stored(): void {
		$id     = $this->pastBooking();
		$before = $this->everything( $id );
		foreach ( array( 'ada@example.com', '770 000 000', 'red dress', 'Broad Street', 'MoMo 991', '20 min late' ) as $pii ) {
			$this->assertStringContainsString( $pii, $before, "fixture should hold {$pii}" );
		}

		$result = ( new PersonalData() )->erase( 'ada@example.com' );

		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
		$after = $this->everything( $id );
		foreach ( array( 'ada@example.com', 'Ada Test', '770 000 000', 'red dress', 'Broad Street', 'MoMo 991', '20 min late', 'Asked by phone' ) as $pii ) {
			$this->assertStringNotContainsString( $pii, $after, "{$pii} survived erasure" );
		}
		$this->assertSame( 0, $this->rowsFor( Schema::notifications(), $id ), 'email snapshots are deleted' );
		$this->assertSame( 0, $this->rowsFor( Schema::links(), $id ), 'manage links stop working' );

		$row = $this->repo->find( $id );
		$this->assertNotNull( $row['anonymised_at'] );
		$this->assertNull( $row['public_token_hash'] );
		$this->assertSame( array( 'completed', '50.00', 'received' ), array( $row['status'], $row['deposit_amount'], $row['deposit_status'] ), 'the studio keeps its accounts' );
		$events = $this->repo->events( $id );
		$this->assertSame( Anonymiser::EVENT, end( $events )['action'] );
		$this->assertSame( array(), ( new PersonalData() )->export( 'ada@example.com' )['data'] );
	}

	public function test_erasure_keeps_upcoming_sessions_and_says_why(): void {
		$past     = $this->pastBooking();
		$upcoming = $this->bookingAt( gmdate( 'Y-m-d H:00:00', time() + 10 * DAY_IN_SECONDS ) );
		$this->repo->transition( $upcoming, BookingStatus::Confirmed, 1, 1 );
		$cancelled = $this->bookingAt( gmdate( 'Y-m-d H:00:00', time() + 12 * DAY_IN_SECONDS ) );
		$this->repo->transition( $cancelled, BookingStatus::Cancelled, 1, 1 );

		$result = ( new PersonalData() )->erase( 'ada@example.com' );

		$this->assertTrue( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertCount( 1, $result['messages'] );
		$this->assertStringContainsString( "#{$upcoming}", $result['messages'][0] );
		$this->assertSame( 'ada@example.com', $this->repo->find( $upcoming )['client_email'] );
		$this->assertNotNull( $this->repo->find( $past )['anonymised_at'] );
		$this->assertNotNull( $this->repo->find( $cancelled )['anonymised_at'], 'a cancelled future booking holds no session to protect' );

		$again = ( new PersonalData() )->erase( 'ada@example.com' );
		$this->assertFalse( $again['items_removed'] );
		$this->assertTrue( $again['done'], 'retained bookings must not make WordPress loop' );
	}

	public function test_anonymising_twice_changes_nothing_and_staff_blocks_are_never_touched(): void {
		$id    = $this->pastBooking();
		$block = $this->bookingAt( '2025-04-01 10:00:00', array( 'kind' => 'block', 'name' => 'Studio maintenance' ) );
		$anon  = new Anonymiser();

		$this->assertTrue( $anon->anonymise( $id, 'test' ) );
		$this->assertFalse( $anon->anonymise( $id, 'test' ) );
		$this->assertFalse( $anon->anonymise( $block, 'test' ) );
		$this->assertSame( 'Studio maintenance', $this->repo->find( $block )['client_name'] );
	}

	public function test_retention_anonymises_only_sessions_older_than_the_setting(): void {
		$now = new DateTimeImmutable( '2027-06-01 12:00:00', new DateTimeZone( 'UTC' ) );
		Settings::update( array( 'retention_months' => 24 ) );
		$old    = $this->pastBooking( '2025-05-01 10:00:00' );
		$recent = $this->pastBooking( '2025-07-01 10:00:00' );

		$this->assertSame( 1, ( new Retention() )->purge( $now ) );
		$this->assertNotNull( $this->repo->find( $old )['anonymised_at'] );
		$this->assertNull( $this->repo->find( $recent )['anonymised_at'] );
		$this->assertSame( 0, ( new Retention() )->purge( $now ), 'already done' );
	}

	public function test_retention_of_zero_months_keeps_everything(): void {
		Settings::update( array( 'retention_months' => 0 ) );
		$id = $this->pastBooking( '2015-01-01 10:00:00' );

		$this->assertSame( 0, ( new Retention() )->purge( new DateTimeImmutable( 'now' ) ) );
		$this->assertNull( $this->repo->find( $id )['anonymised_at'] );
	}

	public function test_anonymised_bookings_no_longer_appear_in_the_outbox_count(): void {
		$id     = $this->pastBooking();
		$before = ( new Outbox() )->count();
		$this->assertGreaterThan( 0, $before );
		( new Anonymiser() )->anonymise( $id, 'test' );
		$this->assertSame( 0, ( new Outbox() )->count() );
	}
}
