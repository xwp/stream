<?php
/**
 * Test: WP Stream Settings Connector.
 *
 * Contexts: Settings, General, Writing, Reading, Discussion, Media, Permalinks, Network, Stream, Stream Network,
 * Stream Defaults, Custom Background, Custom Header.
 * Actions: Updated.
 *
 * @author WP Stream
 * @author Michele Ong <michele@wpstream.com>
 */
class Test_WP_Stream_Connector_Settings extends WP_StreamTestCase {

	/**
	 * Setting Context: Action Update
	 *
	 * This depends on $whitelist_options which is only loaded via the UI.
	 * This will test all actions in that list and will not separate the contexts for Settings, General, Writing,
	 * Reading, Discussion, Media.
	 */
	public function test_action_setting_update() {
		$time = time();
		$blogname = 'WordPress Blog ' . $time;

		// Update option
		update_site_option('blogname', $blogname);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_updated_option' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'context'   => 'settings',
				'action'    => 'updated',
				'meta'      => array( 'old_value' => $blogname )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Permalink Setting Context: Action Update
	 */
	public function test_action_setting_permalink_update() {
		$time = time();
		$category_base = 'category-'. $time;

		// Update option
		update_site_option('category_base', $category_base);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_updated_option' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'context'   => 'permalink',
				'action'    => 'updated',
				'meta'      => array( 'old_value' => $category_base )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Network Setting Context: Action Update
	 */
	public function test_action_setting_network_update() {
		$this->markTestSkipped('Only works for multisite');

		$time = time();
		$site_name = 'Site Name '. $time;

		// Update option
		update_site_option('site_name', $site_name);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_updated_option' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'context'   => 'network',
				'action'    => 'updated',
				'meta'      => array( 'old_value' => $site_name )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Permalink Setting Context: Action Update
	 */
	public function test_action_setting_stream_update() {
		$this->markTestSkipped('TBC');
		$original = wp_stream_query(
			array(
				'context'   => 'wp_stream',
				'action'    => 'updated',
				'option_key' => 'general_private_feeds'
			)
		);

		// Update option
		$wp_stream = get_option('wp_stream', array());
		$wp_stream['general_private_feeds'] = "1";
		update_option('wp_stream', $wp_stream);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_updated_option' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'context'   => 'wp_stream',
				'action'    => 'updated',
				'option_key' => 'general_private_feeds'
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Custom Background Setting Context: Action Update
	 */
	public function test_action_setting_custom_background_update() {
		$this->markTestSkipped('TBC');
	}

	/**
	 * Custom Header Setting Context: Action Update
	 */
	public function test_action_setting_custom_header_update() {
		$this->markTestSkipped('TBC');
	}
}
