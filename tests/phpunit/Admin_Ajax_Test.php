<?php
/**
 * Tests for Admin_Ajax.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Admin_Ajax_Test extends WP_StreamTestCase {

	/**
	 * Holds the admin base class.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Ajax collaborator under test.
	 *
	 * @var Admin_Ajax
	 */
	protected $ajax;

	/**
	 * Purge collaborator (used by reset large-table path).
	 *
	 * @var Admin_Purge
	 */
	protected $purge;

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
		$this->ajax  = $this->get_admin_collaborator( $this->admin, 'ajax' );
		$this->purge = $this->get_admin_collaborator( $this->admin, 'purge' );

		$this->admin_user_id = \WP_UnitTestCase_Base::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_admin',
				'email'      => 'test@land.com',
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

	private function dummy_stream_data() {
		return array(
			'object_id' => null,
			'site_id'   => '1',
			'blog_id'   => get_current_blog_id(),
			'user_id'   => '1',
			'user_role' => 'administrator',
			'created'   => gmdate( 'Y-m-d H:i:s' ),
			'summary'   => '"Hello Dave" plugin activated',
			'ip'        => '192.168.0.1',
			'connector' => 'installer',
			'context'   => 'plugins',
			'action'    => 'activated',
		);
	}

	private function dummy_stream_data_other_blog() {
		return array(
			'object_id' => null,
			'site_id'   => '1',
			'blog_id'   => (int) get_current_blog_id() + 1,
			'user_id'   => '1',
			'user_role' => 'administrator',
			'created'   => gmdate( 'Y-m-d H:i:s' ),
			'summary'   => '"Hello Dave" plugin activated',
			'ip'        => '192.168.0.1',
			'connector' => 'installer',
			'context'   => 'plugins',
			'action'    => 'activated',
		);
	}

	private function dummy_meta_data( $stream_id ) {
		return array(
			'record_id'  => $stream_id,
			'meta_key'   => 'space_helmet',
			'meta_value' => 'false',
		);
	}

	/**
	 * Insert N stream rows aged $days_old days, optionally pinned to a blog id.
	 *
	 * @param int      $count    Number of rows to insert.
	 * @param int      $days_old How many days ago `created` should be set to.
	 * @param int|null $blog_id  Optional blog id override.
	 * @return int[] Inserted stream IDs.
	 */
	private function seed_aged_records( int $count, int $days_old, $blog_id = null ): array {
		global $wpdb;
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$row            = $this->dummy_stream_data();
			$row['created'] = gmdate( 'Y-m-d H:i:s', strtotime( $days_old . ' days ago' ) );
			if ( null !== $blog_id ) {
				$row['blog_id'] = $blog_id;
			}
			$wpdb->insert( $wpdb->stream, $row );
			$stream_id = (int) $wpdb->insert_id;
			$ids[]     = $stream_id;
			$wpdb->insert( $wpdb->streammeta, $this->dummy_meta_data( $stream_id ) );
		}
		return $ids;
	}

	/**
	 * Set the records TTL in whichever option applies on this install.
	 *
	 * @param int $days Number of days to retain records for.
	 */
	private function set_records_ttl( int $days ) {
		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) ) {
			$options                        = (array) get_site_option( 'wp_stream_network', array() );
			$options['general_records_ttl'] = (string) $days;
			unset( $options['general_keep_records_indefinitely'] );
			update_site_option( 'wp_stream_network', $options );
		} else {
			$options                        = (array) get_option( 'wp_stream', array() );
			$options['general_records_ttl'] = (string) $days;
			unset( $options['general_keep_records_indefinitely'] );
			update_option( 'wp_stream', $options );
		}
	}

	/**
	 * Also tests private method erase_stream_records
	 */
	public function test_wp_ajax_reset() {
		$_REQUEST['wp_stream_nonce']       = wp_create_nonce( 'stream_nonce' );
		$_REQUEST['wp_stream_nonce_reset'] = wp_create_nonce( 'stream_nonce_reset' );

		global $wpdb;

		// Create dummy records
		$stream_data = $this->dummy_stream_data();
		$wpdb->insert( $wpdb->stream, $stream_data );
		$stream_id = $wpdb->insert_id;
		$this->assertNotFalse( $stream_id );

		// Create dummy meta
		$meta_data = $this->dummy_meta_data( $stream_id );
		$wpdb->insert( $wpdb->streammeta, $meta_data );
		$meta_id = $wpdb->insert_id;
		$this->assertNotFalse( $meta_id );

		// Check that records exist
		$stream_result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->stream} WHERE ID = %d", $stream_id ) );
		$this->assertNotEmpty( $stream_result );

		// Check that meta exists
		$meta_result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->streammeta} WHERE meta_id = %d", $meta_id ) );
		$this->assertNotEmpty( $meta_result );

		// Clear records and meta
		$reset = $this->ajax->wp_ajax_reset();
		$this->assertTrue( $reset );

		// Check that records have been cleared
		$stream_results = $wpdb->get_results( "SELECT * FROM {$wpdb->stream}" );
		$this->assertEmpty( $stream_results );

		// Check that meta has been cleared
		$meta_results = $wpdb->get_results( "SELECT * FROM {$wpdb->streammeta}" );
		$this->assertEmpty( $meta_results );
	}

	/**
	 * Also tests private method erase_stream_records
	 */
	public function test_wp_ajax_reset_large_records_blog() {

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		global $wpdb;

		$_REQUEST['wp_stream_nonce']       = wp_create_nonce( 'stream_nonce' );
		$_REQUEST['wp_stream_nonce_reset'] = wp_create_nonce( 'stream_nonce_reset' );

		add_filter( 'wp_stream_is_large_records_table', '__return_true' );
		add_filter( 'wp_stream_is_network_activated', '__return_false' );

		$stream_data = $this->dummy_stream_data();
		$wpdb->insert( $wpdb->stream, $stream_data );
		$stream_id = $wpdb->insert_id;
		$this->assertNotFalse( $stream_id );

		$meta_data = $this->dummy_meta_data( $stream_id );
		$wpdb->insert( $wpdb->streammeta, $meta_data );
		$meta_id = $wpdb->insert_id;
		$this->assertNotFalse( $meta_id );

		$stream_data_2 = $this->dummy_stream_data_other_blog();
		$wpdb->insert( $wpdb->stream, $stream_data_2 );
		$stream_id_2 = $wpdb->insert_id;
		$this->assertNotFalse( $stream_id_2 );

		$meta_data = $this->dummy_meta_data( $stream_id_2 );
		$wpdb->insert( $wpdb->streammeta, $meta_data );
		$meta_id_2 = $wpdb->insert_id;
		$this->assertNotFalse( $meta_id_2 );

		// Clear records and meta
		$reset = $this->ajax->wp_ajax_reset();
		$this->assertTrue( $reset );

		$current_blog = (int) get_current_blog_id();

		// Assert the scheduled action has been set.
		$this->assertTrue(
			as_has_scheduled_action(
				Admin::ASYNC_DELETION_ACTION
			)
		);

		// Check that records have not been cleared yet.
		$stream_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->stream} WHERE blog_id=%d",
				$current_blog
			)
		);
		$this->assertNotEmpty( $stream_results );

		$this->purge->erase_large_records( 1, 0, $meta_id, $current_blog );

		// Check that records have been cleared.
		$stream_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->stream} WHERE blog_id=%d",
				$current_blog
			)
		);
		$this->assertEmpty( $stream_results );

		// Check that records of the other blog have not been cleared.
		$stream_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->stream} WHERE blog_id=%d",
				$current_blog + 1
			)
		);
		$this->assertNotEmpty( $stream_results );

		// Check that one meta has been cleared
		$meta_results = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->streammeta}" );
		$this->assertEquals( 1, $meta_results );

		remove_filter( 'wp_stream_is_large_records_table', '__return_true' );
		remove_filter( 'wp_stream_is_network_activated', '__return_false' );
	}

	/**
	 * Test Ajax Filters
	 *
	 * @group ajax
	 * @requires PHPUnit 5.7
	 */
	public function test_ajax_filters() {
		$user = new \WP_User( $this->admin_user_id );

		$this->_setRole( 'subscriber' );

		$_POST['filter'] = 'user_id';
		$_POST['q']      = $user->display_name;
		$_POST['nonce']  = wp_create_nonce( 'stream_filters_user_search_nonce' );

		$this->expectException( 'WPAjaxDieStopException' );

		try {
			$this->_handleAjax( 'wp_stream_filters' );
		} catch ( WPAjaxDieStopException $e ) {
			// Do nothing.
		}

		// Check that the exception was thrown.
		$this->assertTrue( isset( $e ) );

		// The output should be a -1 for failure.
		$this->assertEquals( '-1', $e->getMessage() );
		unset( $e );

		$this->_setRole( 'administrator' );

		$this->_handleAjax( 'wp_stream_filters' );
		$json = $this->_last_response;

		$this->assertNotEmpty( $json );
		$data = json_decode( $json );
		$this->assertNotFalse( $data );
		$this->assertNotEmpty( $data );
		$this->assertIsArray( $data );
	}

	public function test_ajax_clean_orphan_meta_schedules_reaper() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['wp_stream_nonce_clean_orphan_meta'] = wp_create_nonce( 'stream_nonce_clean_orphan_meta' );

		$result = $this->ajax->wp_ajax_clean_orphan_meta();
		$this->assertTrue( $result );

		$this->assertNotFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION ),
			'Ajax handler must enqueue the reaper action'
		);

		unset( $_REQUEST['wp_stream_nonce_clean_orphan_meta'] );
	}

	/**
	 * Security boundary: a user without WP_STREAM_SETTINGS_CAPABILITY must
	 * be rejected before the handler reaches the AS enqueue. Mirrors the
	 * capability check used by the reset/erase handlers in this class.
	 *
	 * Uses _handleAjax() so WP_Ajax_UnitTestCase's output-buffer machinery
	 * runs (the handler calls wp_die(), which the testcase die handler
	 * routes through ob_get_clean()); calling the method directly would
	 * leave the buffer state ambiguous and PHPUnit would mark the test risky.
	 *
	 * @throws \WPAjaxDieStopException Thrown by the testcase die handler when
	 *                                 the rejected request triggers wp_die().
	 */
	public function test_ajax_clean_orphan_meta_denies_users_without_settings_cap() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$_REQUEST['wp_stream_nonce_clean_orphan_meta'] = wp_create_nonce( 'stream_nonce_clean_orphan_meta' );

		$this->expectException( \WPAjaxDieStopException::class );

		try {
			$this->_handleAjax( 'wp_stream_clean_orphan_meta' );
		} catch ( \WPAjaxDieStopException $e ) {
			unset( $_REQUEST['wp_stream_nonce_clean_orphan_meta'] );
			$this->assertFalse(
				as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION ),
				'No work must be enqueued for a rejected request'
			);
			throw $e;
		}
	}

	public function test_get_users_record_meta() {
		$user_id = $this->admin_user_id;
		$authors = array(
			$user_id => get_user_by( 'id', $user_id ),
		);

		$records = $this->ajax->get_users_record_meta( $authors );

		$this->assertArrayHasKey( $user_id, $records );
		$this->assertArrayHasKey( 'text', $records[ $user_id ] );
		$this->assertEquals( 'test_admin', $records[ $user_id ]['text'] );
	}
}
