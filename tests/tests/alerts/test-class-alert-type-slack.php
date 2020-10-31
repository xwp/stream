<?php
/**
 * Tests for "Slack" alert type.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_Alert_Type_Slack extends WP_StreamTestCase {
	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		$post_connector = new Connector_Posts();
		$post_connector->register();

		$this->alerts   = new Alerts( $this->plugin );
	}

	public function test_alert() {
		// Set alert fields
		try {
			$_POST['wp_stream_alerts_nonce']    = wp_create_nonce( 'save_alert' );
			$_POST['wp_stream_trigger_author']  = 1;
			$_POST['wp_stream_trigger_context'] = 'posts-post';
			$_POST['wp_stream_trigger_action']  = 'created';
			$_POST['wp_stream_alert_type']      = 'none';
			$_POST['wp_stream_alert_status']    = 'wp_stream_enabled';

			// Slack alert meta.
			$_POST['wp_stream_slack_webhook']  = '';
			$_POST['wp_stream_slack_channel']  = '';
			$_POST['wp_stream_slack_username'] = '';
			$_POST['wp_stream_slack_icon']     = '';

			// Simulate saving an alert.
			$this->_handleAjax( 'save_new_alert' );
		} catch ( \WPAjaxDieContinueException $e ) {
			$exception = $e;
		}

		$response = json_decode( $this->_last_response );
		$this->assertInternalType( 'object', $response );
		$this->assertObjectHasAttribute( 'success', $response );
		$this->assertTrue( $response->success );

		// Use filter callback to the 'wp_mail' as a place to run assertions.
		$asserted = false;
		add_filter(
			'http_request_args',
			function( $parsed_args ) use ( &$asserted ) {
				$this->assertNotEmpty( $parsed_args['body'] );
				$body = wp_json_decode( $parsed_args['body'] );

				error_log( print_r( $body, true ) );
				$this->assertNotEmpty( $body );
				$asserted = true;
			}
		);

		// Trigger alert.
		wp_set_current_user( 1 );
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test post',
				'post_content' => 'Lorem ipsum dolor...',
				'post_status'  => 'publish',
				'post_author'  => 1
			)
		);

		// Confirm assertion were run.
		$this->assertTrue( $asserted );
	}
}
