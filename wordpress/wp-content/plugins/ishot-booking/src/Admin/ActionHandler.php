<?php
namespace IShot\Booking\Admin;

use IShot\Booking\Application\StaffActions;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Domain\TimeRange;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Persistence\NewBooking;
use IShot\Booking\Persistence\SlotConflict;
use IShot\Booking\Persistence\StaleBooking;
use IShot\Booking\Rest\Format;
use IShot\Booking\Settings;
use InvalidArgumentException;

/**
 * Form posts from the booking screens (admin-post.php). Each is checked for a nonce tied to the
 * booking, the capability for that action, and the version the screen showed.
 * process*() methods return a result; handle*() wrap them with a redirect for WordPress.
 */
final class ActionHandler {

	public const POST_ACTION    = 'ishot_booking_action';
	public const POST_BLOCK     = 'ishot_block_time';
	public const POST_UNBLOCK   = 'ishot_unblock_time';
	public const BLOCK_NONCE    = 'ishot_block_time';
	public const NOTICE_PARAM   = 'ishot_notice';

	public static function nonceAction( int $booking_id ): string {
		return 'ishot_booking_' . $booking_id;
	}

	private static function repo(): BookingRepository {
		global $wpdb;
		return new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
	}

	/** @return array{type: string, code: string} */
	private static function result( string $type, string $code ): array {
		return array(
			'type' => $type,
			'code' => $code,
		);
	}

	/** @param array<string, mixed> $post */
	private static function nonceOk( array $post, string $action ): bool {
		return false !== wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), $action );
	}

	/**
	 * @param array<string, mixed> $post Unslashed form fields.
	 * @return array{type: string, code: string}
	 */
	public static function process( array $post ): array {
		$id = absint( $post['booking'] ?? 0 );
		if ( ! self::nonceOk( $post, self::nonceAction( $id ) ) ) {
			return self::result( 'error', 'bad_nonce' );
		}
		$action = sanitize_key( (string) ( $post['booking_action'] ?? '' ) );
		if ( ! isset( StaffActions::ACTIONS[ $action ] ) ) {
			return self::result( 'error', 'invalid' );
		}
		if ( ! current_user_can( StaffActions::ACTIONS[ $action ] ) ) {
			return self::result( 'error', 'forbidden' );
		}
		$repo = self::repo();
		if ( null === $repo->find( $id ) ) {
			return self::result( 'error', 'not_found' );
		}

		$args = array(
			'note'   => sanitize_textarea_field( (string) ( $post['note'] ?? '' ) ),
			'reason' => sanitize_textarea_field( (string) ( $post['reason'] ?? '' ) ),
		);
		if ( isset( $post['deposit_amount'] ) && is_numeric( $post['deposit_amount'] ) && (float) $post['deposit_amount'] >= 0 ) {
			$args['deposit_amount'] = (float) $post['deposit_amount'];
		}
		if ( 'reschedule' === $action ) {
			$start = Format::localDateTime( (string) ( $post['start'] ?? '' ) );
			if ( null === $start ) {
				return self::result( 'error', 'invalid' );
			}
			$args['start'] = $start;
		}

		try {
			( new StaffActions( $repo ) )->perform( $id, $action, absint( $post['version'] ?? 0 ), get_current_user_id(), $args );
		} catch ( StaleBooking $e ) {
			return self::result( 'error', 'stale' );
		} catch ( SlotConflict $e ) {
			return self::result( 'error', 'slot_taken' );
		} catch ( InvalidArgumentException $e ) {
			return self::result( 'error', 'invalid' );
		}
		return self::result( 'success', $action );
	}

	/**
	 * @param array<string, mixed> $post
	 * @return array{type: string, code: string}
	 */
	public static function processBlock( array $post ): array {
		if ( ! self::nonceOk( $post, self::BLOCK_NONCE ) ) {
			return self::result( 'error', 'bad_nonce' );
		}
		if ( ! current_user_can( 'block_ishot_time' ) ) {
			return self::result( 'error', 'forbidden' );
		}
		$start = Format::localDateTime( (string) ( $post['start'] ?? '' ) );
		$end   = Format::localDateTime( (string) ( $post['end'] ?? '' ) );
		if ( null === $start || null === $end || $end <= $start ) {
			return self::result( 'error', 'invalid' );
		}
		try {
			self::repo()->create(
				new NewBooking(
					session: new TimeRange( $start, $end ),
					buffer_before_min: 0,
					buffer_after_min: 0,
					status: BookingStatus::Confirmed,
					kind: 'block',
					notes: sanitize_text_field( (string) ( $post['reason'] ?? '' ) ),
					client_user_id: get_current_user_id()
				)
			);
		} catch ( SlotConflict $e ) {
			return self::result( 'error', 'slot_taken' );
		}
		return self::result( 'success', 'blocked' );
	}

	/**
	 * @param array<string, mixed> $post
	 * @return array{type: string, code: string}
	 */
	public static function processUnblock( array $post ): array {
		$id = absint( $post['booking'] ?? 0 );
		if ( ! self::nonceOk( $post, self::nonceAction( $id ) ) ) {
			return self::result( 'error', 'bad_nonce' );
		}
		if ( ! current_user_can( 'block_ishot_time' ) ) {
			return self::result( 'error', 'forbidden' );
		}
		$repo = self::repo();
		$row  = $repo->find( $id );
		if ( null === $row || 'block' !== $row['kind'] ) {
			return self::result( 'error', 'not_found' );
		}
		if ( BookingStatus::from( $row['status'] )->holdsSlot() ) {
			try {
				$repo->transition( $id, BookingStatus::Cancelled, (int) $row['version'], get_current_user_id() );
			} catch ( StaleBooking $e ) {
				return self::result( 'error', 'stale' );
			}
		}
		return self::result( 'success', 'unblocked' );
	}

	/** Human message for a result code. */
	public static function message( string $code ): string {
		$messages = array(
			'confirm'          => __( 'Booking confirmed.', 'ishot-booking' ),
			'complete'         => __( 'Marked as completed.', 'ishot-booking' ),
			'no_show'          => __( 'Marked as no-show.', 'ishot-booking' ),
			'request_deposit'  => __( 'Deposit requested. The booking now waits for payment.', 'ishot-booking' ),
			'deposit_received' => __( 'Deposit recorded and booking confirmed.', 'ishot-booking' ),
			'cancel'           => __( 'Booking cancelled. The time is free again.', 'ishot-booking' ),
			'reschedule'       => __( 'Booking moved to the new time.', 'ishot-booking' ),
			'blocked'          => __( 'Time blocked.', 'ishot-booking' ),
			'unblocked'        => __( 'Block removed. The time is free again.', 'ishot-booking' ),
			'saved'            => __( 'Settings saved.', 'ishot-booking' ),
			'bad_nonce'        => __( 'This page had expired. Reload it and try again.', 'ishot-booking' ),
			'forbidden'        => __( 'Your role does not allow that action.', 'ishot-booking' ),
			'not_found'        => __( 'That booking no longer exists.', 'ishot-booking' ),
			'stale'            => __( 'Someone else changed this booking while you were looking. The latest version is shown below; try again.', 'ishot-booking' ),
			'slot_taken'       => __( 'That time overlaps another booking or block. Nothing was changed.', 'ishot-booking' ),
			'invalid'          => __( 'That change is not possible for this booking. Check the details and try again.', 'ishot-booking' ),
			'settings_invalid' => __( 'Some values need fixing. Nothing was saved.', 'ishot-booking' ),
		);
		return $messages[ $code ] ?? '';
	}

	/** Prints the notice for ?ishot_notice=…&ishot_type=… after a redirect. */
	public static function notice(): void {
		$code = sanitize_key( wp_unslash( $_GET[ self::NOTICE_PARAM ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$type = 'error' === ( $_GET['ishot_type'] ?? '' ) ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$text = self::message( $code );
		if ( '' !== $text ) {
			printf( '<div class="notice notice-%1$s is-dismissible" role="status"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $text ) );
		}
	}

	/** @param array{type: string, code: string} $result */
	private static function redirect( array $result ): never {
		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . Menu::SLUG );
		$back = remove_query_arg( array( self::NOTICE_PARAM, 'ishot_type' ), $back );
		wp_safe_redirect(
			add_query_arg(
				array(
					self::NOTICE_PARAM => $result['code'],
					'ishot_type'       => $result['type'],
				),
				$back
			)
		);
		exit;
	}

	public static function handleAction(): void {
		self::redirect( self::process( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in process().
	}

	public static function handleBlock(): void {
		self::redirect( self::processBlock( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in processBlock().
	}

	public static function handleUnblock(): void {
		self::redirect( self::processUnblock( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in processUnblock().
	}
}
