<?php
/**
 * Test: WP Stream Menus Connector.
 *
 * Contexts: Menu
 * Actions: Created, Updated, Deleted, Assigned, Unassigned.
 *
 * @author WP Stream
 * @author Michele Ong <michele@wpstream.com>
 */
class Test_WP_Stream_Connector_Menus extends WP_StreamTestCase {

	/**
	 * Menu Context: Action Create
	 */
	public function test_action_menu_create() {
		$time = time();
		$menu_name = 'Menu ' . $time;

		// Create a menu
		$menu_id = wp_create_nav_menu($menu_name);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_wp_create_nav_menu' ) );

		$menu = wp_get_nav_menu_object($menu_id);

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $menu_id,
				'connector' => 'menus',
				'context'   => $menu->slug,
				'action'    => 'created',
				'meta'      => array( 'name' => $menu_name )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Menu Context: Action Update
	 */
	public function test_action_menu_update() {
		$time = time();
		$menu_name = 'Menu ' . $time;

		// Create a menu
		$menu_id = wp_create_nav_menu($menu_name);
		$menu = wp_get_nav_menu_object($menu_id);

		// Update the menu
		wp_update_nav_menu_object($menu_id, array('menu-name' => $menu_name, 'description' => 'Test Description'));

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_wp_update_nav_menu' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $menu_id,
				'connector' => 'menus',
				'context'   => $menu->slug,
				'action'    => 'updated',
				'meta'      => array( 'name' => $menu_name )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Menu Context: Action Delete
	 */
	public function test_action_menu_delete() {
		$time = time();
		$menu_name = 'Menu ' . $time;

		// Create a menu
		$menu_id = wp_create_nav_menu($menu_name);
		$menu = wp_get_nav_menu_object($menu_id);

		// Delete the menu
		wp_delete_nav_menu($menu_id);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_delete_nav_menu' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $menu_id,
				'connector' => 'menus',
				'context'   => $menu->slug,
				'action'    => 'deleted',
				'meta'      => array( 'name' => $menu_name )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Menu Context: Action Assigned
	 */
	public function test_action_menu_assigned() {
		$this->markTestSkipped('Update of theme_mods is not working.');
		$time = time();
		$menu_name = 'Menu ' . $time;

		// Create a menu
		$menu_id = wp_create_nav_menu($menu_name);
		$menu = wp_get_nav_menu_object($menu_id);

		// Assign the menu
		$new_theme_navs = array();
		$new_theme_locations = get_registered_nav_menus();
		foreach ($new_theme_locations as $location => $description ) {
			// We setting same nav menus for each theme location
			$new_theme_navs[$location] = $menu_id;
		};
		set_theme_mod('nav_menu_locations', $new_theme_navs);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option_theme_mods' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $menu_id,
				'connector' => 'menus',
				'context'   => $menu->slug,
				'action'    => 'assigned',
				'meta'      => array( 'name' => $menu_name )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Menu Context: Action Unassigned
	 */
	public function test_action_menu_unassigned() {
		$this->markTestSkipped('Update of theme_mods is not working.');
		$time = time();
		$menu_name = 'Menu ' . $time;

		// Create a menu
		$menu_id = wp_create_nav_menu($menu_name);
		$menu = wp_get_nav_menu_object($menu_id);

		// Assign the menu
		$new_theme_navs = array( 'primary' => $menu_id );
		set_theme_mod('nav_menu_locations', $new_theme_navs);

		// Unassign the menu
		set_theme_mod('nav_menu_locations', array());

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option_theme_mods' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $menu_id,
				'connector' => 'menus',
				'context'   => $menu->slug,
				'action'    => 'unassigned',
				'meta'      => array( 'name' => $menu_name )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}
}
