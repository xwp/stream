<?php
/**
 * Test: WP Stream Widgets Connector.
 *
 * Context: Widgets.
 * Actions: Added, Removed, Moved, Created, Deleted, Deactivated, Reactivated, Updated, Sorted.
 *
 * @author WP Stream
 * @author Michele Ong <michele@wpstream.com>
 */
class Test_WP_Stream_Connector_Widgets extends WP_StreamTestCase {

	/**
	 * Widget Context: Action Added
	 */
	public function test_action_widget_added() {
		$widget = 'text';
		$context = 'stream_unit_test-1';
		$time = time(true);
		$widget_id = $widget . '-' . $time;

		// Register a sidebar
		register_sidebar( array( 'id' => $context ) );

		$active_widgets = get_option( 'sidebars_widgets' );

		$active_widgets[$context][0] = $widget_id;

		// Add the widget
		update_option('sidebars_widgets', $active_widgets);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option_sidebars_widgets' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'meta'      => array( 'widget_id' => $widget_id ),
				'context'   => $context,
				'action'    => 'added'
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Widget Context: Action Removed
	 */
	public function test_action_widget_removed() {
		$widget = 'text';
		$context = 'stream_unit_test-1';
		$time = time(true);
		$widget_id = $widget . '-' . $time;

		// Register a sidebar
		register_sidebar( array( 'id' => $context ) );

		$active_widgets = get_option( 'sidebars_widgets' );

		$active_widgets[$context][0] = $widget_id;

		// Add the widget
		update_option('sidebars_widgets', $active_widgets);

		unset($active_widgets[$context][0]);

		// Remove the widget
		update_option('sidebars_widgets', $active_widgets);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option_sidebars_widgets' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'meta'      => array( 'widget_id' => $widget_id ),
				'context'   => $context,
				'action'    => 'removed'
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Widget Context: Action Moved
	 */
	public function test_action_widget_moved() {
		$widget = 'text';
		$context = 'stream_unit_test-1';
		$time = time(true);
		$widget_id = $widget . '-' . $time;

		// Register sidebars
		register_sidebar( array( 'id' => $context ) );
		register_sidebar( array( 'id' => $context . '-2' ) );

		$active_widgets = get_option( 'sidebars_widgets' );

		$active_widgets[$context][0] = $widget_id;
		$active_widgets[$context . '-2'] = array();

		// Add the widget
		update_option('sidebars_widgets', $active_widgets);

		$active_widgets[$context . '-2'][0] = $widget_id;
		$active_widgets[$context] = array();

		// Move the widget
		update_option('sidebars_widgets', $active_widgets);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option_sidebars_widgets' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'context'   => $context . '-2',
				'action'    => 'moved',
				'meta'      => array( 'widget_id' => $widget_id, 'old_sidebar_id' => $context )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Widget Context: Action Created
	 */
	public function test_action_widget_created() {
		$this->markTestSkipped('Investigate self::$verbose_widget_created_deleted_actions');

		$widget = 'text';
		$context = 'stream_unit_test-1';
		$time = time(true);
		$widget_id = $widget . '-' . $time;

		// Add the widget
		$widgets = get_option( 'widget_text' );
		$widgets['_multiwidget'] = 1;
		$widgets[$time] = array( 'title' => 'Test Title', 'text' => 'Test Text' );

		update_option( 'widget_text', $widgets );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'context'   => $context,
				'action'    => 'created',
				'meta'      => array( 'widget_id' => $widget_id )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Widget Context: Action Deleted
	 */
	public function test_action_widget_deleted() {
		$this->markTestSkipped('Investigate self::$verbose_widget_created_deleted_actions');

		$widget = 'text';
		$context = 'stream_unit_test-1';
		$time = time(true);
		$widget_id = $widget . '-' . $time;

		// Add the widget
		$widgets = get_option( 'widget_text' );
		$widgets['_multiwidget'] = 1;
		$widgets[$time] = array( 'title' => 'Test Title', 'text' => 'Test Text' );

		update_option( 'widget_text', $widgets );

		// Remove the widget
		$widgets = get_option( 'widget_text' );
		$widgets['_multiwidget'] = 1;
		unset($widgets[$time]);

		update_option( 'widget_text', $widgets );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'context'   => $context,
				'action'    => 'deleted',
				'meta'      => array( 'widget_id' => $widget_id )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Widget Context: Action Deactivated
	 */
	public function test_action_widget_deactivated() {
		$widget = 'text';
		$context = 'stream_unit_test-1';
		$time = time(true);
		$widget_id = $widget . '-' . $time;

		// Register sidebar
		register_sidebar( array( 'id' => $context ) );

		$active_widgets = get_option( 'sidebars_widgets' );

		$active_widgets[$context][0] = $widget_id;

		// Add the widget
		update_option('sidebars_widgets', $active_widgets);

		$active_widgets['wp_inactive_widgets'][0] = $widget_id;
		$active_widgets[$context] = array();

		// Move the widget
		update_option('sidebars_widgets', $active_widgets);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option_sidebars_widgets' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'context'   => 'wp_inactive_widgets',
				'action'    => 'deactivated',
				'meta'      => array( 'widget_id' => $widget_id, 'sidebar_id' => $context )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Widget Context: Action Reactivated
	 */
	public function test_action_widget_reactivated() {
		$widget = 'text';
		$context = 'stream_unit_test-1';
		$time = time(true);
		$widget_id = $widget . '-' . $time;

		// Register sidebar
		register_sidebar( array( 'id' => $context ) );

		$active_widgets = get_option( 'sidebars_widgets' );

		$active_widgets['wp_inactive_widgets'][0] = $widget_id;

		// Add the widget
		update_option('sidebars_widgets', $active_widgets);

		$active_widgets[$context][0] = $widget_id;
		$active_widgets['wp_inactive_widgets'] = array();

		// Move the widget
		update_option('sidebars_widgets', $active_widgets);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option_sidebars_widgets' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'context'   => $context,
				'action'    => 'reactivated',
				'meta'      => array( 'widget_id' => $widget_id, 'sidebar_id' => $context )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Widget Context: Action Updated
	 */
	public function test_action_widget_updated() {
		$widget = 'text';
		$context = 'stream_unit_test-1';
		$time = time(true);
		$widget_id = $widget . '-' . $time;

		// Register sidebar
		register_sidebar( array( 'id' => $context ) );

		$active_widgets = get_option( 'sidebars_widgets' );

		$active_widgets[$context][] = $widget_id;

		// Add the widget
		update_option('sidebars_widgets', $active_widgets);

		$widgets = get_option( 'widget_text' );
		$widgets['_multiwidget'] = 1;
		$widgets[$time] = array( 'title' => 'Test Title', 'text' => 'Test Text' );

		update_option( 'widget_text', $widgets );

		// Update the widget
		$widgets = get_option( 'widget_text' );
		$widgets['_multiwidget'] = 1;
		$widgets[$time] = array( 'title' => 'Test Title', 'text' => 'Test Text 2' );

		update_site_option( 'widget_text', $widgets );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_updated_option' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'context'   => $context,
				'action'    => 'updated',
				'meta'      => array( 'widget_id' => $widget_id, 'sidebar_id' => $context )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}
}
