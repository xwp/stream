<?php
namespace WP_Stream;

class Test_Alerts extends WP_StreamTestCase {

	function test_construct() {
		$alerts = new Alerts( $this->plugin );

		$this->assertNotEmpty( $alerts->plugin );
	}

	function test_load_alert_types() {
		$action = new \MockAction();
		add_filter( 'wp_stream_alert_types', array( $action, 'filter' ) );

		$alerts = new Alerts( $this->plugin );

		$this->assertNotEmpty( $alerts->alert_types );
		$this->assertContainsOnlyInstancesOf( 'WP_Stream\Alert_Type', $alerts->alert_types );
		$this->assertArrayHasKey( 'none', $alerts->alert_types );
		$this->assertArrayHasKey( 'highlight', $alerts->alert_types );
		$this->assertArrayHasKey( 'email', $alerts->alert_types );

		$this->assertEquals( 1, $action->get_call_count() );
	}

	function test_load_bad_alert_type() {
		$this->setExpectedException( 'PHPUnit_Framework_Error_Notice' );

		add_filter( 'wp_stream_alert_types', array( $this, 'callback_load_bad_alert_register' ), 10, 1 );
		$alerts = new Alerts( $this->plugin );
		remove_filter( 'wp_stream_alert_types', array( $this, 'callback_load_bad_alert_register' ), 10, 1 );
	}

	function test_load_alert_triggers() {
		$action = new \MockAction();
		add_filter( 'wp_stream_alert_triggers', array( $action, 'filter' ) );

		$alerts = new Alerts( $this->plugin );

		$this->assertNotEmpty( $alerts->alert_triggers );
		$this->assertContainsOnlyInstancesOf( 'WP_Stream\Alert_Trigger', $alerts->alert_triggers );
		$this->assertArrayHasKey( 'author', $alerts->alert_triggers );
		$this->assertArrayHasKey( 'context', $alerts->alert_triggers );
		$this->assertArrayHasKey( 'author', $alerts->alert_triggers );

		$this->assertEquals( 1, $action->get_call_count() );
	}

	function test_load_bad_alert_trigger() {
		$this->setExpectedException( 'PHPUnit_Framework_Error_Notice' );

		add_filter( 'wp_stream_alert_triggers', array( $this, 'callback_load_bad_alert_register' ), 10, 1 );
		$alerts = new Alerts( $this->plugin );
		remove_filter( 'wp_stream_alert_triggers', array( $this, 'callback_load_bad_alert_register' ), 10, 1 );
	}

	function callback_load_bad_alert_register( $classes ) {
		$classes['bad_alert_trigger'] = new \stdClass;
		return $classes;
	}

	function test_is_valid_alert_type() {
		$alerts = new Alerts( $this->plugin );
		$this->assertFalse( $alerts->is_valid_alert_type( new \stdClass ) );
		$this->assertFalse( $alerts->is_valid_alert_type( new Alert_Trigger_Action( $this->plugin ) ) );
	}

	function test_is_valid_alert_trigger() {
		$alerts = new Alerts( $this->plugin );
		$this->assertFalse( $alerts->is_valid_alert_trigger( new \stdClass ) );
		$this->assertFalse( $alerts->is_valid_alert_trigger( new Alert_Type_None( $this->plugin ) ) );
	}

	function test_check_records() {
		$this->markTestIncomplete(
			'This test is incomplete.'
		);
	}

	function test_register_scripts() {
		$this->plugin->admin->admin_enqueue_scripts( '' ); // Register script details.

		$alerts = new Alerts( $this->plugin );

		$alerts->register_scripts( 'post.php' );

		$this->assertTrue( wp_style_is( 'wp-stream-select2', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'wp-stream-alerts', 'enqueued' ) );
	}

	function test_register_post_type() {
		$this->markTestIncomplete(
			'This test is incomplete.'
		);
	}

	function test_filter_update_messages() {
		$alerts   = new Alerts( $this->plugin );
		$messages = $alerts->filter_update_messages( array() );

		$this->assertArrayHasKey( 'wp_stream_alerts', $messages );
		$this->assertNotEmpty( $messages['wp_stream_alerts'] );
	}

	function test_get_alert() {
		$alerts = new Alerts( $this->plugin );

		$data = $this->get_dummy_data();
		$data->ID = 0;
		$original_alert = new Alert( $data, $this->plugin );
		$post_id = $original_alert->save();

		$alert = $alerts->get_alert( $post_id );
		$this->assertEquals( $original_alert, $alert );
	}

	function test_get_alert_blank() {
		$alerts = new Alerts( $this->plugin );
		$alert = $alerts->get_alert();

		$this->assertEmpty( $alert->ID );
		$this->assertEmpty( $alert->date );
		$this->assertEmpty( $alert->author );
		$this->assertEmpty( $alert->alert_type );

		$this->assertEquals( $alert->status, 'wp_stream_disabled' );
		$this->assertEquals( $alert->alert_meta, array() );
	}

	function test_register_menu() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_register_meta_boxes() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_add_meta_boxes() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_filter_parent_file() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_filter_submenu_file() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_display_notification_box() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_load_alerts_settings() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_display_triggers_box() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_display_preview_box() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_display_preview_box_ajax() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_display_submit_box() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_get_notification_values() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}

	function test_save_post_info() {
		$this->markTestIncomplete(
			'This test is incomplete'
		);
	}


	function get_dummy_data() {
		return (object) array(
			'ID'         => 1,
			'date'       => date( 'Y-m-d H:i:s' ),
			'status'     => 'wp_stream_enabled',
			'author'     => '1',
			'alert_type' => 'highlight',
			'alert_meta' => array(
				'trigger_action'	=> 'activated',
				'trigger_author'	=> 'administrator',
				'trigger_context' => 'plugins',
			),
		);
	}
}
