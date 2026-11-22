<?php
namespace IShot\Booking\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Proof (exception E-5) that no wordpress/dev script can run or write outside a local development site.
 *
 * Each script runs as a real `wp eval-file` subprocess against the tests site (wp-env maps wordpress/dev to
 * wp-content/ishot-dev there). wp_get_environment_type() is never stubbed: `wp --exec` runs before wp-config.php
 * loads, so a define() there wins over the `local` value wp-config.php sets (the later define only warns).
 */
final class DevFixtureEnvironmentTest extends TestCase {

	private const SITE   = '/var/www/html';
	private const SCRIPT = '/var/www/html/wp-content/ishot-dev';

	/**
	 * The subprocess boots the tests site itself (not the PHPUnit database), where wp-env leaves plugins inactive.
	 * Without the plugin every script would refuse for the wrong reason, so make sure it is active first.
	 */
	public static function setUpBeforeClass(): void {
		$probe = self::exec( array( 'plugin', 'is-active', 'ishot-booking' ) );
		if ( 0 !== $probe['code'] ) {
			$activate = self::exec( array( 'plugin', 'activate', 'ishot-booking' ) );
			self::assertSame( 0, $activate['code'], $activate['stderr'] );
		}
	}

	/** @return array<string, array{string, string}> */
	public static function scriptsAndTypes(): array {
		$cases = array();
		foreach ( glob( self::SCRIPT . '/*.php' ) ?: array() as $path ) {
			foreach ( array( 'staging', 'production' ) as $type ) {
				$cases[ basename( $path, '.php' ) . ' under ' . $type ] = array( $path, $type );
			}
		}
		return $cases;
	}

	/** @return array<string, array{string}> */
	public static function scripts(): array {
		$cases = array();
		foreach ( glob( self::SCRIPT . '/*.php' ) ?: array() as $path ) {
			$cases[ basename( $path, '.php' ) ] = array( $path );
		}
		return $cases;
	}

	public function test_the_mapped_dev_directory_holds_the_scripts(): void {
		$this->assertGreaterThanOrEqual( 7, count( self::scripts() ), 'wp-content/ishot-dev must be mapped in env.tests.mappings.' );
	}

	/** @dataProvider scriptsAndTypes */
	public function test_every_dev_script_refuses_staging_and_production_with_the_opt_in_set( string $script, string $type ): void {
		$before = $this->snapshot();
		$run    = $this->runScript( $script, $type, true );
		$this->assertSame( 1, $run['code'], $run['stderr'] );
		$this->assertStringContainsString( sprintf( 'Environment type is "%s"', $type ), $run['stderr'] );
		$this->assertSame( $before, $this->snapshot(), 'A refused script must change nothing.' );
	}

	/** @dataProvider scripts */
	public function test_every_dev_script_refuses_without_the_opt_in_constant( string $script ): void {
		$before = $this->snapshot();
		$run    = $this->runScript( $script, 'development', false );
		$this->assertSame( 1, $run['code'], $run['stderr'] );
		$this->assertStringContainsString( 'ISHOT_DEV_FIXTURES', $run['stderr'] );
		$this->assertSame( $before, $this->snapshot(), 'A refused script must change nothing.' );
	}

	public function test_the_fixture_classes_refuse_through_their_scripts(): void {
		$before = $this->snapshot();
		foreach ( array( 'studio-details-dev', 'page-shells-dev' ) as $name ) {
			$run = $this->runScript( self::SCRIPT . "/{$name}.php", 'staging', true );
			$this->assertSame( 1, $run['code'], $name . ': ' . $run['stderr'] );
			$this->assertStringContainsString( 'Refused, nothing changed: ', $run['stderr'] );
		}
		$this->assertSame( $before, $this->snapshot(), 'Settings, pages and permalink_structure must be identical.' );
	}

	/**
	 * @return array{code: int, stdout: string, stderr: string}
	 */
	private function runScript( string $script, string $type, bool $opt_in ): array {
		$exec = sprintf( 'define("WP_ENVIRONMENT_TYPE","%s");', $type );
		if ( $opt_in ) {
			$exec .= 'define("ISHOT_DEV_FIXTURES",true);';
		}
		return self::exec( array( '--exec=' . $exec, 'eval-file', $script, 'nobody@example.com' ) );
	}

	/** @return array<string, mixed> */
	private function snapshot(): array {
		$code = <<<'PHP'
global $wpdb;
$table = $wpdb->prefix . 'ishot_bookings';
$rows  = $wpdb->get_results( "SELECT post_type, post_status, COUNT(*) AS n FROM {$wpdb->posts} GROUP BY post_type, post_status ORDER BY post_type, post_status", ARRAY_A );
$raw   = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'ishot_booking_settings' ) );
echo wp_json_encode( array(
	'settings_md5'        => md5( (string) $raw ),
	'permalink_structure' => $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'permalink_structure'" ),
	'blogname'            => $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'blogname'" ),
	'posts'               => $rows,
	'bookings'            => $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 'no table',
) );
PHP;
		$run  = self::exec( array( 'eval', $code ) );
		$this->assertSame( 0, $run['code'], 'Snapshot failed: ' . $run['stderr'] );
		$data = json_decode( $run['stdout'], true );
		$this->assertIsArray( $data, 'Snapshot output: ' . $run['stdout'] );
		return $data;
	}

	/**
	 * @param list<string> $args
	 * @return array{code: int, stdout: string, stderr: string}
	 */
	private static function exec( array $args ): array {
		$cmd  = array_merge( array( 'wp', '--path=' . self::SITE ), $args );
		$proc = proc_open( $cmd, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		self::assertIsResource( $proc, 'Could not start wp-cli.' );
		$stdout = (string) stream_get_contents( $pipes[1] );
		$stderr = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		return array(
			'code'   => proc_close( $proc ),
			'stdout' => $stdout,
			'stderr' => $stderr,
		);
	}
}
