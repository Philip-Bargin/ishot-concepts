<?php
namespace IShot\Booking\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use IShot\Booking\Admin\View;
use IShot\Booking\Calendar\Ics;
use IShot\Booking\Calendar\IcsEvent;
use IShot\Booking\Persistence\ManageLinks;
use IShot\Booking\Settings;

/**
 * Turns an outbox row into an email: subject, HTML body styled with the design tokens,
 * a plain-text alternative carrying the same information, and the calendar file.
 *
 * Everything printed comes from the row's snapshot (the booking as it was at the event) and
 * is escaped for HTML. A client's manage link is minted here, at send time, so the raw token
 * exists only in the outgoing email.
 */
final class Renderer {

	/** @var array{colour: array<string, string>, font: array<string, string>}|null */
	private static ?array $theme = null;

	public function __construct( private readonly ManageLinks $links = new ManageLinks() ) {}

	/** @param array<string, mixed> $row An ishot_notifications row. */
	public function render( array $row, DateTimeImmutable $now ): Message {
		$template = (string) $row['template'];
		$tpl      = Templates::get( $template );
		$snap     = json_decode( (string) $row['snapshot'], true );
		$client   = Rules::CLIENT === $row['recipient'];
		$values   = $this->values( $snap );
		$fill     = static fn( string $text ) => strtr( $text, $values );

		$link_hash = null;
		$button    = null;
		if ( Templates::CTA_MANAGE === $tpl['cta'] && $client ) {
			$issued    = $this->links->issue( (int) $snap['id'] );
			$link_hash = $issued['hash'];
			$button    = array( __( 'View or change your booking', 'ishot-booking' ), Settings::manageUrl() . '#token=' . $issued['token'] );
		} elseif ( Templates::CTA_ADMIN === $tpl['cta'] && ! $client ) {
			$button = array( __( 'Open the booking', 'ishot-booking' ), View::detailUrl( (int) $snap['id'] ) );
		}

		$method      = $row['calendar_method'] ?? null;
		$paragraphs  = array( $fill( $tpl['intro'] ) );
		$paragraphs  = array_merge( $paragraphs, $this->extraParagraphs( $template, $snap, $values, $method ) );
		$details     = $this->details( $snap, $values, $client );
		$attachments = array();
		$secondary   = null;
		if ( null !== $method ) {
			$attachments[] = array(
				'content'  => Ics::calendar( $method, array( $this->event( $snap, $method ) ), $now ),
				'filename' => Ics::CANCEL === $method ? 'cancellation.ics' : 'invitation.ics',
				'type'     => 'text/calendar; charset=utf-8; method=' . $method,
			);
			if ( Ics::REQUEST === $method ) {
				$secondary = array( __( 'Add to Google Calendar', 'ishot-booking' ), $this->googleLink( $snap ) );
			}
		}

		$subject = Mailer::headerText( (string) preg_replace( '/\s+/', ' ', $fill( $tpl['subject'] ) ) );
		return new Message(
			$client ? (string) $snap['client_email'] : Settings::studioAddress(),
			$subject,
			$this->html( $fill( $tpl['heading'] ), $paragraphs, $details, $button, $secondary ),
			$this->text( $fill( $tpl['heading'] ), $paragraphs, $details, $button, $secondary ),
			$attachments,
			$link_hash
		);
	}

	/** @return array<string, string> placeholder => value (raw; escaped where printed) */
	private function values( array $snap ): array {
		$utc      = new DateTimeZone( 'UTC' );
		$at       = static fn( ?string $v ) => null === $v || '' === $v ? null : ( new DateTimeImmutable( $v, $utc ) )->getTimestamp();
		$date     = static fn( ?int $ts ) => null === $ts ? '' : wp_date( 'l j F Y', $ts );
		$time     = static fn( ?int $ts ) => null === $ts ? '' : wp_date( (string) get_option( 'time_format', 'g:i a' ), $ts );
		$start    = $at( $snap['start_utc'] );
		$previous = $at( $snap['previous_start_utc'] ?? null );
		$hold     = $at( $snap['hold_expires_at'] ?? null );
		return array(
			'{client_name}'    => self::oneLine( (string) $snap['client_name'] ),
			'{service}'        => '' !== $snap['service_title'] ? (string) $snap['service_title'] : __( 'session', 'ishot-booking' ),
			'{date}'           => $date( $start ),
			'{time}'           => $time( $start ),
			'{previous_date}'  => $date( $previous ),
			'{previous_time}'  => $time( $previous ),
			'{deposit_amount}' => self::money( $snap['deposit_amount'] ?? null ),
			'{hold_expires}'   => null === $hold ? '' : $date( $hold ) . ' ' . $time( $hold ),
			'{studio_name}'    => Settings::studioName(),
		);
	}

	private static function oneLine( string $value ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', $value ) );
	}

	private static function money( ?string $amount ): string {
		return null === $amount ? '' : trim( Settings::all()['currency'] . ' ' . number_format( (float) $amount, 2 ) );
	}

	/** @return string[] */
	private function extraParagraphs( string $template, array $snap, array $values, ?string $method ): array {
		$out = array();
		if ( in_array( $template, array( 'deposit_requested', 'deposit_reminder' ), true ) ) {
			$instructions = trim( (string) Settings::all()['deposit_instructions'] );
			$out[]        = '' !== $instructions ? $instructions : __( 'Reply to this email and we will send you the payment details.', 'ishot-booking' );
		}
		if ( 'confirmed' === $template && 'received' === $snap['deposit_status'] && '' !== $values['{deposit_amount}'] ) {
			/* translators: %s: deposit amount */
			$out[] = sprintf( __( 'Your deposit of %s has been received. Thank you.', 'ishot-booking' ), $values['{deposit_amount}'] );
		}
		if ( 'rescheduled' === $template && Ics::REQUEST === $method ) {
			$out[] = __( 'An updated calendar invitation is attached; opening it moves the session in your calendar.', 'ishot-booking' );
		}
		if ( 'cancelled' === $template && Ics::CANCEL === $method ) {
			$out[] = __( 'The attached calendar update removes the session from your calendar.', 'ishot-booking' );
		}
		return $out;
	}

	/** @return array<int, array{0: string, 1: string}> label/value rows */
	private function details( array $snap, array $values, bool $client ): array {
		$rows = array(
			array( __( 'Service', 'ishot-booking' ), $values['{service}'] ),
			array( __( 'Date', 'ishot-booking' ), $values['{date}'] ),
			array( __( 'Time', 'ishot-booking' ), $values['{time}'] ),
		);
		if ( '' !== $snap['location'] ) {
			$rows[] = array( __( 'Location', 'ishot-booking' ), self::oneLine( (string) $snap['location'] ) );
		}
		if ( '' !== $values['{deposit_amount}'] ) {
			$state  = match ( $snap['deposit_status'] ) {
				'received' => __( 'paid', 'ishot-booking' ),
				'awaiting' => __( 'awaiting payment', 'ishot-booking' ),
				default    => '',
			};
			$rows[] = array( __( 'Deposit', 'ishot-booking' ), trim( $values['{deposit_amount}'] . ( '' !== $state ? ", {$state}" : '' ) ) );
		}
		if ( ! $client ) {
			$rows[] = array( __( 'Client', 'ishot-booking' ), $values['{client_name}'] . ' (' . $snap['client_email'] . ')' );
		}
		$rows[] = array( __( 'Reference', 'ishot-booking' ), '#' . (int) $snap['id'] );
		return $rows;
	}

	private function event( array $snap, string $method ): IcsEvent {
		$utc   = new DateTimeZone( 'UTC' );
		$title = '' !== $snap['service_title'] ? (string) $snap['service_title'] : __( 'Photo session', 'ishot-booking' );
		return new IcsEvent(
			uid: self::uid( (int) $snap['id'] ),
			sequence: (int) $snap['version'],
			start: new DateTimeImmutable( (string) $snap['start_utc'], $utc ),
			end: new DateTimeImmutable( (string) $snap['end_utc'], $utc ),
			summary: $title . ' · ' . Settings::studioName(),
			location: self::oneLine( (string) $snap['location'] ),
			/* translators: %s: studio name */
			description: sprintf( __( 'Booked with %s. Use the link in your email to change or cancel.', 'ishot-booking' ), Settings::studioName() ),
			organizer_email: Settings::studioAddress(),
			organizer_name: Settings::studioName(),
			attendee_email: (string) $snap['client_email'],
			attendee_name: self::oneLine( (string) $snap['client_name'] ),
			status: Ics::CANCEL === $method ? IcsEvent::CANCELLED : IcsEvent::CONFIRMED
		);
	}

	/** One identity per booking for its whole life, so calendar apps update instead of duplicating. */
	public static function uid( int $booking_id ): string {
		return 'booking-' . $booking_id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
	}

	private function googleLink( array $snap ): string {
		$utc  = new DateTimeZone( 'UTC' );
		$fmt  = static fn( string $v ) => ( new DateTimeImmutable( $v, $utc ) )->format( 'Ymd\THis\Z' );
		$args = array(
			'action'   => 'TEMPLATE',
			'text'     => ( '' !== $snap['service_title'] ? $snap['service_title'] : __( 'Photo session', 'ishot-booking' ) ) . ' · ' . Settings::studioName(),
			'dates'    => $fmt( (string) $snap['start_utc'] ) . '/' . $fmt( (string) $snap['end_utc'] ),
			'location' => (string) $snap['location'],
		);
		return 'https://calendar.google.com/calendar/render?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	/** @return array{colour: array<string, string>, font: array<string, string>} */
	private static function theme(): array {
		if ( null === self::$theme ) {
			$file        = dirname( __DIR__, 2 ) . '/assets/email/theme.json';
			self::$theme = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		}
		return self::$theme;
	}

	/**
	 * @param string[]                         $paragraphs
	 * @param array<int, array{0: string, 1: string}> $details
	 * @param array{0: string, 1: string}|null $button
	 * @param array{0: string, 1: string}|null $secondary
	 */
	private function html( string $heading, array $paragraphs, array $details, ?array $button, ?array $secondary ): string {
		$t    = self::theme();
		$c    = $t['colour'];
		$text = 'font-family:' . esc_attr( $t['font']['text'] ) . ';';
		$p    = '';
		foreach ( $paragraphs as $para ) {
			$p .= '<p style="margin:0 0 16px;font-size:16px;line-height:24px;color:' . $c['ink'] . ';' . $text . '">' . nl2br( esc_html( $para ) ) . '</p>';
		}
		$rows = '';
		foreach ( $details as [ $label, $value ] ) {
			$rows .= '<tr><td style="padding:8px 16px 8px 0;border-top:1px solid ' . $c['line'] . ';font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:' . $c['ink-muted'] . ';' . $text . '">' . esc_html( $label ) . '</td>'
				. '<td style="padding:8px 0;border-top:1px solid ' . $c['line'] . ';font-size:15px;color:' . $c['ink'] . ';' . $text . '">' . esc_html( $value ) . '</td></tr>';
		}
		$cta = '';
		if ( null !== $button ) {
			$cta .= '<p style="margin:24px 0 0;"><a href="' . esc_url( $button[1] ) . '" style="display:inline-block;padding:14px 24px;background-color:' . $c['band'] . ';color:' . $c['on-band'] . ';text-decoration:none;font-size:14px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;' . $text . '">' . esc_html( $button[0] ) . '</a></p>';
		}
		if ( null !== $secondary ) {
			$cta .= '<p style="margin:16px 0 0;font-size:14px;' . $text . '"><a href="' . esc_url( $secondary[1] ) . '" style="color:' . $c['accent'] . ';">' . esc_html( $secondary[0] ) . '</a></p>';
		}
		$studio = esc_html( Settings::studioName() );
		$lang   = esc_attr( get_bloginfo( 'language' ) );

		return '<!DOCTYPE html><html lang="' . $lang . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html( $heading ) . '</title></head>'
			. '<body style="margin:0;padding:0;background-color:' . $c['mist'] . ';">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:' . $c['mist'] . ';"><tr><td align="center" style="padding:32px 16px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:' . $c['paper'] . ';border-top:4px solid ' . $c['highlight'] . ';">'
			. '<tr><td style="padding:24px 32px;background-color:' . $c['band'] . ';color:' . $c['on-band'] . ';font-family:' . esc_attr( $t['font']['display'] ) . ';font-size:20px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;">' . $studio . '</td></tr>'
			. '<tr><td style="padding:32px;">'
			. '<h1 style="margin:0 0 24px;font-family:' . esc_attr( $t['font']['display'] ) . ';font-size:28px;line-height:34px;font-weight:700;text-transform:uppercase;color:' . $c['ink'] . ';">' . esc_html( $heading ) . '</h1>'
			. $p
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;border-bottom:1px solid ' . $c['line'] . ';">' . $rows . '</table>'
			. $cta
			. '</td></tr>'
			. '<tr><td style="padding:24px 32px;border-top:1px solid ' . $c['line'] . ';font-size:12px;line-height:18px;color:' . $c['ink-muted'] . ';' . $text . '">' . $studio . ' · ' . esc_html__( 'Reply to this email if you have any questions.', 'ishot-booking' ) . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/**
	 * @param string[]                         $paragraphs
	 * @param array<int, array{0: string, 1: string}> $details
	 * @param array{0: string, 1: string}|null $button
	 * @param array{0: string, 1: string}|null $secondary
	 */
	private function text( string $heading, array $paragraphs, array $details, ?array $button, ?array $secondary ): string {
		$lines = array( strtoupper( $heading ), '' );
		foreach ( $paragraphs as $para ) {
			$lines[] = $para;
			$lines[] = '';
		}
		foreach ( $details as [ $label, $value ] ) {
			$lines[] = "{$label}: {$value}";
		}
		foreach ( array_filter( array( $button, $secondary ) ) as [ $label, $url ] ) {
			$lines[] = '';
			$lines[] = "{$label}: {$url}";
		}
		$lines[] = '';
		$lines[] = '-- ';
		$lines[] = Settings::studioName() . ' · ' . __( 'Reply to this email if you have any questions.', 'ishot-booking' );
		return implode( "\n", $lines ) . "\n";
	}
}
