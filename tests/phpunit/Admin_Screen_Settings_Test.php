<?php
/**
 * Tests for Admin_Screen_Settings.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Admin_Screen_Settings_Test extends WP_StreamTestCase {

	/**
	 * Holds the admin base class.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Settings screen collaborator under test.
	 *
	 * @var Admin_Screen_Settings
	 */
	protected $settings;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->plugin->admin;
		$this->assertNotEmpty( $this->admin );
		$this->settings = $this->admin->settings;
	}

	public function test_render_settings_page() {
		ob_start();
		$this->settings->render_settings_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( '<div class="wrap">', $html );

		global $wp_scripts;

		$this->assertArrayHasKey( 'wp-stream-settings', $wp_scripts->registered );
	}
}
