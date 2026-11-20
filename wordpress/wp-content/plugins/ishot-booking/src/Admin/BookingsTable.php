<?php
namespace IShot\Booking\Admin;

use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Settings;
use WP_List_Table;

/** The All bookings list: status views, upcoming/past filter, search, paging. */
final class BookingsTable extends WP_List_Table {

	private const PER_PAGE = 25;

	/** @param array{status?: string, when?: string, q?: string, paged?: int} $filters */
	public function __construct( private readonly array $filters ) {
		parent::__construct(
			array(
				'singular' => 'booking',
				'plural'   => 'bookings',
				'ajax'     => false,
			)
		);
	}

	public function get_columns(): array {
		return array(
			'when'    => __( 'When', 'ishot-booking' ),
			'client'  => __( 'Client', 'ishot-booking' ),
			'service' => __( 'Service', 'ishot-booking' ),
			'status'  => __( 'Status', 'ishot-booking' ),
			'deposit' => __( 'Deposit', 'ishot-booking' ),
		);
	}

	public function prepare_items(): void {
		global $wpdb;
		$repo   = new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
		$result = $repo->search(
			array(
				'status' => $this->filters['status'] ?? '',
				'when'   => $this->filters['when'] ?? 'upcoming',
				'now'    => gmdate( 'Y-m-d H:i:s' ),
				'q'      => $this->filters['q'] ?? '',
			),
			self::PER_PAGE,
			max( 1, (int) ( $this->filters['paged'] ?? 1 ) )
		);
		$this->items           = $result['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => self::PER_PAGE,
			)
		);
	}

	protected function get_views(): array {
		$when    = $this->filters['when'] ?? 'upcoming';
		$base    = admin_url( 'admin.php?page=' . Menu::LIST_SLUG );
		$options = array(
			'upcoming' => __( 'Upcoming', 'ishot-booking' ),
			'past'     => __( 'Past', 'ishot-booking' ),
			'all'      => __( 'All', 'ishot-booking' ),
		);
		$views   = array();
		foreach ( $options as $key => $label ) {
			$views[ $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( add_query_arg( 'when', $key, $base ) ),
				$key === $when ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}
		return $views;
	}

	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$current = $this->filters['status'] ?? '';
		echo '<div class="alignleft actions"><label class="screen-reader-text" for="ishot-status-filter">' . esc_html__( 'Filter by status', 'ishot-booking' ) . '</label>';
		echo '<select name="status" id="ishot-status-filter"><option value="">' . esc_html__( 'All statuses', 'ishot-booking' ) . '</option>';
		foreach ( array( 'requested', 'pending_deposit', 'confirmed', 'completed', 'cancelled', 'expired', 'no_show' ) as $status ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $status ), selected( $current, $status, false ), esc_html( View::statusLabel( $status ) ) );
		}
		echo '</select>';
		submit_button( __( 'Filter', 'ishot-booking' ), '', 'filter_action', false );
		echo '</div>';
	}

	public function no_items(): void {
		esc_html_e( 'No bookings match.', 'ishot-booking' );
	}

	/** @param array<string, mixed> $item */
	protected function column_when( $item ): string {
		return sprintf(
			'<a class="row-title" href="%1$s">%2$s</a>',
			esc_url( View::detailUrl( (int) $item['id'] ) ),
			esc_html( View::when( $item['start_utc'] ) )
		);
	}

	/** @param array<string, mixed> $item */
	protected function column_client( $item ): string {
		return esc_html( $item['client_name'] ) . '<br><span class="ishot-muted">' . esc_html( $item['client_email'] ) . '</span>';
	}

	/** @param array<string, mixed> $item */
	protected function column_service( $item ): string {
		return esc_html( View::serviceTitle( $item['service_id'] ) );
	}

	/** @param array<string, mixed> $item */
	protected function column_status( $item ): string {
		return View::badge( $item['status'] );
	}

	/** @param array<string, mixed> $item */
	protected function column_deposit( $item ): string {
		return esc_html( BookingScreen::depositText( $item ) );
	}
}
