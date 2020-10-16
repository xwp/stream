<?php
/**
 * Tests for "email" alert type.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_Alert_Type_Email extends WP_StreamTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		$post_connector = new Connector_Posts();
		$post_connector->register();

		$alert_type     = new Alert_Type_Email( $this->plugin );
	}

	public function test_alert() {

		// Set alert fields
		$_REQUEST['wp_stream_alerts_nonce'] = wp_create_nonce( 'save_alert' );
		$_POST['wp_stream_trigger_author']  = 1;
		$_POST['wp_stream_trigger_context'] = 'posts-post';
		$_POST['wp_stream_trigger_action']  = 'created';
		$_POST['wp_stream_alert_type']      = 'email';
		$_POST['wp_stream_alert_status']    = 'wp_stream_enabled';

		// Email alert meta.
		$_POST['wp_stream_email_recipient'] = 'admin@example.com';
		$_POST['wp_stream_email_subject']   = 'Test email';

		// Simulate saving an alert.
		$alert_id = $this->plugin->alerts->save_new_alert( false );

		// Set assertion callback
		add_action(
			'wp_mail',
			function( $mail_props ) {
				$this->assertEquals( 'admin@example.com', $mail_props['to'] );
				$this->assertEquals( 'Test email', $mail_props['subject'] );
				$this->assertContains( 'A Stream Alert was triggered on Test Blog', $mail_props['message'] );
			}
		);

		// Trigger alert.
		wp_set_current_user( 1 );
		wp_insert_post(
			array(
				'post_title'   => 'Test post',
				'post_content' => 'Lorem ipsum dolor...',
				'post_status'  => 'publish',
				'post_author'  => 1
			)
		);

		//$this->assertGreaterThan( 0, did_action( 'wp_mail' ) );
	}
}
