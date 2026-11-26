<?php
namespace IShot\Booking\Tests\Integration;

use IShot\Booking\Dev\StudioDevFixtures;
use IShot\Booking\Settings;
use IShot\Booking\Studio;
use PHPUnit\Framework\TestCase;

/** The dev-only fixture writer fails safe: each guard alone blocks every write. */
final class StudioDevFixturesTest extends TestCase {

	private const FILLED = array( 'phone', 'whatsapp', 'instagram', 'facebook', 'email', 'address' );

	protected function setUp(): void {
		delete_option( Settings::OPTION );
	}

	/**
	 * The guard inputs are not a public parameter list: reach the private worker by reflection so each guard can be tested alone.
	 *
	 * @return array{ok: bool, reason: string, written: list<string>}
	 */
	private static function exec( string $env, mixed $opt_in, string $home ): array {
		$method = new \ReflectionMethod( StudioDevFixtures::class, 'execute' );
		return $method->invoke( null, $env, $opt_in, $home );
	}

	private function snapshot(): string {
		return serialize( get_option( Settings::OPTION, 'absent' ) );
	}

	/** @return array<string, array{string, mixed, string}> */
	public static function singleFailures(): array {
		return array(
			'production environment' => array( 'production', true, 'http://localhost:8888' ),
			'staging environment'    => array( 'staging', true, 'http://localhost:8888' ),
			'opt-in missing'         => array( 'development', null, 'http://localhost:8888' ),
			'opt-in truthy not true' => array( 'development', 1, 'http://localhost:8888' ),
			'opt-in string true'     => array( 'local', 'true', 'http://localhost:8888' ),
			'public host'            => array( 'development', true, 'https://ishotconcept.com' ),
			'look-alike host'        => array( 'development', true, 'https://localhost.evil.example' ),
			'local in the path'      => array( 'development', true, 'https://evil.example/site.local' ),
			'userinfo host'          => array( 'development', true, 'https://localhost@evil.example' ),
			'unparseable url'        => array( 'development', true, '' ),
		);
	}

	/** @dataProvider singleFailures */
	public function test_each_guard_alone_blocks_the_write( string $env, mixed $opt_in, string $home ): void {
		$result = self::exec( $env, $opt_in, $home );
		$this->assertFalse( $result['ok'] );
		$this->assertNotSame( '', $result['reason'] );
		$this->assertSame( array(), $result['written'] );
		$this->assertSame( 'absent', get_option( Settings::OPTION, 'absent' ), 'option must not even be created' );
	}

	public function test_a_blocked_run_leaves_existing_settings_byte_identical(): void {
		Settings::update(
			array(
				'lead_time_hours' => 7,
				'studio_address'  => 'Owner street',
			)
		);
		$before = $this->snapshot();
		foreach ( self::singleFailures() as [ $env, $opt_in, $home ] ) {
			$this->assertFalse( self::exec( $env, $opt_in, $home )['ok'] );
		}
		$this->assertSame( $before, $this->snapshot() );
	}

	/** @return array<string, array{string}> */
	public static function allowedHomes(): array {
		return array(
			'localhost with port' => array( 'http://localhost:8888' ),
			'loopback'            => array( 'http://127.0.0.1:8888' ),
			'.local'              => array( 'https://ishot.local' ),
			'.test'               => array( 'https://ishot.test' ),
		);
	}

	/** @dataProvider allowedHomes */
	public function test_all_guards_passing_fills_only_empty_fields_and_marks_them_unconfirmed( string $home ): void {
		delete_option( Settings::OPTION );
		$result = self::exec( 'development', true, $home );
		$this->assertTrue( $result['ok'] );
		$d = Studio::details();
		$this->assertSame( '0881405259', $d['phone'] );
		$this->assertSame( 'https://wa.me/231881405259', $d['whatsapp_url'] );
		$this->assertSame( 'https://www.instagram.com/ishot_concept/', $d['instagram_url'] );
		$this->assertSame( 'ishotconcept01@gmail.com', $d['email'] );
		$this->assertSame( 'Monrovia, Liberia', $d['address'] );
		$this->assertSame( 'https://www.facebook.com/people/Ishot-Concept/100083810506889/', $d['facebook_url'] );
		$this->assertSame( self::FILLED, Studio::unconfirmed() );
		$this->assertSame( self::FILLED, $result['written'] );
	}

	public function test_local_environment_type_is_also_allowed(): void {
		$this->assertTrue( self::exec( 'local', true, 'http://localhost' )['ok'] );
	}

	public function test_it_never_overwrites_a_non_empty_value(): void {
		Settings::update(
			array(
				'studio_address'      => 'Owner street',
				'studio_public_email' => 'owner@example.com',
			)
		);
		$result = self::exec( 'development', true, 'http://localhost:8888' );
		$this->assertTrue( $result['ok'] );
		$d = Studio::details();
		$this->assertSame( 'Owner street', $d['address'] );
		$this->assertSame( 'owner@example.com', $d['email'] );
		$this->assertNotContains( 'address', $result['written'] );
		$this->assertNotContains( 'email', $result['written'] );
		$this->assertNotContains( 'address', Studio::unconfirmed(), 'owner data is not marked unconfirmed' );
		$this->assertSame( '0881405259', $d['phone'] );
	}

	public function test_a_second_run_changes_nothing(): void {
		self::exec( 'development', true, 'http://localhost:8888' );
		$before = $this->snapshot();
		$result = self::exec( 'development', true, 'http://localhost:8888' );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( array(), $result['written'] );
		$this->assertSame( $before, $this->snapshot() );
	}

	public function test_the_only_public_entry_point_takes_no_guard_values(): void {
		$public = array_map( static fn( $m ) => $m->getName(), ( new \ReflectionClass( StudioDevFixtures::class ) )->getMethods( \ReflectionMethod::IS_PUBLIC ) );
		$this->assertSame( array( 'run' ), $public );
		$this->assertSame( 0, ( new \ReflectionMethod( StudioDevFixtures::class, 'run' ) )->getNumberOfParameters() );
	}

	public function test_run_reads_the_real_guards_and_refuses_without_the_opt_in_constant(): void {
		$result = StudioDevFixtures::run();
		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( 'ISHOT_DEV_FIXTURES', $result['reason'] );
		$this->assertSame( 'absent', get_option( Settings::OPTION, 'absent' ) );
	}

	public function test_fixture_values_round_trip_through_the_form_without_clearing_flags(): void {
		self::exec( 'development', true, 'http://localhost:8888' );
		$errors = \IShot\Booking\Admin\StudioForm::save(
			array(
				'studio_phone'         => StudioDevFixtures::VALUES['phone'],
				'studio_whatsapp'      => StudioDevFixtures::VALUES['whatsapp'],
				'studio_instagram_url' => StudioDevFixtures::VALUES['instagram'],
				'studio_facebook_url'  => StudioDevFixtures::VALUES['facebook'],
				'studio_public_email'  => StudioDevFixtures::VALUES['email'],
				'studio_address'       => StudioDevFixtures::VALUES['address'],
			)
		);
		$this->assertSame( array(), $errors, 'the fixture values must pass the form validation' );
		$this->assertSame( self::FILLED, Studio::unconfirmed() );
	}

	public function test_the_tests_environment_does_not_opt_in(): void {
		$this->assertFalse( defined( 'ISHOT_DEV_FIXTURES' ) );
	}
}
