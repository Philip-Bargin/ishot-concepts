<?php
namespace IShot\Booking\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Admin\View;
use IShot\Booking\Domain\BookingStatus;
use IShot\Booking\Notifications\Renderer;
use IShot\Booking\Persistence\BookingRepository;
use IShot\Booking\Rest\RateLimiter;
use IShot\Booking\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Private subscription feed for staff calendars (Google, Apple, Outlook):
 * GET /wp-json/ishot/v1/calendar.ics?key=…
 *
 * The URL's key is the only credential (calendar apps cannot log in), so it is 32 random
 * bytes, shown once when generated, stored only as a hash, and revoked by regenerating.
 * The feed carries client names and services but no email addresses or phone numbers,
 * because a subscribed calendar is easily shared further.
 */
final class CalendarFeed {

	public const OPTION = 'ishot_booking_feed_key_hash';
	public const ROUTE  = '/calendar.ics';

	private const PAST_DAYS   = 30;
	private const FUTURE_DAYS = 365;

	/** @return string The new key; it is not stored and cannot be shown again. */
	public static function regenerateKey(): string {
		$key = bin2hex( random_bytes( 32 ) );
		update_option( self::OPTION, hash( 'sha256', $key ), false );
		return $key;
	}

	public static function url( string $key ): string {
		return add_query_arg( 'key', $key, rest_url( 'ishot/v1' . self::ROUTE ) );
	}

	public static function hasKey(): bool {
		return '' !== (string) get_option( self::OPTION, '' );
	}

	public function register(): void {
		register_rest_route(
			'ishot/v1',
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'serve' ),
				'permission_callback' => '__return_true', // the key is checked in serve(); a miss is a 404
				'args'                => array(
					'key' => array(
						'type'    => 'string',
						'pattern' => '^[0-9a-f]{64}$',
					),
				),
			)
		);
	}

	public function serve( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limiter = new RateLimiter( 'feed', (int) apply_filters( 'ishot_booking_feed_rate_limit', 60 ), HOUR_IN_SECONDS );
		if ( ! $limiter->hit() ) {
			return new WP_Error( 'ishot_rate_limited', __( 'Too many requests.', 'ishot-booking' ), array( 'status' => 429 ) );
		}
		$stored = (string) get_option( self::OPTION, '' );
		$key    = (string) $request['key'];
		if ( '' === $stored || '' === $key || ! hash_equals( $stored, hash( 'sha256', $key ) ) ) {
			return new WP_Error( 'rest_no_route', __( 'No route was found matching the URL and request method.', 'ishot-booking' ), array( 'status' => 404 ) );
		}

		global $wpdb;
		$now    = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$repo   = new BookingRepository( $wpdb, Settings::int( 'granularity_minutes' ) );
		$rows   = $repo->between(
			gmdate( 'Y-m-d H:i:s', $now->getTimestamp() - self::PAST_DAYS * DAY_IN_SECONDS ),
			gmdate( 'Y-m-d H:i:s', $now->getTimestamp() + self::FUTURE_DAYS * DAY_IN_SECONDS )
		);
		$events = array();
		foreach ( $rows as $row ) {
			if ( BookingStatus::from( $row['status'] )->holdsSlot() || 'completed' === $row['status'] ) {
				$events[] = self::event( $row );
			}
		}

		$response = new WP_REST_Response( Ics::calendar( Ics::PUBLISH, $events, $now ) );
		$response->header( 'Content-Type', 'text/calendar; charset=utf-8' );
		$response->header( 'Cache-Control', 'private, no-store' );
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}

	/** @param array<string, mixed> $row */
	private static function event( array $row ): IcsEvent {
		$utc   = new DateTimeZone( 'UTC' );
		$block = 'block' === $row['kind'];
		if ( $block ) {
			$summary = __( 'Studio time blocked', 'ishot-booking' );
		} else {
			$service = null === $row['service_id'] ? '' : html_entity_decode( get_the_title( (int) $row['service_id'] ), ENT_QUOTES, 'UTF-8' );
			$summary = trim( ( '' !== $service ? $service : __( 'Session', 'ishot-booking' ) ) . ' · ' . $row['client_name'] );
		}
		$status = match ( $row['status'] ) {
			'requested', 'pending_deposit' => IcsEvent::TENTATIVE,
			default                        => IcsEvent::CONFIRMED,
		};
		return new IcsEvent(
			uid: Renderer::uid( (int) $row['id'] ),
			sequence: (int) $row['version'],
			start: new DateTimeImmutable( (string) $row['start_utc'], $utc ),
			end: new DateTimeImmutable( (string) $row['end_utc'], $utc ),
			summary: $summary,
			location: (string) $row['location'],
			/* translators: 1: status, 2: booking reference */
			description: $block ? '' : sprintf( __( 'Status: %1$s. Reference #%2$d.', 'ishot-booking' ), str_replace( '_', ' ', (string) $row['status'] ), (int) $row['id'] ),
			organizer_email: Settings::studioAddress(),
			organizer_name: Settings::studioName(),
			attendee_email: null,
			attendee_name: '',
			status: $status,
			url: $block ? '' : View::detailUrl( (int) $row['id'] )
		);
	}

	/**
	 * Serves the feed body as raw iCalendar instead of a JSON-encoded string.
	 *
	 * @param bool             $served
	 * @param WP_REST_Response $result
	 */
	public static function serveRaw( $served, $result, WP_REST_Request $request ): bool {
		if ( $served || '/ishot/v1' . self::ROUTE !== $request->get_route() || 200 !== $result->get_status() || ! is_string( $result->get_data() ) ) {
			return (bool) $served;
		}
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar text, served as text/calendar.
		return true;
	}
}
