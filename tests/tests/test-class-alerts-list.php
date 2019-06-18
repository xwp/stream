<?php
namespace WP_Stream;
/**
 * Class Test_Alerts_List
 * @package WP_Stream
 * @group   alerts
 */

class Test_Alerts_List extends WP_StreamTestCase {

	function test_construct() {
		$alerts_list = new Alerts_List( $this->plugin );
		$this->assertNotEmpty( $alerts_list->plugin );
	}

	function test_suppress_bulk_actions() {
		$alerts_list      = new Alerts_List( $this->plugin );
		$actions          = array(
			'edit'          => 'edit',
			'testaction'    => 'testaction',
			'anotheraction' => 'anotheraction',
		);
		$expected_actions = array(
			'testaction'    => 'testaction',
			'anotheraction' => 'anotheraction',
		);
		$filtered_actions = $alerts_list->suppress_bulk_actions( $actions );
		$this->assertEquals( $expected_actions, $filtered_actions );
	}

	function test_suppress_quick_edit() {
		$alerts_list      = new Alerts_List( $this->plugin );
		$actions          = array(
			'edit'                 => 'edit',
			'view'                 => 'view',
			'trash'                => 'trash',
			'inline hide-if-no-js' => 'inline hide-if-no-js',
		);
		$post             = $this->factory->post->create( array( 'post_type' => 'post' ) );
		$filtered_actions = $alerts_list->suppress_quick_edit( $actions );
		$this->assertEquals( $actions, $filtered_actions );
	}

	function test_custom_column_actions() {
		$alerts_list      = new Alerts_List( $this->plugin );
		$custom_column_actions_html =  $alerts_list->custom_column_actions( 42 );
		$this->assertNotEmpty( $custom_column_actions_html );
	}
	function test_display_custom_quick_edit() {
		$alerts_list      = new Alerts_List( $this->plugin );
		ob_start();
		$alerts_list->display_custom_quick_edit();
		$output = ob_get_clean();
		$this->assertEmpty( $output );
	}
	function test_enqueue_scripts() {
		$alerts_list      = new Alerts_List( $this->plugin );
		global $current_screen;
		$current_screen->id = 'edit-wp_stream_alerts';
		$alerts_list->enqueue_scripts( '' );
		$this->assertTrue( wp_script_is( 'wp-stream-alerts-list-js', 'registered' ) );
	}
}
