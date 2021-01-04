<?php
/**
 * Tests for "highlight" alert type.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_Alert_Type_Highlight extends WP_StreamTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		$post_connector = new Connector_Posts();
		$post_connector->register();

		$this->alert_type = new Alert_Type_Highlight( $this->plugin );
	}

	public function test_alert() {

		// Set alert fields
		try {
			$_POST['wp_stream_alerts_nonce']    = wp_create_nonce( 'save_alert' );
			$_POST['wp_stream_trigger_author']  = 1;
			$_POST['wp_stream_trigger_context'] = 'posts-post';
			$_POST['wp_stream_trigger_action']  = 'created';
			$_POST['wp_stream_alert_type']      = 'highlight';
			$_POST['wp_stream_alert_status']    = 'wp_stream_enabled';

			// Highlight alert meta.
			$_POST['wp_stream_highlight_color'] = 'yellow';

			// Simulate saving an alert.
			$this->_handleAjax( 'save_new_alert' );
		} catch ( \WPAjaxDieContinueException $e ) {
			$exception = $e;
		}

		$response = json_decode( $this->_last_response );
		$this->assertInternalType( 'object', $response );
		$this->assertObjectHasAttribute( 'success', $response );
		$this->assertTrue( $response->success );

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

		// Retrieve record.
		$record = ( $this->plugin->db->query( array( 'object_id' => $post_id ) ) )[0];
		$record = new Record( $record );

		// Retrieve alert trigger meta.
		$record->meta[ Alerts::ALERTS_TRIGGERED_META_KEY ] = $record->get_meta( Alerts::ALERTS_TRIGGERED_META_KEY, true );

		$this->assertTrue(
			in_array(
				'alert-highlight highlight-yellow record-id-' . $record->ID,
				$this->alert_type->post_class( array(), $record ),
				true
			)
		);
	}
}
