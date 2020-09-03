<?php
/**
 * WP Integration Test w/ User Switching plugin
 *
 * Tests for User Switching connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_WP_Stream_Connector_User_Switching extends WP_StreamTestCase {
	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		// Make partial of Connector_User_Switching class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_User_Switching::class )
			->setMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

	public function test_callback_switch_to_user() {

	}

	public function test_callback_switch_back_user() {

	}

	public function test_callback_switch_off_user() {

	}

	public function test_callback_wp_stream_after_connectors_registration() {

	}
}
