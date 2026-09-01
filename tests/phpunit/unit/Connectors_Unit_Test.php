<?php
namespace WP_Stream;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

class Connectors_Unit_Test extends TestCase {
	protected function set_up() {
		parent::set_up();
		$this->stubTranslationFunctions();
	}

	public function test_builtin_connector_slugs_includes_core_and_integrated() {
		$this->assertContains( 'posts', Connectors::BUILTIN_CONNECTOR_SLUGS );
		$this->assertContains( 'jetpack', Connectors::BUILTIN_CONNECTOR_SLUGS );
	}

	public function test_register_connector_instances_registers_happy_path() {
		Functions\when( 'is_admin' )->justReturn( true );
		Actions\expectDone( 'wp_stream_after_connectors_registration' )
			->once()
			->with(
				array( 'unit-stub' => 'Unit Stub' ),
				Mockery::type( Connectors::class )
			);

		$connector  = $this->mock_connector();
		$connectors = $this->make_connectors();

		$connectors->register_connector_instances( array( 'unit-stub' => $connector ) );

		$this->assertSame( $connector, $connectors->connectors['unit-stub'] );
		$this->assertTrue( $connector->is_registered() );
		$this->assertSame( array( 'unit-ctx' => 'Unit Context' ), $connectors->contexts['unit-stub'] );
		$this->assertSame( 'Unit Stub', $connectors->term_labels['stream_connector']['unit-stub'] );
		$this->assertSame( 'Unit Action', $connectors->term_labels['stream_action']['unit-act'] );
		$this->assertSame( 'Unit Context', $connectors->term_labels['stream_context']['unit-ctx'] );
	}

	public function test_register_connector_instances_registers_on_frontend() {
		Functions\when( 'is_admin' )->justReturn( false );

		$connector  = $this->mock_connector();
		$connectors = $this->make_connectors();

		$connectors->register_connector_instances( array( 'unit-stub' => $connector ) );

		$this->assertSame( $connector, $connectors->connectors['unit-stub'] );
		$this->assertTrue( $connector->is_registered() );
	}

	/**
	 * Skip registration when admin, frontend, dependency, or exclusion gates fail.
	 *
	 * @param bool   $is_admin             Whether to treat the request as admin.
	 * @param string $slug                 Connector slug.
	 * @param bool   $register_admin       Connector register_admin flag.
	 * @param bool   $register_frontend    Connector register_frontend flag.
	 * @param bool   $dependency_satisfied Whether is_dependency_satisfied returns true.
	 * @param bool   $excluded             Whether the excluded filter returns true.
	 */
	#[DataProvider( 'data_register_connector_instances_skips' )]
	public function test_register_connector_instances_skips( $is_admin, $slug, $register_admin, $register_frontend, $dependency_satisfied, $excluded ) {
		Functions\when( 'is_admin' )->justReturn( $is_admin );

		if ( $excluded ) {
			Filters\expectApplied( 'wp_stream_check_connector_is_excluded' )->andReturn( true );
		}

		$connector                    = $this->mock_connector( $slug );
		$connector->register_admin    = $register_admin;
		$connector->register_frontend = $register_frontend;

		if ( ! $dependency_satisfied ) {
			$connector->shouldReceive( 'is_dependency_satisfied' )->andReturn( false );
		}

		$connectors = $this->make_connectors();
		$connectors->register_connector_instances( array( $slug => $connector ) );

		$this->assertArrayNotHasKey( $slug, $connectors->connectors );
		$this->assertFalse( $connector->is_registered() );
	}

	/**
	 * Data provider for test_register_connector_instances_skips.
	 *
	 * @return array<string, array{0: bool, 1: string, 2: bool, 3: bool, 4: bool, 5: bool}>
	 */
	public static function data_register_connector_instances_skips() {
		return array(
			'admin_register_admin_false'       => array( true, 'unit-stub', false, true, true, false ),
			'frontend_register_frontend_false' => array( false, 'unit-stub', true, false, true, false ),
			'unsatisfied_dependency'           => array( true, 'unit-unsatisfied', true, true, false, false ),
			'excluded_by_filter'               => array( true, 'unit-stub', true, true, true, true ),
		);
	}

	public function test_register_connector_instances_registers_extra_connector() {
		Functions\when( 'is_admin' )->justReturn( true );

		$extra      = $this->mock_connector( 'unit-extra' );
		$connectors = $this->make_connectors();

		$connectors->register_connector_instances( array( 'unit-extra' => $extra ) );

		$this->assertSame( $extra, $connectors->connectors['unit-extra'] );
		$this->assertTrue( $extra->is_registered() );
	}

	public function test_register_connector_instances_notices_when_register_method_missing() {
		Functions\when( 'is_admin' )->justReturn( true );

		$connector                    = Mockery::mock();
		$connector->name              = 'unit-no-register';
		$connector->register_admin    = true;
		$connector->register_frontend = true;
		$connector->shouldReceive( 'is_dependency_satisfied' )->andReturn( true );

		$connectors = $this->make_connectors();
		$connectors->plugin->admin->shouldReceive( 'notice' )
			->once()
			->with( Mockery::pattern( '/unit-no-register/' ), true );

		$connectors->register_connector_instances( array( 'unit-no-register' => $connector ) );

		$this->assertArrayNotHasKey( 'unit-no-register', $connectors->connectors );
	}

	public function test_load_connectors_registers_seeded_instances() {
		Functions\when( 'is_admin' )->justReturn( true );

		$connector  = $this->mock_connector();
		$connectors = $this->make_connectors();
		$this->seed_available( $connectors, array() );
		$this->seed_instances( $connectors, array( 'unit-stub' => $connector ) );

		$connectors->load_connectors();

		$this->assertSame( $connector, $connectors->connectors['unit-stub'] );
		$this->assertTrue( $connector->is_registered() );
	}

	public function test_load_connectors_keeps_filter_extra_that_extends_connector() {
		Functions\when( 'is_admin' )->justReturn( true );

		$extra = $this->mock_connector( 'unit-extra' );
		Filters\expectApplied( 'wp_stream_connectors' )->andReturn( array( $extra ) );

		$connectors = $this->make_connectors();
		$this->seed_available( $connectors, array() );

		$connectors->load_connectors();

		$this->assertSame( $extra, $connectors->connectors['unit-extra'] );
		$this->assertTrue( $extra->is_registered() );
		$this->assertSame( array( 'unit-extra' ), $connectors->get_slugs( true ) );
	}

	public function test_load_connectors_drops_filter_extra_that_is_not_connector() {
		Functions\when( 'is_admin' )->justReturn( true );
		Filters\expectApplied( 'wp_stream_connectors' )->andReturn( array( new stdClass() ) );

		$connectors = $this->make_connectors();
		$this->seed_available( $connectors, array() );

		$connectors->load_connectors();

		$this->assertSame( array(), $connectors->connectors );
		$this->assertSame( array(), $connectors->get_slugs( true ) );
	}

	public function test_load_connectors_keeps_unsatisfied_in_instances_but_does_not_register() {
		Functions\when( 'is_admin' )->justReturn( true );

		$connector = $this->mock_connector( 'unit-unsatisfied' );
		$connector->shouldReceive( 'is_dependency_satisfied' )->andReturn( false );
		$connectors = $this->make_connectors();
		$this->seed_available( $connectors, array() );
		$this->seed_instances( $connectors, array( 'unit-unsatisfied' => $connector ) );

		$connectors->load_connectors();

		$this->assertArrayNotHasKey( 'unit-unsatisfied', $connectors->connectors );
		$this->assertFalse( $connector->is_registered() );
		$this->assertSame( array( 'unit-unsatisfied' ), $connectors->get_slugs( true ) );
	}

	/**
	 * Return registered slugs and payloads, optionally including inactive connectors.
	 *
	 * @param bool  $include_inactive Whether to include skipped connectors.
	 * @param array $expected_slugs   Expected slugs from get_slugs().
	 * @param array $expected_all     Expected payloads from get_all().
	 */
	#[DataProvider( 'data_get_slugs_and_get_all' )]
	public function test_get_slugs_and_get_all( $include_inactive, $expected_slugs, $expected_all ) {
		Functions\when( 'is_admin' )->justReturn( false );

		$active                        = $this->mock_connector();
		$admin_only                    = $this->mock_connector( 'unit-admin-only' );
		$admin_only->register_frontend = false;
		$unsatisfied                   = $this->mock_connector( 'unit-unsatisfied' );
		$unsatisfied->shouldReceive( 'is_dependency_satisfied' )->andReturn( false );

		$connectors = $this->make_connectors();
		$this->seed_available( $connectors, array() );
		$this->seed_instances(
			$connectors,
			array(
				'unit-stub'        => $active,
				'unit-admin-only'  => $admin_only,
				'unit-unsatisfied' => $unsatisfied,
			)
		);

		$connectors->load_connectors();

		$this->assertSame( $expected_slugs, $connectors->get_slugs( $include_inactive ) );
		$this->assertSame( $expected_all, $connectors->get_all( $include_inactive ) );
	}

	/**
	 * Data provider for test_get_slugs_and_get_all.
	 *
	 * @return array<string, array{0: bool, 1: string[], 2: array<int, array{slug: string, label: string, contexts: array<string, string>, actions: array<string, string>}>}>
	 */
	public static function data_get_slugs_and_get_all() {
		$stub        = self::connector_payload( 'unit-stub' );
		$admin_only  = self::connector_payload( 'unit-admin-only' );
		$unsatisfied = self::connector_payload( 'unit-unsatisfied' );

		return array(
			'registered_only'  => array(
				false,
				array( 'unit-stub' ),
				array( $stub ),
			),
			'include_inactive' => array(
				true,
				array( 'unit-stub', 'unit-admin-only', 'unit-unsatisfied' ),
				array( $stub, $admin_only, $unsatisfied ),
			),
		);
	}

	public function test_unload_and_reload_connectors_toggle_registration() {
		Functions\when( 'is_admin' )->justReturn( true );

		$connector  = $this->mock_connector();
		$connectors = $this->make_connectors();
		$connectors->register_connector_instances( array( 'unit-stub' => $connector ) );

		$this->assertTrue( $connector->is_registered() );

		$connectors->unload_connectors();
		$this->assertFalse( $connector->is_registered() );

		$connectors->reload_connectors();
		$this->assertTrue( $connector->is_registered() );
	}

	public function test_unload_and_reload_named_connector() {
		Functions\when( 'is_admin' )->justReturn( true );

		$connector  = $this->mock_connector();
		$other      = $this->mock_connector( 'unit-other' );
		$connectors = $this->make_connectors();
		$connectors->register_connector_instances(
			array(
				'unit-stub'  => $connector,
				'unit-other' => $other,
			)
		);

		$connectors->unload_connector( 'unit-stub' );
		$this->assertFalse( $connector->is_registered() );
		$this->assertTrue( $other->is_registered() );

		$connectors->unload_connector( 'missing' );
		$this->assertFalse( $connector->is_registered() );

		$connectors->reload_connector( 'unit-stub' );
		$this->assertTrue( $connector->is_registered() );
		$this->assertTrue( $other->is_registered() );
	}

	/**
	 * Normalized get_all() payload used by data_get_slugs_and_get_all().
	 *
	 * @param string $slug Connector slug.
	 * @return array{slug: string, label: string, contexts: array<string, string>, actions: array<string, string>}
	 */
	private static function connector_payload( $slug ) {
		return array(
			'slug'     => $slug,
			'label'    => 'Unit Stub',
			'contexts' => array( 'unit-ctx' => 'Unit Context' ),
			'actions'  => array( 'unit-act' => 'Unit Action' ),
		);
	}

	/**
	 * Partial Connector mock with abstract label methods stubbed.
	 *
	 * @param string $name Connector slug.
	 * @return Connector
	 */
	private function mock_connector( $name = 'unit-stub' ) {
		$connector                    = Mockery::mock( Connector::class )->makePartial();
		$connector->name              = $name;
		$connector->register_admin    = true;
		$connector->register_frontend = true;
		$connector->shouldReceive( 'get_label' )->andReturn( 'Unit Stub' )->byDefault();
		$connector->shouldReceive( 'get_context_labels' )->andReturn( array( 'unit-ctx' => 'Unit Context' ) )->byDefault();
		$connector->shouldReceive( 'get_action_labels' )->andReturn( array( 'unit-act' => 'Unit Action' ) )->byDefault();

		return $connector;
	}

	/**
	 * Build a Connectors instance without running the production constructor.
	 *
	 * @return Connectors
	 */
	private function make_connectors() {
		$plugin            = Mockery::mock();
		$plugin->locations = array(
			'dir' => '',
		);
		$plugin->admin     = Mockery::mock();

		$connectors         = ( new ReflectionClass( Connectors::class ) )->newInstanceWithoutConstructor();
		$connectors->plugin = $plugin;

		return $connectors;
	}

	/**
	 * Seed the available-class-name cache so load_connectors() does not include files.
	 *
	 * @param Connectors $connectors  Connectors instance.
	 * @param string[]   $class_names Fully-qualified class names.
	 */
	private function seed_available( Connectors $connectors, array $class_names ) {
		$property = new ReflectionProperty( Connectors::class, 'available_connectors' );
		$property->setAccessible( true );
		$property->setValue( $connectors, $class_names );
	}

	/**
	 * Seed instantiated connectors so load_connectors() does not `new` class names.
	 *
	 * @param Connectors               $connectors Connectors instance.
	 * @param array<string, Connector> $instances  Instances keyed by slug.
	 */
	private function seed_instances( Connectors $connectors, array $instances ) {
		$property = new ReflectionProperty( Connectors::class, 'connector_instances' );
		$property->setAccessible( true );
		$property->setValue( $connectors, $instances );
	}
}
