<?php
/**
 * Tests stream main class
 *
 * @author X-Team
 * @author Jonathan Bardo <jonathan.bardo@x-team.com>
 *
 * @ToDo Test all remaining function of the admin class
 * - erase_stream_records
 * - purge_schedule_setup
 */
class Test_WP_Stream_Admin extends WP_StreamTestCase {

	//Name of the class to test
	const CLASSNAME = 'WP_Stream_Admin';

	/**
	 * Change to admin page
	 */
	public function setUp() {
		parent::setUp();

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

		//Load the class manually like if we were in the admin
		require_once WP_STREAM_INC_DIR . 'admin.php';
		call_user_func( array( self::CLASSNAME, 'load' ) );
	}

	/**
	 * Check constructor composition
	 *
	 * @return void
	 */
	public function test_constructor() {
		//Check all constructor action
		$test_actions = array(
			array( 'admin_menu', self::CLASSNAME, 'register_menu' ),
			array( 'admin_enqueue_scripts', self::CLASSNAME, 'admin_enqueue_scripts' ),
			array( 'admin_enqueue_scripts', self::CLASSNAME, 'admin_menu_css' ),
			array( 'wp_ajax_wp_stream_reset', self::CLASSNAME, 'wp_ajax_reset' ),
			array( 'init', self::CLASSNAME, 'purge_schedule_setup' ),
			array( 'stream_auto_purge', self::CLASSNAME, 'purge_scheduled_action' ),
		);

		$this->do_action_validation( $test_actions );

		//Check all constuctor filters
		$test_actions = array(
			array( 'user_has_cap', self::CLASSNAME, '_filter_user_caps' ),
			array( 'role_has_cap', self::CLASSNAME, '_filter_role_caps' ),
			array( 'plugin_action_links', self::CLASSNAME, 'plugin_action_links' ),
		);

		$this->do_filter_validation( $test_actions );
	}

	/**
	 * Check is menu is present in menu array
	 */
	public function test_register_menu(){
		do_action( 'admin_menu' );
		global $menu, $submenu;

		//Check main menu
		$this->assertTrue( in_array( 'wp_stream', reset( $menu ) ) );

		//Check submenu
		$this->assertTrue( in_array( 'wp_stream_settings', $submenu['wp_stream'][1] ) );
	}

	/**
	 * Check if script are enqueued properly if on wp_stream page
	 */
	public function test_admin_enqueue_script() {
		set_current_screen( 'toplevel_page_wp_stream' );
		do_action( 'admin_enqueue_scripts', 'toplevel_page_wp_stream' );

		//Check if chosen is present
		$this->assertTrue( wp_script_is( 'select2', 'enqueued' ), 'jQuery Select2 js is not enqueud' );
		$this->assertTrue( wp_style_is( 'select2', 'enqueued' ), 'jQuery Chosen css is not enqueud' );
		$this->assertTrue( wp_script_is( 'jquery-core', 'registered' ), 'jQuery is not enqueud' );
		$this->assertTrue( wp_script_is( 'wp-stream-admin', 'enqueued' ), 'wp-stream-admin is not enqueud' );
		$this->assertTrue( wp_style_is( 'wp-stream-admin', 'enqueued' ), 'wp-stream-admin is not enqueud' );

		//Check wp localise script
		$localize_data = $GLOBALS['wp_scripts']->get_data( 'wp-stream-admin', 'data' );
		$this->assertTrue( strrpos( $localize_data, 'i18n' ) > 0 );
	}

	/**
	 * Check the output of the plugin action links function
	 */
	public function test_plugin_action_links() {
		$filter_output = apply_filters( 'plugin_action_links', array(), plugin_basename( WP_STREAM_DIR . 'stream.php' ) );
		$this->assertTrue( strrpos( $filter_output[0], '/wp-admin/admin.php?page=wp_stream_settings' ) >= 0 );
	}

}
