<?php
namespace IShot\Booking\Rest;

use DateTimeImmutable;
use DateTimeZone;

/** Date handling shared by the REST controllers. Storage is UTC; the API speaks studio time. */
final class Format {

	public const DATE_PATTERN     = '^\d{4}-\d{2}-\d{2}$';
	public const DATETIME_PATTERN = '^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$';

	/** UTC 'Y-m-d H:i:s' from the database → ISO 8601 in studio time. */
	public static function iso( DateTimeImmutable|string|null $time ): ?string {
		if ( null === $time ) {
			return null;
		}
		if ( is_string( $time ) ) {
			$time = new DateTimeImmutable( $time, new DateTimeZone( 'UTC' ) );
		}
		return $time->setTimezone( wp_timezone() )->format( DATE_ATOM );
	}

	/** Strict studio-local 'Y-m-d\TH:i'; null for malformed or impossible values such as 2026-02-30. */
	public static function localDateTime( string $value ): ?DateTimeImmutable {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
		return ( false !== $parsed && $parsed->format( 'Y-m-d\TH:i' ) === $value ) ? $parsed : null;
	}

	/** Studio-local midnight of a 'Y-m-d' date, or null when invalid. */
	public static function localDate( string $value ): ?DateTimeImmutable {
		return self::localDateTime( "{$value}T00:00" );
	}

	public static function utc( DateTimeImmutable $time ): string {
		return $time->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
}
