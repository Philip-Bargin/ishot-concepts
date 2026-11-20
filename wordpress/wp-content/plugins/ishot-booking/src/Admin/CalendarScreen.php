<?php
namespace IShot\Booking\Admin;

use DateTimeImmutable;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Rest\Format;
use IShot\Booking\Settings;

/**
 * Week agenda: seven studio days with opening hours, bookings and blocks, plus a form to block
 * time. Server-rendered, so it works with a keyboard and screen reader and needs no script.
 */
final class CalendarScreen {

	/** Menu callback. */
	public static function page(): void {
		$week = sanitize_text_field( wp_unslash( $_GET['week'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		self::render( $week );
	}

	public static function render( string $week ): void {
		global $wpdb;
		$day  = Format::localDate( $week ) ?? new DateTimeImmutable( 'now', wp_timezone() );
		$repo = new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
		$data = CalendarWeek::build( $repo, $day, Settings::policy()->hours );
		$url  = static fn( string $date ) => add_query_arg(
			array(
				'page' => Menu::SLUG,
				'week' => $date,
			),
			admin_url( 'admin.php' )
		);

		echo '<div class="wrap ishot-admin"><h1 class="wp-heading-inline">' . esc_html__( 'Calendar', 'ishot-booking' ) . '</h1><hr class="wp-header-end">';
		ActionHandler::notice();

		$first = new DateTimeImmutable( $data['start'], wp_timezone() );
		printf(
			'<nav class="ishot-weeknav" aria-label="%1$s"><a class="button" href="%2$s">&larr; %3$s</a> <h2>%4$s</h2> <a class="button" href="%5$s">%6$s &rarr;</a> <a class="button-link" href="%7$s">%8$s</a></nav>',
			esc_attr__( 'Weeks', 'ishot-booking' ),
			esc_url( $url( $data['prev'] ) ),
			esc_html__( 'Previous week', 'ishot-booking' ),
			esc_html( sprintf( /* translators: %s: date */ __( 'Week of %s', 'ishot-booking' ), wp_date( (string) get_option( 'date_format' ), $first->getTimestamp() ) ) ),
			esc_url( $url( $data['next'] ) ),
			esc_html__( 'Next week', 'ishot-booking' ),
			esc_url( $url( '' ) ),
			esc_html__( 'This week', 'ishot-booking' )
		);

		$today = wp_date( 'Y-m-d' );
		echo '<ol class="ishot-week">';
		foreach ( $data['days'] as $day_data ) {
			$date      = new DateTimeImmutable( $day_data['date'], wp_timezone() );
			$hours     = null === $day_data['hours'] ? __( 'Closed', 'ishot-booking' ) : implode( '–', $day_data['hours'] );
			$is_today  = $day_data['date'] === $today;
			printf(
				'<li class="ishot-day%1$s"%2$s><h3><span>%3$s</span> <small>%4$s</small></h3>',
				$is_today ? ' ishot-day--today' : '',
				$is_today ? ' aria-current="date"' : '',
				esc_html( wp_date( 'D j M', $date->getTimestamp() ) ),
				esc_html( $hours )
			);
			if ( array() === $day_data['items'] ) {
				echo '<p class="ishot-muted">' . esc_html__( 'Nothing booked', 'ishot-booking' ) . '</p>';
			} else {
				echo '<ul>';
				foreach ( $day_data['items'] as $item ) {
					self::item( $item );
				}
				echo '</ul>';
			}
			echo '</li>';
		}
		echo '</ol>';

		if ( current_user_can( 'block_ishot_time' ) ) {
			self::blockForm();
		}
		echo '</div>';
	}

	/** @param array<string, mixed> $item */
	private static function item( array $item ): void {
		$time = esc_html( $item['start'] . '–' . $item['end'] );
		if ( 'block' === $item['kind'] ) {
			printf( '<li class="ishot-item ishot-item--block"><span class="ishot-item__time">%1$s</span> %2$s ', $time, esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $time escaped above.
			if ( current_user_can( 'block_ishot_time' ) ) {
				echo View::formOpen( ActionHandler::POST_UNBLOCK, ActionHandler::nonceAction( (int) $item['id'] ), 'ishot-inline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formOpen() escapes.
				printf(
					'<input type="hidden" name="booking" value="%1$d"><button type="submit" class="button-link">%2$s</button></form>',
					(int) $item['id'],
					esc_html__( 'Remove block', 'ishot-booking' )
				);
			}
			echo '</li>';
			return;
		}
		printf(
			'<li class="ishot-item ishot-item--%1$s"><a href="%2$s"><span class="ishot-item__time">%3$s</span> %4$s</a> %5$s</li>',
			esc_attr( $item['status'] ),
			esc_url( View::detailUrl( (int) $item['id'] ) ),
			$time, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_html( $item['title'] ),
			View::badge( $item['status'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		);
	}

	private static function blockForm(): void {
		$step = Settings::int( 'granularity_minutes' ) * 60;
		echo '<section class="ishot-panel" aria-labelledby="ishot-block-title"><h2 id="ishot-block-title">' . esc_html__( 'Block time', 'ishot-booking' ) . '</h2>';
		echo '<p class="ishot-muted">' . esc_html__( 'Blocked time cannot be booked by clients. Use it for days off, maintenance or outside shoots.', 'ishot-booking' ) . '</p>';
		echo View::formOpen( ActionHandler::POST_BLOCK, ActionHandler::BLOCK_NONCE, 'ishot-blockform' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formOpen() escapes.
		printf(
			'<p><label for="ishot-block-start">%1$s</label><br><input id="ishot-block-start" type="datetime-local" name="start" required step="%4$d"></p>
			<p><label for="ishot-block-end">%2$s</label><br><input id="ishot-block-end" type="datetime-local" name="end" required step="%4$d"></p>
			<p><label for="ishot-block-reason">%3$s</label><br><input id="ishot-block-reason" type="text" name="reason" maxlength="120" class="regular-text"></p>',
			esc_html__( 'From', 'ishot-booking' ),
			esc_html__( 'Until', 'ishot-booking' ),
			esc_html__( 'Reason (shown to staff only)', 'ishot-booking' ),
			(int) $step
		);
		submit_button( __( 'Block this time', 'ishot-booking' ) );
		echo '</form></section>';
	}
}
