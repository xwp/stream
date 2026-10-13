<?php
/**
 * Tests for Install constructor-time migrations (XWPENG-22).
 *
 * @package WP_Stream
 */

namespace WP_Stream;

require_once __DIR__ . '/class-auto-300-wpdb-spy.php';

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
	 * Missing wp_stream_db during construction uses Install::install() (AC 9 clean install).
	 *
	 * An empty string option is the same empty() branch as a missing option.
	 */
	public function test_check_with_empty_db_version_during_construction_installs() {
		delete_site_option( 'wp_stream_db' );
		$this->simulate_construction_window();

		$install = new Install( $this->plugin );

		$this->assertInstanceOf( Install::class, $install );
		$this->assertSame( Plugin::VERSION, get_site_option( 'wp_stream_db' ) );
		$this->assert_stream_schema_present();
	}

	/**
	 * Leftover 3.0.0 skips auto_300 (not < 3.0.0) and uses the same auto_308 dbDelta path as 3.0.7.
	 *
	 * Skipped: wp_stream_db < 3.0.0. wp_stream_update_auto_300() RENAME/DROPs stream and
	 * stream_context and would destroy the PHPUnit schema.
	 */
	public function test_check_with_leftover_db_version_3_0_0_during_construction_does_not_fatal() {
		update_site_option( 'wp_stream_db', '3.0.0' );
		$this->simulate_construction_window();

		$install = new Install( $this->plugin );

		$this->assertInstanceOf( Install::class, $install );
		$this->assertSame( Plugin::VERSION, get_site_option( 'wp_stream_db' ) );
		$this->assert_stream_schema_present();
	}

	/**
	 * The 3.0.0 migration returns false before RENAME when the Install instance is missing.
	 */
	public function test_auto_300_returns_false_before_rename_when_install_is_missing() {
		global $wpdb;

		include_once $this->plugin->locations['inc_dir'] . 'db-updates.php';

		$saved = $wpdb;
		$spy   = new Auto_300_Wpdb_Spy( $wpdb->base_prefix );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- spy replaces wpdb for this call only.
		$GLOBALS['wpdb'] = $spy;

		try {
			$result = \wp_stream_update_auto_300( '3.0.0', '1.4.9', null );

			$this->assertFalse( $result );
			$this->assertSame( array(), $spy->queries );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore the real wpdb.
			$GLOBALS['wpdb'] = $saved;
		}
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
