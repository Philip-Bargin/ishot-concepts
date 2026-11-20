<?php
namespace IShot\Booking\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Admin\CalendarWeek;
use IShot\Booking\Admin\DashboardSummary;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Settings;
use PHPUnit\Framework\TestCase;

/** The data behind the wp-admin screens: list filters, the week calendar and the dashboard widget. */
final class AdminQueriesTest extends TestCase {

	use BookingFixtures;

	private DateTimeImmutable $now;

	protected function setUp(): void {
		$this->resetBookings();
		$this->now = new DateTimeImmutable( '2026-12-10 12:00', new DateTimeZone( 'UTC' ) );
	}

	public function test_search_filters_upcoming_by_status_and_text_and_pages_results(): void {
		$past      = $this->bookingAt( '2026-12-01 10:00', array( 'status' => BookingStatus::Confirmed ) );
		$soon      = $this->bookingAt( '2026-12-14 10:00', array( 'name' => 'Bea Kollie' ) );
		$later     = $this->bookingAt( '2026-12-15 10:00', array( 'status' => BookingStatus::Confirmed, 'email' => 'cee@example.com' ) );
		$this->bookingAt( '2026-12-16 10:00', array( 'kind' => 'block' ) );
		$now_utc   = '2026-12-10 12:00:00';

		$upcoming = $this->repo->search( array( 'when' => 'upcoming', 'now' => $now_utc ), 10, 1 );
		$this->assertSame( array( $soon, $later ), array_map( 'intval', array_column( $upcoming['rows'], 'id' ) ) );
		$this->assertSame( 2, $upcoming['total'] );

		$past_rows = $this->repo->search( array( 'when' => 'past', 'now' => $now_utc ), 10, 1 );
		$this->assertSame( array( $past ), array_map( 'intval', array_column( $past_rows['rows'], 'id' ) ) );

		$confirmed = $this->repo->search( array( 'status' => 'confirmed' ), 10, 1 );
		$this->assertSame( array( $later, $past ), array_map( 'intval', array_column( $confirmed['rows'], 'id' ) ) );

		$this->assertSame( array( $soon ), array_map( 'intval', array_column( $this->repo->search( array( 'q' => 'kollie' ), 10, 1 )['rows'], 'id' ) ) );
		$this->assertSame( array( $later ), array_map( 'intval', array_column( $this->repo->search( array( 'q' => 'cee@' ), 10, 1 )['rows'], 'id' ) ) );

		$page_two = $this->repo->search( array(), 2, 2 );
		$this->assertSame( 3, $page_two['total'] );
		$this->assertSame( array( $past ), array_map( 'intval', array_column( $page_two['rows'], 'id' ) ) );
	}

	public function test_search_treats_wildcards_in_the_text_literally(): void {
		$this->bookingAt( '2026-12-14 10:00', array( 'name' => 'Ada Test' ) );

		$this->assertSame( 0, $this->repo->search( array( 'q' => '%' ), 10, 1 )['total'] );
		$this->assertSame( 0, $this->repo->search( array( 'q' => '_da' ), 10, 1 )['total'] );
	}

	public function test_week_lays_out_live_bookings_and_blocks_by_studio_day(): void {
		update_option( 'timezone_string', 'Africa/Lagos' ); // UTC+1, so day boundaries move
		$monday_late = $this->bookingAt( '2026-12-14 22:30', array( 'status' => BookingStatus::Confirmed ) ); // 23:30 Mon local
		$tuesday     = $this->bookingAt( '2026-12-14 23:30', array( 'name' => 'Bea Kollie' ) ); // 00:30 Tue local
		$block       = $this->bookingAt( '2026-12-15 10:00', array( 'kind' => 'block' ) );
		$cancelled = $this->bookingAt( '2026-12-16 10:00' );
		$this->repo->transition( $cancelled, BookingStatus::Cancelled, 1 );

		$week = CalendarWeek::build( $this->repo, new DateTimeImmutable( '2026-12-17', wp_timezone() ), Settings::policy()->hours );

		$this->assertSame( '2026-12-14', $week['start'] );
		$this->assertSame( '2026-12-07', $week['prev'] );
		$this->assertSame( '2026-12-21', $week['next'] );
		$this->assertCount( 7, $week['days'] );
		$this->assertSame( array( '09:00', '18:00' ), $week['days'][0]['hours'] );
		$this->assertNull( $week['days'][6]['hours'] ); // Sunday closed by default

		$this->assertSame( array( $monday_late ), array_column( $week['days'][0]['items'], 'id' ) );
		$this->assertSame( '23:30', $week['days'][0]['items'][0]['start'] );
		$this->assertSame( array( $tuesday, $block ), array_column( $week['days'][1]['items'], 'id' ) );
		$this->assertSame( 'Bea Kollie', $week['days'][1]['items'][0]['title'] );
		$this->assertSame( 'block', $week['days'][1]['items'][1]['kind'] );
		$this->assertSame( array(), $week['days'][2]['items'] );
	}

	public function test_dashboard_summary_counts_what_needs_attention(): void {
		$this->bookingAt( '2026-12-10 15:00', array( 'status' => BookingStatus::Confirmed, 'name' => 'Today Client' ) );
		$this->bookingAt( '2026-12-10 09:00', array( 'status' => BookingStatus::Confirmed ) ); // earlier today, still listed
		$this->bookingAt( '2026-12-11 10:00', array( 'status' => BookingStatus::Confirmed ) );
		$this->bookingAt( '2026-12-14 10:00', array( 'hold' => '2026-12-11 06:00' ) ); // request, expires within 24h
		$this->bookingAt( '2026-12-15 10:00', array( 'hold' => '2026-12-13 06:00' ) ); // request, expires later
		$pending = $this->bookingAt( '2026-12-16 10:00', array( 'hold' => '2026-12-12 06:00' ) );
		$this->repo->transition( $pending, BookingStatus::PendingDeposit, 1 );

		$summary = DashboardSummary::build( $this->repo, $this->now );

		$this->assertSame( array( 'Ada Test', 'Today Client' ), array_column( $summary['today'], 'client_name' ) );
		$this->assertSame( 2, $summary['requests'] );
		$this->assertSame( 1, $summary['awaiting_deposit'] );
		$this->assertSame( 1, $summary['expiring_soon'] );
	}
}
