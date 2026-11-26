<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Admin\BulkActions;
use IShot\Booking\Admin\CsvExport;
use IShot\Booking\Admin\ListScreen;
use IShot\Booking\Domain\BookingStatus;
use WP_UnitTestCase;

/** Bookings list: bulk status changes and CSV export. */
final class BulkExportTest extends WP_UnitTestCase {

	use BookingFixtures;

	public function set_up(): void {
		parent::set_up();
		$this->resetBookings();
		$this->makeStaff();
		delete_option( CsvExport::LOG_OPTION );
	}

	public function tear_down(): void {
		$this->removeStaff();
		parent::tear_down();
	}

	private static function now(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-12-01 08:00:00', new DateTimeZone( 'UTC' ) );
	}

	private function status( int $id ): string {
		return $this->repo->find( $id )['status'];
	}

	public function test_bulk_confirm_changes_every_eligible_booking_and_audits_it_as_bulk(): void {
		$ids = array( $this->bookingAt( '2026-12-14 10:00:00' ), $this->bookingAt( '2026-12-14 12:00:00' ) );
		$this->actAs( 'ishot_manager' );

		$result = BulkActions::apply( $ids, 'confirm', self::now() );

		$this->assertSame( 2, $result['done'] );
		$this->assertSame( array(), $result['skipped'] );
		foreach ( $ids as $id ) {
			$this->assertSame( 'confirmed', $this->status( $id ) );
			$events = $this->repo->events( $id );
			$this->assertSame( array( 'bulk' => true ), json_decode( end( $events )['payload'], true ) );
		}
	}

	public function test_a_booking_that_keeps_losing_deadlocks_is_skipped_as_busy_and_the_run_goes_on(): void {
		$ids = array( $this->bookingAt( '2026-12-14 10:00:00' ), $this->bookingAt( '2026-12-14 12:00:00' ) );
		$this->actAs( 'ishot_manager' );

		DeadlockingWpdb::swapIn()->deadlock_row_locks = true;
		try {
			$result = BulkActions::apply( $ids, 'confirm', self::now() );
		} finally {
			DeadlockingWpdb::restore();
		}

		$this->assertSame( 0, $result['done'] );
		$this->assertSame( array_fill_keys( $ids, 'busy' ), $result['skipped'] );
		foreach ( $ids as $id ) {
			$this->assertSame( 'requested', $this->status( $id ) );
		}
	}

	public function test_the_bulk_notice_reports_busy_bookings_apart_and_says_to_retry_them(): void {
		$text = ListScreen::bulkSummary( 3, 1, 2 );

		$this->assertStringContainsString( '3 bookings updated.', $text );
		$this->assertStringContainsString( '1 skipped', $text );
		$this->assertStringContainsString( '2 not changed because the calendar was busy. Select them and try again.', $text );
		$this->assertStringNotContainsString( 'busy', ListScreen::bulkSummary( 3, 1, 0 ) );
		$this->assertSame(
			array(
				'skipped' => 2,
				'busy'    => 2,
			),
			ListScreen::skipCounts( array( 4 => 'busy', 7 => 'changed', 9 => 'busy', 12 => 'not_allowed' ) ),
			'what the redirect after a bulk run carries'
		);
	}

	public function test_bookings_the_action_does_not_apply_to_are_skipped_not_forced(): void {
		$requested = $this->bookingAt( '2026-12-14 10:00:00' );
		$cancelled = $this->bookingAt( '2026-12-14 12:00:00' );
		$this->repo->transition( $cancelled, BookingStatus::Cancelled, 1, 1 );
		$future    = $this->bookingAt( '2026-12-15 10:00:00' );
		$this->repo->transition( $future, BookingStatus::Confirmed, 1, 1 );
		$block     = $this->bookingAt( '2026-12-16 10:00:00', array( 'kind' => 'block' ) );
		$this->actAs( 'ishot_manager' );

		$confirm = BulkActions::apply( array( $requested, $cancelled, $block, 999999 ), 'confirm', self::now() );
		$this->assertSame( 1, $confirm['done'] );
		$this->assertEqualsCanonicalizing( array( $cancelled, $block, 999999 ), array_keys( $confirm['skipped'] ) );
		$this->assertSame( 'cancelled', $this->status( $cancelled ) );

		$complete = BulkActions::apply( array( $future ), 'complete', self::now() );
		$this->assertSame( 0, $complete['done'], 'a session that has not started cannot be completed' );
		$this->assertSame( 'confirmed', $this->status( $future ) );
	}

	public function test_actions_needing_input_are_not_offered_in_bulk(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->actAs( 'ishot_manager' );
		foreach ( array( 'deposit_received', 'reschedule', 'request_deposit', 'delete' ) as $action ) {
			$this->assertSame( 'invalid', BulkActions::apply( array( $id ), $action, self::now() )['error'] );
		}
		$this->assertSame( 'requested', $this->status( $id ) );
	}

	public function test_staff_cannot_bulk_apply_actions_beyond_their_role(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );

		$this->actAs( 'ishot_photographer' );
		$this->assertSame( 'forbidden', BulkActions::apply( array( $id ), 'cancel', self::now() )['error'] );
		$this->assertSame( 'requested', $this->status( $id ) );
		$this->assertSame( 1, BulkActions::apply( array( $id ), 'confirm', self::now() )['done'], 'photographers may confirm' );

		$this->actAs( 'ishot_client' );
		$this->assertSame( 'forbidden', BulkActions::apply( array( $id ), 'confirm', self::now() )['error'] );
		$this->assertSame( array(), BulkActions::available() );
	}

	public function test_a_bulk_run_is_capped(): void {
		$this->actAs( 'ishot_manager' );
		$this->assertSame( 'too_many', BulkActions::apply( range( 1, BulkActions::MAX + 1 ), 'confirm', self::now() )['error'] );
	}

	public function test_the_list_offers_only_the_bulk_actions_the_user_may_take(): void {
		$this->actAs( 'ishot_photographer' );
		$this->assertEqualsCanonicalizing( array( 'confirm', 'complete', 'no_show' ), array_keys( BulkActions::available() ) );
		$this->actAs( 'ishot_manager' );
		$this->assertEqualsCanonicalizing( array( 'confirm', 'complete', 'no_show', 'cancel' ), array_keys( BulkActions::available() ) );
	}

	public function test_the_list_form_submits_bulk_actions_only_with_a_valid_nonce(): void {
		$id = $this->bookingAt( '2026-12-14 10:00:00' );
		$this->actAs( 'ishot_manager' );

		$forged = \IShot\Booking\Admin\ListScreen::processBulk( array( 'action' => 'confirm', 'booking' => array( $id ) ), self::now() );
		$this->assertSame( 'nonce', $forged['error'] );
		$this->assertSame( 'requested', $this->status( $id ) );

		$real = \IShot\Booking\Admin\ListScreen::processBulk(
			array(
				'action'   => '-1',
				'action2'  => 'confirm', // the bottom dropdown
				'booking'  => array( (string) $id ),
				'_wpnonce' => wp_create_nonce( 'bulk-bookings' ),
			),
			self::now()
		);
		$this->assertSame( 1, $real['done'] );
	}

	public function test_the_list_shows_checkboxes_bulk_actions_and_export_only_to_those_allowed(): void {
		$this->bookingAt( gmdate( 'Y-m-d 10:00:00', time() + 5 * DAY_IN_SECONDS ) );
		$render = static function (): string {
			ob_start();
			\IShot\Booking\Admin\ListScreen::render( array( 'when' => 'upcoming' ) );
			return (string) ob_get_clean();
		};

		$this->actAs( 'ishot_manager' );
		$html = $render();
		$this->assertStringContainsString( 'name="booking[]"', $html );
		$this->assertStringContainsString( 'value="cancel"', $html );
		$this->assertStringContainsString( 'action=' . CsvExport::POST, $html );

		$this->actAs( 'ishot_photographer' );
		$html = $render();
		$this->assertStringNotContainsString( 'value="cancel"', $html );
		$this->assertStringNotContainsString( 'action=' . CsvExport::POST, $html, 'no export link for photographers' );
	}

	public function test_csv_export_neutralises_spreadsheet_formulas(): void {
		foreach ( array( '=HYPERLINK("http://evil","x")', '+SUM(1)', '-2+3', '@cmd', "\tTAB", "\rCR" ) as $evil ) {
			$this->assertStringStartsWith( "'", CsvExport::cell( $evil ), $evil );
		}
		$this->assertSame( 'Ada Test', CsvExport::cell( 'Ada Test' ) );
	}

	public function test_only_managers_may_export_and_the_file_leaves_out_secrets_and_notes(): void {
		global $wpdb;
		$id = $this->bookingAt( '2026-12-14 10:00:00', array( 'name' => '=1+1' ) );
		$wpdb->update( \IShot\Booking\Persistence\Schema::bookings(), array( 'notes' => 'Private note', 'public_token_hash' => str_repeat( 'f', 64 ) ), array( 'id' => $id ) );

		$this->actAs( 'ishot_photographer' );
		$this->assertFalse( CsvExport::allowed() );
		$this->actAs( 'ishot_manager' );
		$this->assertTrue( CsvExport::allowed() );

		$rows = CsvExport::rows( array( 'when' => 'all' ) );
		$this->assertSame( 'Booking', $rows[0][0], 'header row' );
		$this->assertCount( 2, $rows );
		$this->assertContains( "'=1+1", $rows[1] );
		$flat = implode( '|', array_merge( ...$rows ) );
		$this->assertStringNotContainsString( 'Private note', $flat );
		$this->assertStringNotContainsString( str_repeat( 'f', 64 ), $flat );
	}

	public function test_exports_are_logged_with_who_and_how_many(): void {
		$this->bookingAt( '2026-12-14 10:00:00' );
		$this->actAs( 'ishot_manager' );
		CsvExport::log( CsvExport::rows( array( 'when' => 'all' ) ), array( 'when' => 'all' ) );

		$log = get_option( CsvExport::LOG_OPTION );
		$this->assertSame( $this->users['ishot_manager'], $log[0]['user'] );
		$this->assertSame( 1, $log[0]['rows'] );
	}
}
