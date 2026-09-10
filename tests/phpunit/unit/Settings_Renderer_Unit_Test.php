<?php
namespace WP_Stream;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

require_once __DIR__ . '/settings-registry-wp-roles-stub.php';

class Settings_Renderer_Unit_Test extends TestCase {
	/**
	 * Renderer under test.
	 *
	 * @var Settings_Renderer
	 */
	protected $renderer;

	/**
	 * Picker state returned by the db mock.
	 *
	 * @var array
	 */
	protected $picker_state;

	/**
	 * Plugin mock with connectors stubs.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	protected function set_up() {
		parent::set_up();
		$this->stubTranslationFunctions();
		$this->stubEscapeFunctions();

		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'absint' )->alias( array( self::class, 'absint_stub' ) );
		Functions\when( 'checked' )->alias( array( self::class, 'checked_stub' ) );
		Functions\when( 'selected' )->alias( array( self::class, 'selected_stub' ) );
		Functions\when( 'translate_user_role' )->returnArg();
		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
		Functions\when( 'wp_parse_args' )->alias( array( self::class, 'wp_parse_args_stub' ) );
		if ( ! class_exists( \WP_Roles::class, false ) ) {
			class_alias( Settings_Registry_Wp_Roles_Stub::class, 'WP_Roles' );
		}
		Functions\when( 'wp_roles' )->justReturn( new Settings_Registry_Wp_Roles_Stub() );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_users' )->justReturn(
			array(
				(object) array(
					'ID'           => 1,
					'display_name' => 'Test Admin',
				),
				(object) array(
					'ID'           => 2,
					'display_name' => 'Test Editor',
				),
			)
		);
		Functions\when( 'get_userdata' )->justReturn( false );

		$this->plugin = Mockery::mock( Plugin::class );

		$this->picker_state = array(
			'ajax'  => false,
			'items' => array(),
		);
		$picker = Mockery::mock( User_Picker::class );
		$picker->allows( 'get' )->andReturnUsing(
			function () {
				return $this->picker_state;
			}
		);
		$picker->allows( 'label' )->andReturnUsing(
			static function ( int $user_id ) {
				return 0 === $user_id ? 'WP-CLI' : 'STUB_LABEL_' . $user_id;
			}
		);
		$this->plugin->user_picker = $picker;
		$admin               = Mockery::mock( Admin::class );
		$admin->allows( 'get_preload_users_max' )->andReturn( 50 );
		$this->plugin->admin = $admin;

		$this->plugin->connectors              = Mockery::mock( Connectors::class );
		$this->plugin->connectors->term_labels = array(
			'stream_action'    => array(
				'updated' => 'Updated',
			),
			'stream_connector' => array(
				'posts' => 'Posts',
			),
			'stream_context'   => array(
				'post' => 'Posts',
			),
		);
		$this->plugin->connectors->contexts    = array(
			'posts' => array(
				'post' => 'Posts',
			),
		);

		$this->renderer = new Settings_Renderer( $this->plugin );
	}

	/**
	 * WordPress absint stand-in.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function absint_stub( $value ) {
		return abs( (int) $value );
	}

	/**
	 * WordPress checked() stand-in.
	 *
	 * @param mixed $checked Value to check.
	 * @param mixed $current Comparison value.
	 * @param bool  $echo    Unused.
	 * @return string
	 */
	public static function checked_stub( $checked, $current = true, $echo = true ) {
		unset( $echo );
		return ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
	}

	/**
	 * WordPress selected() stand-in.
	 *
	 * @param mixed $selected Value to check.
	 * @param mixed $current  Comparison value.
	 * @param bool  $echo     Unused.
	 * @return string
	 */
	public static function selected_stub( $selected, $current = true, $echo = true ) {
		unset( $echo );
		return ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';
	}

	/**
	 * WordPress wp_parse_args stand-in.
	 *
	 * @param array $args     Incoming args.
	 * @param array $defaults Defaults.
	 * @return array
	 */
	public static function wp_parse_args_stub( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
	}

	/**
	 * Build a field array for render_field(), with general/records_ttl defaults.
	 *
	 * @param array $overrides Field keys merged over the defaults.
	 * @return array
	 */
	private function make_field( array $overrides = array() ) {
		return array_merge(
			array(
				'type'    => 'text',
				'section' => 'general',
				'name'    => 'records_ttl',
			),
			$overrides
		);
	}

	/**
	 * Rendered control HTML matches type-specific name, value, and description needles.
	 *
	 * @param array    $overrides    Merged into make_field() defaults.
	 * @param string[] $contains     Substrings that must appear.
	 * @param string[] $not_contains Substrings that must not appear.
	 * @param string[] $count_once   Substrings that must appear exactly once.
	 */
	#[DataProvider( 'data_render_field_html' )]
	public function test_render_field_html( $overrides, $contains, $not_contains, $count_once ) {
		$html = $this->renderer->render_field(
			$this->make_field( $overrides ),
			array(),
			'wp_stream'
		);

		foreach ( $contains as $needle ) {
			$this->assertStringContainsString( $needle, $html, "Expected HTML to contain: {$needle}" );
		}

		foreach ( $not_contains as $needle ) {
			$this->assertStringNotContainsString( $needle, $html, "Expected HTML not to contain: {$needle}" );
		}

		foreach ( $count_once as $needle ) {
			$this->assertSame( 1, substr_count( $html, $needle ), "Expected HTML to contain exactly once: {$needle}" );
		}
	}

	/**
	 * Render-field HTML cases keyed by control type.
	 *
	 * @return array<string, array{0: array, 1: array<int, string>, 2: array<int, string>, 3: array<int, string>}>
	 */
	public static function data_render_field_html() {
		return array(
			'number'         => array(
				array(
					'type'        => 'number',
					'name'        => 'records_ttl',
					'class'       => 'small-text',
					'min'         => 1,
					'max'         => 999,
					'step'        => 1,
					'after_field' => 'days',
					'value'       => 30,
				),
				array(
					'type="number"',
					'name="wp_stream[general_records_ttl]"',
					'value="30"',
					'days',
				),
				array(),
				array(),
			),
			'checkbox'       => array(
				array(
					'type'        => 'checkbox',
					'name'        => 'keep_records_indefinitely',
					'after_field' => 'Enabled',
					'value'       => 1,
				),
				array(
					'type="checkbox"',
					'name="wp_stream[general_keep_records_indefinitely]"',
					'checked="checked"',
					'Enabled',
				),
				array(),
				array(),
			),
			'multi_checkbox' => array(
				array(
					'type'    => 'multi_checkbox',
					'name'    => 'role_access',
					'choices' => array(
						'administrator' => 'Administrator',
						'editor'        => 'Editor',
					),
					'value'   => array( 'administrator' ),
				),
				array(
					'name="wp_stream[general_role_access][]"',
					'value="administrator"',
					'value="editor"',
					'__placeholder__',
					'Administrator',
				),
				array(),
				array(),
			),
			'link'           => array(
				array(
					'type'    => 'link',
					'section' => 'advanced',
					'name'    => 'delete_all_records',
					'class'   => 'warning',
					'href'    => 'http://example.com/reset',
					'title'   => 'Reset Stream Database',
					'desc'    => 'Warning: This will delete all activity records from the database.',
				),
				array(
					'<a ',
					'href="http://example.com/reset"',
					'Reset Stream Database',
					'class="description"',
					'Warning: This will delete all activity records',
				),
				array(),
				array(),
			),
			'none'           => array(
				array(
					'type'    => 'none',
					'section' => 'advanced',
					'name'    => 'delete_all_records',
					'desc'    => 'Currently deleting records. Please be patient, this can take a while.',
				),
				array(
					'class="description"',
					'Currently deleting records',
				),
				array(
					'<a ',
					'<input',
				),
				array(),
			),
			'rule_list'      => array(
				array(
					'type'    => 'rule_list',
					'section' => 'exclude',
					'name'    => 'rules',
					'desc'    => 'Create rules to exclude certain kinds of activity from being recorded by Stream.',
				),
				array(
					'stream-exclude-list',
					'Add New Rule',
					'Create rules to exclude certain kinds of activity',
				),
				array(),
				array(
					'Create rules to exclude certain kinds of activity from being recorded by Stream.',
				),
			),
		);
	}

	public function test_rule_list_renders_native_selects_with_roles_and_users() {
		$html = $this->renderer->render_field(
			$this->make_field(
				array(
					'type'    => 'rule_list',
					'section' => 'exclude',
					'name'    => 'rules',
					'desc'    => 'Exclude',
				)
			),
			array(
				'exclude_rules' => array(
					'exclude_row'    => array( 'row1' => '1' ),
					'author_or_role' => array( 'row1' => '1' ),
					'connector'      => array( 'row1' => 'posts' ),
					'context'        => array( 'row1' => 'post' ),
					'action'         => array( 'row1' => 'updated' ),
					'ip_address'     => array( 'row1' => '203.0.113.10' ),
				),
			),
			'wp_stream'
		);

		$this->assertStringContainsString( '<optgroup label="Roles">', $html );
		$this->assertStringContainsString( '<optgroup label="Users">', $html );
		$this->assertStringContainsString( '>Test Admin</option>', $html );
		$this->assertStringContainsString( '>WP-CLI</option>', $html );
		$this->assertStringContainsString( 'value="1"  selected="selected"', $html );
		$this->assertStringContainsString( 'value="203.0.113.10"', $html );
		$this->assertStringNotContainsString( 'select2', $html );
	}

	public function test_rule_list_labels_missing_user_as_not_available() {
		Functions\when( 'get_userdata' )->justReturn( false );

		$html = $this->renderer->render_field(
			$this->make_field(
				array(
					'type'    => 'rule_list',
					'section' => 'exclude',
					'name'    => 'rules',
					'desc'    => 'Exclude',
				)
			),
			array(
				'exclude_rules' => array(
					'exclude_row'    => array( 'row1' => '1' ),
					'author_or_role' => array( 'row1' => '42' ),
				),
			),
			'wp_stream'
		);

		$this->assertStringContainsString( 'value="42"', $html );
		$this->assertStringContainsString( '>N/A</option>', $html );
	}

	public function test_rule_list_over_cap_renders_user_combobox_with_roles() {
		$this->picker_state = array(
			'ajax'  => true,
			'items' => array(),
		);

		$html = $this->renderer->render_field(
			$this->make_field(
				array(
					'type'    => 'rule_list',
					'section' => 'exclude',
					'name'    => 'rules',
					'desc'    => 'Exclude',
				)
			),
			array(
				'exclude_rules' => array(
					'exclude_row'    => array( 'row1' => '1' ),
					'author_or_role' => array( 'row1' => '42' ),
				),
			),
			'wp_stream'
		);

		$this->assertStringContainsString( 'stream-user-combobox', $html );
		// Roles render inside the combobox (single control), not as a select.
		$this->assertStringContainsString( 'data-role-options=', $html );
		$this->assertStringContainsString( '"value":"administrator"', $html );
		$this->assertStringNotContainsString( 'stream-user-combobox__roles', $html );
		$this->assertStringContainsString( 'value="42"', $html );
		$this->assertStringNotContainsString( '<optgroup label="Users">', $html );
	}

	public function test_rule_list_over_cap_seeds_stored_role_label() {
		$this->picker_state = array(
			'ajax'  => true,
			'items' => array(),
		);

		$html = $this->renderer->render_field(
			$this->make_field(
				array(
					'type'    => 'rule_list',
					'section' => 'exclude',
					'name'    => 'rules',
					'desc'    => 'Exclude',
				)
			),
			array(
				'exclude_rules' => array(
					'exclude_row'    => array( 'row1' => '1' ),
					'author_or_role' => array( 'row1' => 'editor' ),
				),
			),
			'wp_stream'
		);

		// Stored role: hidden value keeps the slug, label seeds the combobox.
		$this->assertStringContainsString( 'value="editor"', $html );
		$this->assertStringContainsString( 'data-selected-label="Editor"', $html );
	}

	public function test_render_field_returns_empty_when_required_keys_missing() {
		$this->assertSame( '', $this->renderer->render_field( array( 'type' => 'text' ), array(), 'wp_stream' ) );
	}
}
