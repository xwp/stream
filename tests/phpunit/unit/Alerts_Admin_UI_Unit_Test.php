<?php
namespace WP_Stream;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

class Alerts_Admin_UI_Unit_Test extends TestCase {
	/**
	 * Admin UI under test.
	 *
	 * @var Alerts_Admin_UI
	 */
	protected $admin_ui;

	/**
	 * Plugin mock with an Alerts registry of types.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	protected function set_up() {
		parent::set_up();
		$this->stubTranslationFunctions();
		$this->stubEscapeFunctions();

		Functions\when( 'wp_list_pluck' )->alias( array( self::class, 'wp_list_pluck_stub' ) );
		Functions\when( 'wp_parse_args' )->alias( array( self::class, 'wp_parse_args_stub' ) );
		Functions\when( 'selected' )->alias( array( self::class, 'selected_stub' ) );
		Functions\when( 'wp_nonce_field' )->alias( array( self::class, 'wp_nonce_field_stub' ) );

		$this->plugin         = Mockery::mock( Plugin::class );
		$alerts               = Mockery::mock( Alerts::class );
		$alerts->alert_types  = $this->sample_alert_types();
		$this->plugin->alerts = $alerts;

		$this->admin_ui = new Alerts_Admin_UI( $this->plugin );
	}

	/**
	 * Merge defaults like wp_parse_args.
	 *
	 * @param array $args     Incoming args.
	 * @param array $defaults Defaults.
	 * @return array
	 */
	public static function wp_parse_args_stub( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
	}

	/**
	 * selected() stand-in; third arg false returns the attribute.
	 *
	 * @param mixed $selected Compared value.
	 * @param mixed $current  Current value.
	 * @param bool  $echo     Unused.
	 * @return string
	 */
	public static function selected_stub( $selected, $current = true, $echo = true ) {
		unset( $echo );
		return ( $selected === $current ) ? ' selected="selected"' : '';
	}

	/**
	 * Echo a nonce field marker.
	 *
	 * @param string $action  Action name.
	 * @param string $name    Field name.
	 * @return void
	 */
	public static function wp_nonce_field_stub( $action, $name ) {
		unset( $action );
		printf( '<input type="hidden" name="%s" />', esc_attr( $name ) );
	}

	/**
	 * WordPress wp_list_pluck stand-in.
	 *
	 * @param array       $input_list List of objects or arrays.
	 * @param string      $field      Field to pluck.
	 * @param string|null $index_key  Optional index field.
	 * @return array
	 */
	public static function wp_list_pluck_stub( $input_list, $field, $index_key = null ) {
		$result = array();
		foreach ( $input_list as $key => $item ) {
			$value = is_object( $item ) ? $item->{$field} : $item[ $field ];
			if ( null !== $index_key ) {
				$idx            = is_object( $item ) ? $item->{$index_key} : $item[ $index_key ];
				$result[ $idx ] = $value;
			} else {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Sample alert types with slug/name, matching production objects.
	 *
	 * @return array<string, object>
	 */
	protected function sample_alert_types() {
		$none            = new \stdClass();
		$none->slug      = 'none';
		$none->name      = 'Do Nothing';
		$highlight       = new \stdClass();
		$highlight->slug = 'highlight';
		$highlight->name = 'Highlight';
		$email           = new \stdClass();
		$email->slug     = 'email';
		$email->name     = 'Email';

		return array(
			'none'      => $none,
			'highlight' => $highlight,
			'email'     => $email,
		);
	}

	/**
	 * Maps type slug to display name.
	 *
	 * @param string $slug     Type slug.
	 * @param string $expected Expected display name.
	 */
	#[DataProvider( 'data_notification_slugs' )]
	public function test_get_notification_values_maps_slug_to_name( $slug, $expected ) {
		$values = $this->admin_ui->get_notification_values();

		$this->assertArrayHasKey( $slug, $values );
		$this->assertSame( $expected, $values[ $slug ] );
	}

	/**
	 * Slug → name pairs for get_notification_values.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_notification_slugs() {
		return array(
			'none'      => array( 'none', 'Do Nothing' ),
			'highlight' => array( 'highlight', 'Highlight' ),
			'email'     => array( 'email', 'Email' ),
		);
	}

	public function test_get_notification_values_count_matches_alert_types() {
		$values = $this->admin_ui->get_notification_values();

		$this->assertCount( count( $this->plugin->alerts->alert_types ), $values );
	}

	public function test_display_status_box_outputs_enabled_and_disabled() {
		ob_start();
		$this->admin_ui->display_status_box();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wp_stream_alert_status', $output );
		$this->assertStringContainsString( 'wp_stream_enabled', $output );
		$this->assertStringContainsString( 'wp_stream_disabled', $output );
	}

	public function test_display_submit_box_returns_early_when_post_empty() {
		ob_start();
		$this->admin_ui->display_submit_box( null );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_display_notification_box_without_post_renders_type_select() {
		$none       = Mockery::mock();
		$none->slug = 'none';
		$none->name = 'Do Nothing';
		$none->shouldReceive( 'display_fields' )->once()->with( array() );
		$this->plugin->alerts->alert_types['none'] = $none;

		ob_start();
		$this->admin_ui->display_notification_box();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wp_stream_alert_type', $output );
		$this->assertStringContainsString( 'wp_stream_alert_type_form', $output );
		$this->assertStringContainsString( 'Do Nothing', $output );
	}

	public function test_display_triggers_box_without_post_prints_nonce() {
		ob_start();
		$this->admin_ui->display_triggers_box();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wp_stream_alerts_nonce', $output );
		$this->assertStringContainsString( 'Alert me when', $output );
	}

	public function test_change_menu_link_url_returns_false_when_stream_menu_missing() {
		$GLOBALS['submenu'] = array();

		$this->assertFalse( $this->admin_ui->change_menu_link_url() );
	}

	public function test_change_menu_link_url_rewrites_alerts_item_to_first_site() {
		$GLOBALS['submenu'] = array(
			'wp_stream' => array(
				array( 'Alerts', 'manage_options', 'edit.php?post_type=wp_stream_alerts' ),
			),
		);

		Functions\when( 'wp_stream_get_sites' )->justReturn(
			array(
				(object) array(
					'blog_id' => '7',
				),
			)
		);
		Functions\when( 'get_admin_url' )->alias(
			static function ( $site_id, $page ) {
				return 'https://example.test/site-' . $site_id . '/' . $page;
			}
		);

		$this->assertTrue( $this->admin_ui->change_menu_link_url() );
		$this->assertSame(
			'https://example.test/site-7/edit.php?post_type=wp_stream_alerts',
			$GLOBALS['submenu']['wp_stream'][0][2]
		);
	}

	public function test_change_menu_link_url_falls_back_to_site_one_when_sites_empty() {
		$GLOBALS['submenu'] = array(
			'wp_stream' => array(
				array( 'Alerts', 'manage_options', 'edit.php?post_type=wp_stream_alerts' ),
			),
		);

		Functions\when( 'wp_stream_get_sites' )->justReturn( array() );
		Functions\when( 'get_admin_url' )->alias(
			static function ( $site_id, $page ) {
				return 'https://example.test/site-' . $site_id . '/' . $page;
			}
		);

		$this->assertTrue( $this->admin_ui->change_menu_link_url() );
		$this->assertSame(
			'https://example.test/site-1/edit.php?post_type=wp_stream_alerts',
			$GLOBALS['submenu']['wp_stream'][0][2]
		);
	}

	public function test_register_scripts_skips_enqueue_off_alerts_screen() {
		Functions\when( 'get_current_screen' )->justReturn(
			(object) array(
				'id' => 'edit-post',
			)
		);
		$this->plugin->shouldReceive( 'enqueue_asset' )->never();

		$this->admin_ui->register_scripts();
	}

	public function test_register_scripts_enqueues_alerts_asset_on_list_screen() {
		Functions\when( 'get_current_screen' )->justReturn(
			(object) array(
				'id' => 'edit-wp_stream_alerts',
			)
		);
		Functions\when( 'wp_create_nonce' )->justReturn( 'stream-nonce' );
		$this->plugin->shouldReceive( 'with_select2' )->once()->andReturn( array( 'select2' ) );
		$this->plugin->shouldReceive( 'enqueue_asset' )
			->once()
			->with(
				'alerts',
				array( array( 'select2' ), 'inline-edit-post' ),
				Mockery::on(
					static function ( $l10n ) {
						return isset( $l10n['getActionsNonce'] ) && 'stream-nonce' === $l10n['getActionsNonce'];
					}
				)
			);

		$this->admin_ui->register_scripts();
	}

	public function test_change_alert_action_links_unchanged_when_post_missing() {
		Functions\when( 'get_post' )->justReturn( null );
		$record             = Mockery::mock( Record::class );
		$record->object_id  = 12;
		$links              = array(
			'View' => 'https://example.test/view',
		);

		$this->assertSame( $links, $this->admin_ui->change_alert_action_links( $links, $record ) );
	}

	public function test_change_alert_action_links_unchanged_for_other_post_types() {
		Functions\when( 'get_post' )->justReturn(
			(object) array(
				'ID'          => 4,
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);
		$record            = Mockery::mock( Record::class );
		$record->object_id = 4;
		$links             = array(
			'View' => 'https://example.test/view',
		);

		$this->assertSame( $links, $this->admin_ui->change_alert_action_links( $links, $record ) );
	}
}
