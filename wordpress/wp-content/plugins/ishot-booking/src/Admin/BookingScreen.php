<?php
namespace IShot\Booking\Admin;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Application\StaffActions;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Settings;

/** One booking: details, the actions this user may take now, and its audit timeline. */
final class BookingScreen {

	/** Candidate actions per status, in the order buttons appear (primary first). */
	private const BY_STATUS = array(
		'requested'       => array( 'confirm', 'request_deposit', 'reschedule', 'cancel' ),
		'pending_deposit' => array( 'deposit_received', 'confirm', 'reschedule', 'cancel' ),
		'confirmed'       => array( 'complete', 'no_show', 'reschedule', 'cancel' ),
	);

	/** Only meaningful once the session has started. */
	private const AFTER_START = array( 'complete', 'no_show' );

	/**
	 * @param array<string, mixed> $row
	 * @return string[]
	 */
	public static function availableActions( array $row, DateTimeImmutable $now ): array {
		if ( 'booking' !== $row['kind'] ) {
			return array();
		}
		$started = $now >= new DateTimeImmutable( $row['start_utc'], new DateTimeZone( 'UTC' ) );
		return array_values(
			array_filter(
				self::BY_STATUS[ $row['status'] ] ?? array(),
				static fn( string $action ) => ( $started || ! in_array( $action, self::AFTER_START, true ) )
					&& current_user_can( StaffActions::ACTIONS[ $action ] )
			)
		);
	}

	private static function actionLabel( string $action ): string {
		$labels = array(
			'confirm'          => __( 'Confirm booking', 'ishot-booking' ),
			'request_deposit'  => __( 'Request deposit', 'ishot-booking' ),
			'deposit_received' => __( 'Mark deposit received', 'ishot-booking' ),
			'complete'         => __( 'Mark completed', 'ishot-booking' ),
			'no_show'          => __( 'Mark no-show', 'ishot-booking' ),
			'reschedule'       => __( 'Move to new time', 'ishot-booking' ),
			'cancel'           => __( 'Cancel booking', 'ishot-booking' ),
		);
		return $labels[ $action ];
	}

	/** Extra fields an action's form needs. */
	private static function fields( string $action, array $row ): string {
		$id = (int) $row['id'];
		return match ( $action ) {
			'request_deposit'  => sprintf(
				'<label for="ishot-deposit-%1$d">%2$s</label> <input id="ishot-deposit-%1$d" type="number" name="deposit_amount" min="0" step="0.01" value="%3$s" class="small-text">',
				$id,
				esc_html__( 'Deposit amount', 'ishot-booking' ),
				esc_attr( (string) ( $row['deposit_amount'] ?? ( null === $row['service_id'] ? '' : get_post_meta( (int) $row['service_id'], 'deposit_amount', true ) ) ) )
			),
			'deposit_received' => sprintf(
				'<label for="ishot-ref-%1$d">%2$s</label> <input id="ishot-ref-%1$d" type="text" name="note" maxlength="500" placeholder="%3$s">',
				$id,
				esc_html__( 'Payment reference', 'ishot-booking' ),
				esc_attr__( 'e.g. mobile money ref', 'ishot-booking' )
			),
			'reschedule'       => sprintf(
				'<label for="ishot-start-%1$d">%2$s</label> <input id="ishot-start-%1$d" type="datetime-local" name="start" required step="%3$d">',
				$id,
				esc_html__( 'New start', 'ishot-booking' ),
				Settings::int( 'granularity_minutes' ) * 60
			),
			'cancel'           => sprintf(
				'<label for="ishot-reason-%1$d">%2$s</label> <input id="ishot-reason-%1$d" type="text" name="reason" maxlength="500">',
				$id,
				esc_html__( 'Reason (optional)', 'ishot-booking' )
			),
			default            => '',
		};
	}

	private static function eventLabel( array $event ): string {
		$payload = null === $event['payload'] ? array() : (array) json_decode( $event['payload'], true );
		$label   = match ( $event['action'] ) {
			'created'        => __( 'Created', 'ishot-booking' ),
			'rescheduled'    => sprintf(
				/* translators: 1: old time, 2: new time */
				__( 'Moved from %1$s to %2$s', 'ishot-booking' ),
				View::when( $payload['from'] ?? null ),
				View::when( $payload['to'] ?? null )
			),
			'status_changed' => sprintf(
				/* translators: 1: old status, 2: new status */
				__( '%1$s → %2$s', 'ishot-booking' ),
				View::statusLabel( (string) $event['from_status'] ),
				View::statusLabel( (string) $event['to_status'] )
			),
			default          => $event['action'],
		};
		$extra = array_intersect_key( $payload, array_flip( array( 'note', 'reason', 'by' ) ) );
		return array() === $extra ? $label : $label . ' (' . implode( '; ', array_map( 'strval', $extra ) ) . ')';
	}

	private static function actor( ?string $user_id, array $event ): string {
		if ( null !== $user_id ) {
			$user = get_userdata( (int) $user_id );
			return false === $user ? __( 'Former staff member', 'ishot-booking' ) : $user->display_name;
		}
		$payload = null === $event['payload'] ? array() : (array) json_decode( $event['payload'], true );
		if ( 'client' === ( $payload['by'] ?? '' ) || 'created' === $event['action'] ) {
			return __( 'Client', 'ishot-booking' );
		}
		return __( 'System', 'ishot-booking' );
	}

	/** @param array<string, mixed> $row */
	public static function depositText( array $row ): string {
		$state = array(
			'none'     => __( 'Not requested', 'ishot-booking' ),
			'awaiting' => __( 'Awaiting payment', 'ishot-booking' ),
			'received' => __( 'Received', 'ishot-booking' ),
		)[ $row['deposit_status'] ] ?? $row['deposit_status'];
		return null === $row['deposit_amount'] ? $state : number_format_i18n( (float) $row['deposit_amount'], 2 ) . ' · ' . $state;
	}

	public static function render( int $id, DateTimeImmutable $now ): void {
		global $wpdb;
		$repo = new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
		$row  = $repo->find( $id );
		echo '<div class="wrap ishot-admin">';
		printf( '<p><a href="%1$s">&larr; %2$s</a></p>', esc_url( admin_url( 'admin.php?page=' . Menu::LIST_SLUG ) ), esc_html__( 'All bookings', 'ishot-booking' ) );
		if ( null === $row ) {
			printf( '<div class="notice notice-error"><p>%s</p></div></div>', esc_html__( 'Booking not found.', 'ishot-booking' ) );
			return;
		}

		/* translators: %d: booking number */
		printf( '<h1 class="ishot-title">%1$s %2$s</h1>', esc_html( sprintf( __( 'Booking #%d', 'ishot-booking' ), $id ) ), View::badge( $row['status'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		ActionHandler::notice();

		echo '<div class="ishot-detail">';
		echo '<section class="ishot-panel" aria-labelledby="ishot-summary"><h2 id="ishot-summary">' . esc_html__( 'Session', 'ishot-booking' ) . '</h2><dl class="ishot-dl">';
		$items = array(
			__( 'When', 'ishot-booking' )     => View::when( $row['start_utc'] ) . ' – ' . View::when( $row['end_utc'], (string) get_option( 'time_format' ) ),
			__( 'Service', 'ishot-booking' )  => View::serviceTitle( $row['service_id'] ),
			__( 'Client', 'ishot-booking' )   => $row['client_name'],
			__( 'Location', 'ishot-booking' ) => '' !== $row['location'] ? $row['location'] : '—',
			__( 'Deposit', 'ishot-booking' )  => self::depositText( $row ),
			__( 'Hold expires', 'ishot-booking' ) => View::when( $row['hold_expires_at'] ),
		);
		foreach ( $items as $term => $value ) {
			printf( '<dt>%1$s</dt><dd>%2$s</dd>', esc_html( $term ), esc_html( $value ) );
		}
		if ( '' !== $row['client_email'] ) {
			printf( '<dt>%1$s</dt><dd><a href="mailto:%2$s">%3$s</a></dd>', esc_html__( 'Email', 'ishot-booking' ), esc_attr( $row['client_email'] ), esc_html( $row['client_email'] ) );
		}
		if ( '' !== $row['client_phone'] ) {
			printf( '<dt>%1$s</dt><dd><a href="tel:%2$s">%3$s</a></dd>', esc_html__( 'Phone', 'ishot-booking' ), esc_attr( preg_replace( '/[^\d+]/', '', $row['client_phone'] ) ), esc_html( $row['client_phone'] ) );
		}
		if ( '' !== (string) $row['notes'] ) {
			printf( '<dt>%1$s</dt><dd>%2$s</dd>', esc_html__( 'Notes', 'ishot-booking' ), nl2br( esc_html( $row['notes'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped before nl2br.
		}
		echo '</dl></section>';

		$actions = self::availableActions( $row, $now );
		echo '<section class="ishot-panel" aria-labelledby="ishot-actions"><h2 id="ishot-actions">' . esc_html__( 'Actions', 'ishot-booking' ) . '</h2>';
		if ( array() === $actions ) {
			echo '<p>' . esc_html__( 'No actions are available for this booking.', 'ishot-booking' ) . '</p>';
		}
		foreach ( $actions as $i => $action ) {
			echo View::formOpen( ActionHandler::POST_ACTION, ActionHandler::nonceAction( $id ), 'ishot-action' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formOpen() escapes.
			printf( '<input type="hidden" name="booking" value="%1$d"><input type="hidden" name="version" value="%2$d">', $id, (int) $row['version'] );
			echo self::fields( $action, $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fields() escapes.
			printf(
				' <button type="submit" name="booking_action" value="%1$s" class="button %2$s">%3$s</button></form>',
				esc_attr( $action ),
				0 === $i ? 'button-primary' : ( 'cancel' === $action ? 'ishot-button-danger' : '' ),
				esc_html( self::actionLabel( $action ) )
			);
		}
		echo '</section>';

		echo '<section class="ishot-panel" aria-labelledby="ishot-history"><h2 id="ishot-history">' . esc_html__( 'History', 'ishot-booking' ) . '</h2><ol class="ishot-timeline">';
		foreach ( $repo->events( $id ) as $event ) {
			printf(
				'<li><time datetime="%1$s">%2$s</time> <strong>%3$s</strong> <span>%4$s</span></li>',
				esc_attr( str_replace( ' ', 'T', $event['created_at'] ) . 'Z' ),
				esc_html( View::when( $event['created_at'] ) ),
				esc_html( self::eventLabel( $event ) ),
				esc_html( self::actor( $event['actor_user_id'], $event ) )
			);
		}
		echo '</ol></section></div></div>';
	}
}
