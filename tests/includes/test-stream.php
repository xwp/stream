<?php
/**
 * Tests stream main class
 *
 * @author X-Team
 * @author Jonathan Bardo <jonathan.bardo@x-team.com>
 */
class Test_Stream extends WP_UnitTestCase {

	/**
	 * Holds the plugin base class
	 *
	 * @return void
	 */
	private $plugin;

	/**
	 * PHP unit setup function
	 *
	 * @return void
	 */
	function setUp() {
		parent::setUp();
		$this->plugin = $GLOBALS['wp_stream'];

		$administrator_id = $this->factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_admin',
				'email'      => 'test@stream.com',
			)
		);
		wp_set_current_user( $administrator_id );
	}

	/**
	 * Make sure the plugin is initialized with it's global variable
	 *
	 * @return void
	 */
	public function test_plugin_initialized() {
		$this->assertFalse( null == $this->plugin );
	}

	/**
	 * Check constructor composition
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_constructor() {
		$this->assertTrue( defined( 'WP_STREAM_DIR' ), 'WP_STREAM_DIR is not defined' );
		$this->assertTrue( defined( 'WP_STREAM_URL' ), 'WP_STREAM_URL is not defined' );
		$this->assertTrue( defined( 'WP_STREAM_INC_DIR' ), 'WP_STREAM_INC_DIR is not defined' );
		$this->assertTrue( defined( 'WP_STREAM_CLASS_DIR' ), 'WP_STREAM_CLASS_DIR is not defined' );
	}

}
