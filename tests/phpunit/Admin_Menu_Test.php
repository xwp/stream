<?php
/**
 * Tests for Admin_Menu.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Admin_Menu_Test extends WP_StreamTestCase {

	/**
	 * Holds the admin base class.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Menu collaborator under test.
	 *
	 * @var Admin_Menu
	 */
	protected $menu;

	/**
	 * Holds the administrator id.
	 *
	 * @var int
	 */
	protected $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->plugin->admin;
		$this->assertNotEmpty( $this->admin );
		$this->menu = $this->get_admin_collaborator( $this->admin, 'menu' );

		$this->admin_user_id = \WP_UnitTestCase_Base::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_admin_menu',
				'email'      => 'test-menu@land.com',
			)
		);
		wp_set_current_user( $this->admin_user_id );
	}

	public function tearDown(): void {
		parent::tear_down();

		if ( is_multisite() ) {
			wpmu_delete_user( $this->admin_user_id );
		} else {
			wp_delete_user( $this->admin_user_id );
		}
	}

	public function test_register_menu() {
		global $menu;
		$menu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		do_action( 'admin_menu' );

		$this->assertNotEmpty( $this->menu->screen_id );
		$this->assertNotEmpty( $this->menu->screen_id['main'] );
		$this->assertNotEmpty( $this->menu->screen_id['settings'] );
	}

	/**
	 * Network registers network_admin_menu → Admin_Menu::register_menu().
	 */
	public function test_network_admin_menu_uses_menu_collaborator() {
		global $menu;
		$menu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->menu->screen_id = array();
		$this->menu->register_menu();

		$this->assertNotEmpty( $this->menu->screen_id['main'] );
		$this->assertNotEmpty( $this->menu->screen_id['settings'] );
	}

}
