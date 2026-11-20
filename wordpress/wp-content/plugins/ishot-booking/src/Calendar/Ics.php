<?php
namespace IShot\Booking\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Builds iCalendar text (RFC 5545). Methods follow iTIP (RFC 5546): REQUEST for a new or
 * moved session, CANCEL to remove it, PUBLISH for the staff subscription feed.
 * All times are written in UTC, so no VTIMEZONE block is needed.
 */
final class Ics {

	public const REQUEST = 'REQUEST';
	public const CANCEL  = 'CANCEL';
	public const PUBLISH = 'PUBLISH';

	private const PRODID   = '-//iShot Concept//iShot Booking//EN';
	private const MAX_LINE = 75;

	/** @param IcsEvent[] $events */
	public static function calendar( string $method, array $events, DateTimeImmutable $now ): string {
		$lines = array( 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:' . self::PRODID, 'CALSCALE:GREGORIAN', 'METHOD:' . $method );
		foreach ( $events as $event ) {
			array_push( $lines, ...self::event( $event, $now ) );
		}
		$lines[] = 'END:VCALENDAR';
		return implode( '', array_map( static fn( string $line ) => self::fold( $line ) . "\r\n", $lines ) );
	}

	/** @return string[] */
	private static function event( IcsEvent $e, DateTimeImmutable $now ): array {
		$lines = array(
			'BEGIN:VEVENT',
			'UID:' . self::text( $e->uid ),
			'SEQUENCE:' . $e->sequence,
			'DTSTAMP:' . self::time( $now ),
			'DTSTART:' . self::time( $e->start ),
			'DTEND:' . self::time( $e->end ),
			'STATUS:' . $e->status,
			'SUMMARY:' . self::text( $e->summary ),
		);
		if ( '' !== $e->location ) {
			$lines[] = 'LOCATION:' . self::text( $e->location );
		}
		if ( '' !== $e->description ) {
			$lines[] = 'DESCRIPTION:' . self::text( $e->description );
		}
		if ( '' !== $e->url ) {
			$lines[] = 'URL:' . $e->url;
		}
		$lines[] = 'ORGANIZER;CN=' . self::param( $e->organizer_name ) . ':mailto:' . $e->organizer_email;
		if ( null !== $e->attendee_email ) {
			$lines[] = 'ATTENDEE;CN=' . self::param( $e->attendee_name ) . ';ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED:mailto:' . $e->attendee_email;
		}
		$lines[] = 'TRANSP:OPAQUE';
		$lines[] = 'END:VEVENT';
		return $lines;
	}

	private static function time( DateTimeImmutable $t ): string {
		return $t->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	}

	/** TEXT value escaping (RFC 5545 §3.3.11). */
	private static function text( string $value ): string {
		$value = str_replace( array( '\\', ';', ',' ), array( '\\\\', '\\;', '\\,' ), $value );
		return str_replace( array( "\r\n", "\r", "\n" ), '\\n', $value );
	}

	/** A parameter value: quoted when it holds a separator, never containing a quote or line break. */
	private static function param( string $value ): string {
		$value = trim( str_replace( array( '"', "\r", "\n" ), array( "'", ' ', ' ' ), $value ) );
		return preg_match( '/[;:,]/', $value ) ? '"' . $value . '"' : $value;
	}

	/** Folds a content line to 75 octets, never splitting a UTF-8 character (RFC 5545 §3.1). */
	private static function fold( string $line ): string {
		if ( strlen( $line ) <= self::MAX_LINE ) {
			return $line;
		}
		$out   = '';
		$chunk = '';
		$limit = self::MAX_LINE;
		foreach ( mb_str_split( $line, 1, 'UTF-8' ) as $char ) {
			if ( strlen( $chunk ) + strlen( $char ) > $limit ) {
				$out  .= $chunk . "\r\n ";
				$chunk = '';
				$limit = self::MAX_LINE - 1; // continuation lines start with one space
			}
			$chunk .= $char;
		}
		return $out . $chunk;
	}
}
