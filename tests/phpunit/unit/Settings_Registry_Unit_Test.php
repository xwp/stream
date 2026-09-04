<?php
namespace WP_Stream;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

class Settings_Registry_Unit_Test extends TestCase {
	/**
	 * Registry under test.
	 *
	 * @var Settings_Registry
	 */
	protected $registry;

	/**
	 * Plugin mock with admin purge stubs.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	protected function set_up() {
		parent::set_up();
		$this->stubTranslationFunctions();
		$this->stubEscapeFunctions();

		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_network_admin' )->justReturn( false );
		Functions\when( 'translate_user_role' )->returnArg();
		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/admin-ajax.php' );
		Functions\when( 'add_query_arg' )->alias( array( self::class, 'add_query_arg_stub' ) );
		self::stub_wp_roles();

		$this->plugin        = Mockery::mock( Plugin::class );
		$this->plugin->admin = Mockery::mock( Admin::class );
		$this->plugin->admin->purge = Mockery::mock( Admin_Purge::class );

		$this->plugin->shouldReceive( 'is_network_activated' )->andReturn( false )->byDefault();
		$this->plugin->shouldReceive( 'is_multisite_network_activated' )->andReturn( false )->byDefault();
		$this->plugin->shouldReceive( 'is_multisite_not_network_activated' )->andReturn( false )->byDefault();
		$this->plugin->admin->purge->shouldReceive( 'is_running_async_deletion' )->andReturn( false )->byDefault();
		$this->plugin->admin->purge->shouldReceive( 'is_running_auto_purge' )->andReturn( false )->byDefault();

		$this->registry = new Settings_Registry( $this->plugin );
	}

	/**
	 * Alias a test-local role source as WP_Roles and stub wp_roles().
	 *
	 * Host unit tests do not load WordPress; Settings_Registry::get_roles()
	 * calls wp_roles() and requires a WP_Roles instance.
	 *
	 * @return void
	 */
	private static function stub_wp_roles() {
		if ( ! class_exists( \WP_Roles::class, false ) ) {
			class_alias( Settings_Registry_Wp_Roles_Stub::class, 'WP_Roles' );
		}

		Functions\when( 'wp_roles' )->justReturn( new Settings_Registry_Wp_Roles_Stub() );
	}

	/**
	 * Minimal add_query_arg stand-in.
	 *
	 * @param mixed ...$args add_query_arg argument list.
	 * @return string
	 */
	public static function add_query_arg_stub( ...$args ) {
		if ( 2 === count( $args ) && is_array( $args[0] ) ) {
			$url   = (string) $args[1];
			$query = http_build_query( $args[0] );
			return false === strpos( $url, '?' ) ? $url . '?' . $query : $url . '&' . $query;
		}

		return 'http://example.com/wp-admin/admin-ajax.php';
	}

	/**
	 * Inject a network-style field, matching Network::get_network_admin_fields().
	 *
	 * @param array $fields Existing option fields.
	 * @return array
	 */
	public static function inject_site_access_field( $fields ) {
		$fields['general']['fields'][] = array(
			'name'    => 'site_access',
			'title'   => 'Site Access',
			'type'    => 'checkbox',
			'default' => 1,
		);

		return $fields;
	}

	public function test_get_fields_contains_core_sections() {
		$fields = $this->registry->get_fields();

		$this->assertArrayHasKey( 'general', $fields );
		$this->assertArrayHasKey( 'exclude', $fields );
		$this->assertArrayHasKey( 'advanced', $fields );
	}

	/**
	 * Registered fields match the expected section, type, and extra keys.
	 *
	 * @param string               $section Section slug.
	 * @param string               $name    Field name.
	 * @param string               $type    Expected field type.
	 * @param array<string, mixed> $extra   Optional extra key => expected value.
	 */
	#[DataProvider( 'data_field_schema' )]
	public function test_registered_field_matches_schema( $section, $name, $type, $extra ) {
		$field = $this->find_field( $this->registry->get_fields(), $section, $name );

		$this->assertNotNull( $field );
		$this->assertSame( $type, $field['type'] );

		foreach ( $extra as $key => $value ) {
			$this->assertSame( $value, $field[ $key ], "Failed asserting field[{$key}]" );
		}
	}

	/**
	 * Schema cases for core fields that share find + type assertions.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string, 3: array<string, mixed>}>
	 */
	public static function data_field_schema() {
		return array(
			'records_ttl'        => array( 'general', 'records_ttl', 'number', array( 'default' => 30 ) ),
			'exclude_rules'      => array( 'exclude', 'rules', 'rule_list', array() ),
			'delete_all_records' => array( 'advanced', 'delete_all_records', 'link', array() ),
			'clean_orphan_meta'  => array( 'advanced', 'clean_orphan_meta', 'link', array() ),
		);
	}

	/**
	 * has_field() / get_field() accept `{section}_{name}` and reject bare names.
	 *
	 * @param string      $key           Option key under test.
	 * @param bool        $exists        Expected has_field().
	 * @param string|null $expected_name Expected field name when present.
	 * @param string|null $expected_type Expected field type when present.
	 */
	#[DataProvider( 'data_field_lookup' )]
	public function test_has_field_and_get_field_use_section_name_option_key( $key, $exists, $expected_name, $expected_type ) {
		$this->assertSame( $exists, $this->registry->has_field( $key ) );

		if ( $exists ) {
			$field = $this->registry->get_field( $key );
			$this->assertIsArray( $field );
			$this->assertSame( $expected_name, $field['name'] );
			$this->assertSame( $expected_type, $field['type'] );
			return;
		}

		$this->assertNull( $this->registry->get_field( $key ) );
	}

	/**
	 * Lookup cases for option keys vs bare names and stale aliases.
	 *
	 * @return array<string, array{0: string, 1: bool, 2: string|null, 3: string|null}>
	 */
	public static function data_field_lookup() {
		return array(
			'option_key'          => array( 'general_records_ttl', true, 'records_ttl', 'number' ),
			'bare_name'           => array( 'records_ttl', false, null, null ),
			'stale_jira_alias'    => array( 'keep_records_for', false, null, null ),
			'unknown_section_key' => array( 'general_unknown', false, null, null ),
		);
	}

	/**
	 * Defaults are keyed by `{section}_{name}`.
	 *
	 * @param string $key      Defaults key (`{section}_{name}`).
	 * @param mixed  $expected Expected default.
	 */
	#[DataProvider( 'data_defaults' )]
	public function test_get_defaults_uses_section_name_keys( $key, $expected ) {
		$defaults = $this->registry->get_defaults();

		$this->assertSame( $expected, $defaults[ $key ] );
	}

	/**
	 * Default values for representative option keys.
	 *
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	public static function data_defaults() {
		return array(
			'records_ttl'        => array( 'general_records_ttl', 30 ),
			'keep_indefinitely'  => array( 'general_keep_records_indefinitely', 0 ),
			'exclude_rules'      => array( 'exclude_rules', array() ),
		);
	}

	public function test_network_filter_injects_fields_into_get_fields() {
		Filters\expectApplied( 'wp_stream_settings_option_fields' )
			->andReturnUsing( array( self::class, 'inject_site_access_field' ) );

		$this->assertTrue( $this->registry->has_field( 'general_site_access' ) );
		$field = $this->registry->get_field( 'general_site_access' );
		$this->assertSame( 'checkbox', $field['type'] );
	}

	/**
	 * Settings translations label `{section}_{name}` keys under the option group.
	 *
	 * @param string $option_key Settings option key.
	 * @param string $field_key  `{section}_{name}` label key.
	 * @param string $expected   Expected title.
	 */
	#[DataProvider( 'data_settings_translations' )]
	public function test_get_settings_translations_labels_option_keys( $option_key, $field_key, $expected ) {
		$labels = $this->registry->get_settings_translations( array(), $option_key );

		$this->assertSame( $expected, $labels[ $option_key ][ $field_key ] );
	}

	/**
	 * Translation labels for representative option keys.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function data_settings_translations() {
		return array(
			'records_ttl'   => array( 'wp_stream', 'general_records_ttl', 'Keep Records for' ),
			'exclude_rules' => array( 'wp_stream', 'exclude_rules', 'Exclude Rules' ),
		);
	}

	/**
	 * Deletion warning text depends on the precomputed running flag.
	 *
	 * @param bool   $is_running Whether deletion is running.
	 * @param string $needle     Expected substring.
	 */
	#[DataProvider( 'data_deletion_warning' )]
	public function test_get_deletion_warning_uses_precomputed_running_state( $is_running, $needle ) {
		$this->assertStringContainsString( $needle, $this->registry->get_deletion_warning( $is_running ) );
	}

	/**
	 * Running vs idle single-site deletion warning needles.
	 *
	 * @return array<string, array{0: bool, 1: string}>
	 */
	public static function data_deletion_warning() {
		return array(
			'running'          => array( true, 'Currently deleting records' ),
			'idle_single_site' => array( false, 'Warning: This will delete all activity records from the database.' ),
		);
	}

	public function test_get_roles_returns_translated_wp_role_names() {
		$roles = Settings_Registry::get_roles();

		$this->assertArrayHasKey( 'administrator', $roles );
		$this->assertSame( 'Administrator', $roles['administrator'] );
	}

	public function test_get_roles_returns_empty_array_when_wp_roles_unavailable() {
		Functions\when( 'wp_roles' )->justReturn( null );

		$this->assertSame( array(), Settings_Registry::get_roles() );
	}

	/**
	 * Find a field definition by section and name.
	 *
	 * @param array  $fields  get_fields() output.
	 * @param string $section Section slug.
	 * @param string $name    Field name.
	 * @return array|null
	 */
	private function find_field( $fields, $section, $name ) {
		if ( empty( $fields[ $section ]['fields'] ) ) {
			return null;
		}

		foreach ( $fields[ $section ]['fields'] as $field ) {
			if ( isset( $field['name'] ) && $name === $field['name'] ) {
				return $field;
			}
		}

		return null;
	}
}

/**
 * Test-local role list for Settings_Registry::get_roles().
 */
class Settings_Registry_Wp_Roles_Stub {

	/**
	 * Return role slug => label pairs.
	 *
	 * @return array<string, string>
	 */
	public function get_names() {
		return array(
			'administrator' => 'Administrator',
			'editor'        => 'Editor',
			'author'        => 'Author',
			'contributor'   => 'Contributor',
			'subscriber'    => 'Subscriber',
		);
	}
}
