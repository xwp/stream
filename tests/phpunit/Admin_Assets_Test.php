<?php
/**
 * Tests for Admin_Assets.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Admin_Assets_Test extends WP_StreamTestCase {

	/**
	 * Holds the admin base class.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Assets collaborator under test.
	 *
	 * @var Admin_Assets
	 */
	protected $assets;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->plugin->admin;
		$this->assertNotEmpty( $this->admin );
		$this->assets = $this->get_admin_collaborator( $this->admin, 'assets' );

		$admin_user_id = \WP_UnitTestCase_Base::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $admin_user_id );

		// Populate screen_id so enqueue tests can target a Stream screen hook.
		global $menu;
		$menu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'admin_menu' );
	}

	public function test_admin_enqueue_scripts() {
		global $wp_scripts;

		$this->assertNotEmpty( $this->admin->menu->screen_id['main'] );

		// Non-Stream screen
		$this->assets->admin_enqueue_scripts( 'edit.php' );

		$this->assertFalse( wp_script_is( 'wp-stream-admin' ), 'wp-stream-admin script is not enqueued' );
		$this->assertFalse( wp_style_is( 'wp-stream-admin' ), 'wp-stream-admin style is not enqueued' );

		$this->assertTrue( wp_script_is( 'wp-stream-global' ), 'wp-stream-global script is enqueued' );

		$this->assertStringContainsString(
			'bulk_actions',
			$wp_scripts->get_inline_script_data( 'wp-stream-global', 'before' ),
		);

		// Stream screen
		$this->assets->admin_enqueue_scripts( $this->plugin->admin->menu->screen_id['main'] );

		$this->assertTrue( wp_style_is( 'wp-stream-admin' ), 'wp-stream-admin style is enqueued' );

		$this->assertTrue( wp_script_is( 'wp-stream-select2' ), 'wp-stream-select2 script is enqueued' );
		$this->assertTrue( wp_script_is( 'wp-stream-select2-en' ), 'wp-stream-select2-en script is enqueued' );
		$this->assertTrue( wp_script_is( 'wp-stream-jquery-timeago' ), 'wp-stream-jquery-timeago script is enqueued' );
		$this->assertTrue( wp_script_is( 'wp-stream-jquery-timeago-en' ), 'wp-stream-jquery-timeago-en script is enqueued' );

		$this->assertTrue( wp_script_is( 'wp-stream-admin' ), 'wp-stream-admin script is enqueued' );
		$this->assertTrue( wp_script_is( 'wp-stream-live-updates' ), 'wp-stream-live-updates script is enqueued' );

		$this->assertStringContainsString(
			'i18n',
			$wp_scripts->get_inline_script_data( 'wp-stream-admin', 'before' ),
		);

		$this->assertStringContainsString(
			'current_screen',
			$wp_scripts->get_inline_script_data( 'wp-stream-live-updates', 'before' ),
		);
		$this->assertStringContainsString(
			$this->plugin->admin->menu->screen_id['main'],
			$wp_scripts->get_inline_script_data( 'wp-stream-live-updates', 'before' ),
		);
	}

	public function test_is_stream_screen() {
		$this->assertFalse( $this->assets->is_stream_screen() );

		if ( ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}
		$_GET['page'] = $this->admin->records_page_slug;

		$this->assertTrue( $this->assets->is_stream_screen() );
	}

	public function test_admin_body_class() {
		// Make this the Stream screen
		if ( ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}
		$_GET['page'] = $this->admin->records_page_slug;

		$classes            = 'sit-down-calmy take-a-stress-pill think-things-over';
		$admin_body_classes = $this->assets->admin_body_class( $classes );

		$this->assertStringContainsString( 'think-things-over ', $admin_body_classes );
		$this->assertStringContainsString( $this->admin->admin_body_class . ' ', $admin_body_classes );
		$this->assertStringContainsString( $this->admin->records_page_slug . ' ', $admin_body_classes );
	}

	public function test_admin_menu_css() {
		global $wp_styles;

		$this->assets->admin_menu_css();

		$dependency = $wp_styles->registered['wp-admin'];
		$this->assertArrayHasKey( 'after', $dependency->extra );
		$this->assertNotEmpty( $dependency->extra['after'] );
		$this->assertStringContainsString( "body.{$this->admin->admin_body_class}", $dependency->extra['after'][0] );
	}

}
