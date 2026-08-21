<?php
/**
 * Tests for Install constructor-time migrations (XWPENG-22).
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_Install extends WP_StreamTestCase {
	/**
	 * Install instance present before a test mutates $plugin->install.
	 *
	 * @var Install|null
	 */
	protected $saved_install;

	public function setUp(): void {
		parent::setUp();

		$this->saved_install = $this->plugin->install;
	}

	public function tearDown(): void {
		$GLOBALS['wp_stream'] = $this->plugin;

		if ( $this->saved_install instanceof Install ) {
			$this->plugin->install = $this->saved_install;
		}

		update_site_option( 'wp_stream_db', Plugin::VERSION );

		parent::tearDown();
	}

	/**
	 * Simulate Plugin::__construct() before $GLOBALS['wp_stream'] and $plugin->install are assigned.
	 */
	protected function simulate_construction_window() {
		$this->plugin->install = null;
		unset( $GLOBALS['wp_stream'] );
	}

	/**
	 * Leftover wp_stream_db < 3.0.8 must not fatal during Install construction.
	 */
	public function test_check_with_leftover_db_version_3_0_7_during_construction_does_not_fatal() {
		update_site_option( 'wp_stream_db', '3.0.7' );
		$this->simulate_construction_window();

		$install = new Install( $this->plugin );

		$this->assertInstanceOf( Install::class, $install );
		$this->assertSame( Plugin::VERSION, get_site_option( 'wp_stream_db' ) );
		$this->assert_stream_schema_present();
	}

	/**
	 * Stream tables exist and user_role is present (dbDelta / 3.0.8 column-width path).
	 */
	protected function assert_stream_schema_present() {
		global $wpdb;

		$this->assertNotEmpty( $wpdb->stream );
		$this->assertNotEmpty(
			$wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->stream ) )
			)
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$columns = $wpdb->get_col( "DESCRIBE {$wpdb->stream}", 0 );
		$this->assertContains( 'user_role', $columns );
	}
}
