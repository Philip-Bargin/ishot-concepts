<?php
namespace IShot\Booking\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Plan acceptance criterion: N simultaneous requests for one slot produce exactly one booking.
 *
 * Each racer is a separate PHP process with its own MySQL connection (tests/Concurrency/claim-slot.php),
 * released at the same instant after booting, so the requests genuinely overlap inside MySQL.
 * The racers use overlapping (not identical) times to prove partial overlaps are caught too.
 */
final class ConcurrencyTest extends TestCase {

	private const RACERS = 50;

	private static function child( string ...$args ): string {
		$script = dirname( __DIR__ ) . '/Concurrency/claim-slot.php';
		$cmd    = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) );
		return trim( (string) shell_exec( $cmd . ' 2>&1' ) );
	}

	/** A child's output as one readable line: wp_die() pages are HTML. */
	private static function plain( string $output ): string {
		$body = preg_replace( '#<(style|script|head)\b.*?</\1>#is', '', $output );
		return substr( trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( (string) $body ) ) ) ), 0, 300 );
	}

	public function test_fifty_simultaneous_requests_for_overlapping_times_yield_exactly_one_booking(): void {
		$this->assertSame( 'ready', self::child( 'reset', '0', '' ) );

		$start_at = sprintf( '%.3f', microtime( true ) + 4 ); // time for every child to boot WordPress
		$pipes    = array();
		$procs    = array();
		for ( $i = 0; $i < self::RACERS; $i++ ) {
			// Starts alternate 10:00 and 10:30: every pair overlaps, none are identical.
			$slot     = 0 === $i % 2 ? '2026-12-14 10:00:00' : '2026-12-14 10:30:00';
			$cmd      = array( PHP_BINARY, dirname( __DIR__ ) . '/Concurrency/claim-slot.php', 'claim', $start_at, $slot );
			$procs[]  = proc_open( $cmd, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $p );
			$pipes[]  = $p;
		}

		$results = array();
		foreach ( $procs as $i => $proc ) {
			$results[] = trim( stream_get_contents( $pipes[ $i ][1] ) . stream_get_contents( $pipes[ $i ][2] ) );
			proc_close( $proc );
		}

		$winners   = array_filter( $results, static fn( $r ) => str_starts_with( $r, 'ok:' ) );
		$conflicts = array_filter( $results, static fn( $r ) => 'conflict' === $r );
		// A racer that lost every deadlock retry before the winner committed: a clean "busy, try again".
		$busy = array_filter( $results, static fn( $r ) => 'busy' === $r );

		// Evidence for the run log (stderr, so it does not count as test output).
		fwrite( STDERR, sprintf( "\nConcurrencyTest: %d racers: %d winner, %d conflict, %d busy\n", self::RACERS, count( $winners ), count( $conflicts ), count( $busy ) ) );

		$this->assertCount( 1, $winners, 'exactly one racer may win; got: ' . implode( ' | ', array_unique( $results ) ) );
		$others = array_diff_key( $results, $winners, $conflicts, $busy );
		$this->assertSame( array(), $others, 'every other racer must get a clean conflict or busy answer, not an error; got: ' . implode( ' | ', array_unique( array_map( array( self::class, 'plain' ), $others ) ) ) );
		$this->assertGreaterThan( count( $busy ), count( $conflicts ), 'busy must stay the rare exception, not the usual loser answer' );
		// One booking; its 60 min + 30 min buffer = 3 locked half-hour units.
		$this->assertSame( '1:3', self::child( 'count', '0', '' ) );
	}
}
