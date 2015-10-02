<?php
namespace WP_Stream;

class Test_Admin extends WP_StreamTestCase {
	/**
	 * Holds the admin base class
	 *
	 * @var Admin
	 */
	protected $admin;

	public function setUp() {
		parent::setUp();

		$this->admin = $this->plugin->admin;
		$this->assertNotEmpty( $this->admin );

		//Add admin user to test caps
		// We need to change user to verify editing option as admin or editor
		$administrator_id = $this->factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_admin',
				'email'      => 'test@land.com',
			)
		);
		wp_set_current_user( $administrator_id );
	}

	public function test_construct() {
		$this->assertNotEmpty( $this->admin->plugin );
		$this->assertInstanceOf( '\WP_Stream\Plugin', $this->admin->plugin );

		$this->assertTrue( function_exists( 'is_plugin_active_for_network' ) );

		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) && ! is_network_admin() ) {
			$this->assertTrue( $this->admin->disable_access );
		} else {
			$this->assertFalse( $this->admin->disable_access );
		}
	}

	public function test_init() {
		$this->admin->init();
		$this->assertNotEmpty( $this->admin->network );
		$this->assertNotEmpty( $this->admin->live_update );

		$this->assertInstanceOf( '\WP_Stream\Network', $this->admin->network );
		$this->assertInstanceOf( '\WP_Stream\Live_Update', $this->admin->live_update );
	}

	public function test_prepare_admin_notices() {
		// Test no notices
		$this->admin->notices = array();
		$this->admin->prepare_admin_notices();
		$this->assertEmpty( $this->admin->notices );

		// Test settings reset notice
		$_GET['message'] = 'settings_reset';
		$this->admin->prepare_admin_notices();
		$this->assertNotEmpty( $this->admin->notices );

		// Prevent output
		$this->admin->notices = array();
	}

	public function test_notice() {
		// Start with nothing
		$this->admin->notices = array();
		$this->assertEmpty( $this->admin->notices );

		$message  = 'Affirmative, Dave. I read you.';
		$is_error = false;

		$this->admin->notice( $message, $is_error );
		$this->assertNotEmpty( $this->admin->notices );
		ob_start();
		$this->admin->admin_notices();
		$notice = ob_get_clean();

		$this->assertContains( $message, $notice );
		$this->assertContains( 'updated', $notice );
		$this->assertNotContains( 'error', $notice );

		// Clear notices and start again
		$this->admin->notices = array();
		$this->assertEmpty( $this->admin->notices );

		$is_error = true;

		$this->admin->notice( $message, $is_error );
		$this->assertNotEmpty( $this->admin->notices );
		ob_start();
		$this->admin->admin_notices();
		$notice = ob_get_clean();

		$this->assertContains( $message, $notice );
		$this->assertContains( 'error', $notice );
		$this->assertNotContains( 'updated', $notice );

		// Prevent output
		$this->admin->notices = array();
	}

	public function test_admin_notices() {
		$allowed_html    = '<progress class="migration" max="100"></progress>';
		$disallowed_html = '<iframe></iframe>';
		$this->admin->notices = array(
			array(
				'message'  => "I'm sorry, Dave. I'm afraid I can't do that. $disallowed_html",
				'is_error' => false,
			),
			array(
				'message'  => "This mission is too important for me to allow you to jeopardize it. $allowed_html",
				'is_error' => false,
			),
		);

		ob_start();
		$this->admin->admin_notices();
		$notices = ob_get_clean();

		$this->assertContains( $allowed_html, $notices );
		$this->assertNotContains( $disallowed_html, $notices );
		$this->assertContains( str_replace( $disallowed_html, '', $this->admin->notices[0]['message'] ), $notices );
		$this->assertContains( wpautop( $this->admin->notices[1]['message'] ), $notices );

		// Prevent output
		$this->admin->notices = array();
	}

	public function test_register_menu() {
		global $menu;
		$menu = array(); //phpcs override okay

		do_action( 'admin_menu' );

		$this->assertNotEmpty( $this->admin->screen_id );
		$this->assertNotEmpty( $this->admin->screen_id['main'] );
		$this->assertNotEmpty( $this->admin->screen_id['settings'] );
	}

	public function test_admin_enqueue_scripts() {
		global $wp_styles;
		global $wp_scripts;

		// Non-Stream screen
		$this->admin->admin_enqueue_scripts( 'edit.php' );

		$this->assertArrayNotHasKey( 'wp-stream-admin', $wp_scripts->registered );

		$this->assertArrayHasKey( 'wp-stream-admin', $wp_styles->registered );
		$this->assertArrayHasKey( 'wp-stream-global', $wp_scripts->registered );

		$dependency = $wp_scripts->registered['wp-stream-global'];
		$this->assertArrayHasKey( 'data', $dependency->extra );
		$this->assertNotFalse( strpos( $dependency->extra['data'], 'bulk_actions' ) );

		// Stream screen
		$this->admin->admin_enqueue_scripts( $this->plugin->admin->screen_id['main'] );

		$this->assertArrayHasKey( 'select2', $wp_scripts->registered );
		$this->assertArrayHasKey( 'timeago', $wp_scripts->registered );
		$this->assertArrayHasKey( 'timeago-locale', $wp_scripts->registered );

		$this->assertArrayHasKey( 'wp-stream-admin', $wp_scripts->registered );
		$this->assertArrayHasKey( 'wp-stream-live-updates', $wp_scripts->registered );

		$dependency = $wp_scripts->registered['wp-stream-admin'];
		$this->assertArrayHasKey( 'data', $dependency->extra );
		$this->assertNotFalse( strpos( $dependency->extra['data'], 'wp_stream' ) );

		$dependency = $wp_scripts->registered['wp-stream-live-updates'];
		$this->assertArrayHasKey( 'data', $dependency->extra );
		$this->assertNotFalse( strpos( $dependency->extra['data'], 'wp_stream_live_updates' ) );
		$this->assertNotFalse( strpos( $dependency->extra['data'], $this->plugin->admin->screen_id['main'] ) );
	}

	public function test_is_stream_screen() {
		$this->assertFalse( $this->admin->is_stream_screen() );

		if ( ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}
		$_GET['page'] = $this->admin->records_page_slug;

		$this->assertTrue( $this->admin->is_stream_screen() );
	}

	public function test_admin_body_class() {
		// Make this the Stream screen
		if ( ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}
		$_GET['page'] = $this->admin->records_page_slug;

		$classes = 'sit-down-calmy take-a-stress-pill think-things-over';
		$admin_body_classes = $this->admin->admin_body_class( $classes );

		$this->assertContains( 'think-things-over ', $admin_body_classes );
		$this->assertContains( $this->admin->admin_body_class . ' ', $admin_body_classes );
		$this->assertContains( $this->admin->records_page_slug . ' ', $admin_body_classes );
	}

	public function test_admin_menu_css() {
		global $wp_styles;

		$this->admin->admin_menu_css();

		$this->assertArrayHasKey( 'wp-stream-datepicker', $wp_styles->registered );
		$this->assertArrayHasKey( 'wp-stream-icons', $wp_styles->registered );

		$dependency = $wp_styles->registered['wp-admin'];
		$this->assertArrayHasKey( 'after', $dependency->extra );
		$this->assertNotEmpty( $dependency->extra['after'] );
		$this->assertContains( "#toplevel_page_{$this->admin->records_page_slug}", $dependency->extra['after'][0] );
	}

	/*
	 * Also tests private method erase_stream_records
	 */
	public function test_wp_ajax_reset() {
		$_REQUEST['wp_stream_nonce'] = wp_create_nonce( 'stream_nonce' );

		global $wpdb;

		// Create dummy records
		$stream_data = $this->dummy_stream_data();
		$wpdb->insert( $wpdb->stream, $stream_data );
		$stream_id = $wpdb->insert_id;
		$this->assertNotFalse( $stream_id );

		// Create dummy meta
		$meta_data = $this->dummy_meta_data( $stream_id );
		$wpdb->insert( $wpdb->streammeta, $meta_data );
		$meta_id = $wpdb->insert_id;
		$this->assertNotFalse( $meta_id );

		// Check that records exist
		$stream_result = $wpdb->get_row( "SELECT * FROM {$wpdb->stream} WHERE ID = $stream_id" );
		$this->assertNotEmpty( $stream_result );

		// Check that meta exists
		$meta_result = $wpdb->get_row( "SELECT * FROM {$wpdb->streammeta} WHERE meta_id = $meta_id" );
		$this->assertNotEmpty( $meta_result );

		// Clear records and meta
		$reset = $this->admin->wp_ajax_reset();
		$this->assertTrue( $reset );

		// Check that records have been cleared
		$stream_results = $wpdb->get_results( "SELECT * FROM {$wpdb->stream}" );
		$this->assertEmpty( $stream_results );

		// Check that meta has been cleared
		$meta_results = $wpdb->get_results( "SELECT * FROM {$wpdb->streammeta}" );
		$this->assertEmpty( $meta_results );
	}

	public function test_purge_schedule_setup() {
		wp_clear_scheduled_hook( 'wp_stream_auto_purge' );
		$this->assertFalse( wp_next_scheduled( 'wp_stream_auto_purge' ) );
		$this->admin->purge_schedule_setup();
		$this->assertNotFalse( wp_next_scheduled( 'wp_stream_auto_purge' ) );
	}

	public function test_purge_scheduled_action() {
		// Set the TTL to one day
		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) ) {
			$options = (array) get_site_option( 'wp_stream_network', array() );
			$options['general_records_ttl'] = '1';
			update_site_option( 'wp_stream_network', $options );
		} else {
			$options = (array) get_option( 'wp_stream', array() );
			$options['general_records_ttl'] = '1';
			update_option( 'wp_stream', $options );
		}

		global $wpdb;

		// Create (two day old) dummy records
		$stream_data = $this->dummy_stream_data();
		$stream_data['created'] = date( 'Y-m-d h:i:s', strtotime( '2 days ago' ) );
		$wpdb->insert( $wpdb->stream, $stream_data );
		$stream_id = $wpdb->insert_id;
		$this->assertNotFalse( $stream_id );

		// Create dummy meta
		$meta_data = $this->dummy_meta_data( $stream_id );
		$wpdb->insert( $wpdb->streammeta, $meta_data );
		$meta_id = $wpdb->insert_id;
		$this->assertNotFalse( $meta_id );

		// Purge old records and meta
		$this->admin->purge_scheduled_action();

		// Check if the old records have been cleared
		$stream_results = $wpdb->get_row( "SELECT * FROM {$wpdb->stream} WHERE ID = $stream_id" );
		$this->assertEmpty( $stream_results );

		// Check if the old meta has been cleared
		$meta_results = $wpdb->get_row( "SELECT * FROM {$wpdb->streammeta} WHERE meta_id = $meta_id" );
		$this->assertEmpty( $meta_results );
	}

	public function test_plugin_action_links() {
		$links = array( '<a href="javascript:void(0);">Disconnect</a>' );
		$file  = plugin_basename( $this->plugin->locations['dir'] . 'stream.php' );

		$action_links = $this->admin->plugin_action_links( $links, $file );

		$this->assertContains( 'Disconnect', $action_links[0] );
		$this->assertContains( 'Settings', $action_links[1] );
		$this->assertContains( 'Uninstall', $action_links[2] );
	}

	public function test_render_list_table() {
		$this->admin->register_list_table();

		ob_start();
		$this->admin->render_list_table();
		$html = ob_get_clean();

		$this->assertContains( '<div class="wrap">', $html );
		$this->assertContains( 'record-filter-form', $html );
	}

	public function test_render_settings_page() {
		ob_start();
		$this->admin->render_settings_page();
		$html = ob_get_clean();

		$this->assertContains( '<div class="wrap">', $html );

		global $wp_scripts;

		$this->assertArrayHasKey( 'wp-stream-settings', $wp_scripts->registered );
	}

	public function test_register_list_table() {
		$this->admin->register_list_table();

		$this->assertNotEmpty( $this->admin->list_table );
		$this->assertInstanceOf( '\WP_Stream\List_Table', $this->admin->list_table );
	}

	/*
	 * Also tests private method role_can_view
	 */
	public function test_filter_user_caps() {
		$user = new \WP_User( get_current_user_id() );

		$this->plugin->settings->options['general_role_access'] = array( 'administrator' );
		$this->assertTrue( $user->has_cap( $this->admin->view_cap ) );

		$this->plugin->settings->options['general_role_access'] = array( 'editor' );
		$this->assertFalse( $user->has_cap( $this->admin->view_cap ) );
	}

	/*
	 * Also tests private method role_can_view
	 */
	public function test_filter_role_caps() {
		$role = get_role( 'administrator' );

		$this->plugin->settings->options['general_role_access'] = array( 'administrator' );
		$this->assertTrue( $role->has_cap( $this->admin->view_cap ) );

		$this->plugin->settings->options['general_role_access'] = array( 'editor' );
		$this->assertFalse( $role->has_cap( $this->admin->view_cap ) );
	}

	public function test_ajax_filters() {
		$user = new \WP_User( get_current_user_id() );

		$_GET['filter'] = 'user_id';
		$_GET['q'] = $user->display_name;

		ob_start();
		$this->admin->ajax_filters();
		$json = ob_get_clean();

		$this->assertNotEmpty( $json );
		$data = json_decode( $json );
		$this->assertNotFalse( $data );
		$this->assertNotEmpty( $data );
		$this->assertInternalType( 'array', $data );
	}

	public function test_get_filter_value_by_id() {
		$_POST['filter'] = 'user_id';
		$_POST['id']     = get_current_user_id();

		ob_start();
		$this->admin->get_filter_value_by_id();
		$json = ob_get_clean();

		$this->assertNotEmpty( $json );
		$data = json_decode( $json );
		$this->assertNotFalse( $data );
		$this->assertNotEmpty( $data );
		$this->assertInternalType( 'string', $data );
	}

	public function test_get_users_record_meta() {
		$user_id = get_current_user_id();
		$authors = array(
			$user_id => array(),
		);

		$records = $this->admin->get_users_record_meta( $authors );

		$this->assertArrayHasKey( $user_id, $records );
		$this->assertArrayHasKey( 'text', $records[ $user_id ] );
		$this->assertEquals( 'test_admin', $records[ $user_id ]['text'] );
	}

	public function test_get_user_meta() {
		$key   = 'message_1';
		$value = 'It is dangerous to remain here. You must leave within two days.';
		update_user_meta( get_current_user_id(), $key, $value );
		$this->assertEquals( $this->admin->get_user_meta( get_current_user_id(), $key, true ), $value );
	}

	public function test_update_user_meta() {
		$key   = 'message_2';
		$value = 'I understand. It is important that you believe me. Look behind you.';
		$this->admin->update_user_meta( get_current_user_id(), $key, $value );
		$this->assertEquals( get_user_meta( get_current_user_id(), $key, true ), $value );
	}

	public function test_delete_user_meta() {
		$key   = 'message_3';
		$value = 'I was David Bowman.';

		update_user_meta( get_current_user_id(), $key, $value );
		$this->assertEquals( get_user_meta( get_current_user_id(), $key, true ), $value );

		$this->admin->delete_user_meta( get_current_user_id(), $key );

		$this->assertEmpty( get_user_meta( get_current_user_id(), $key, true ) );
	}

	private function dummy_stream_data() {
		return array(
			'object_id' => null,
			'site_id' => '1',
			'blog_id' => get_current_blog_id(),
			'user_id' => '1',
			'user_role' => 'administrator',
			'created' => null,
			'summary' => '"Hello Dave" plugin activated',
			'ip' => '192.168.0.1',
			'connector' => 'installer',
			'context' => 'plugins',
			'action' => 'activated',
		);
	}

	private function dummy_meta_data( $stream_id ) {
		return array(
			'record_id'  => $stream_id,
			'meta_key'   => 'space_helmet',
			'meta_value' => 'false',
		);
	}
}
