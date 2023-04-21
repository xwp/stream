<?php
namespace WP_Stream;

class Test_DB_Driver_WPDB extends WP_StreamTestCase {
	/**
	 * Holds the DB_Driver_WPDB class
	 *
	 * @var DB_Driver_WPDB driver
	 */
	protected $driver;

	public function setUp() {
		parent::setUp();

		$plugin = wp_stream_get_instance();
		$plugin->install->install( $plugin->get_version() );

		$this->driver = $plugin->db->driver;
	}

	public function tear_down()
	{
		parent::tear_down();

		$plugin = wp_stream_get_instance();
		$plugin->install->install( $plugin->get_version() );
	}

	public function test_construct() {
		$this->assertInstanceOf( __NAMESPACE__ . '\DB_Driver_WPDB', $this->driver );

		$this->assertNotEmpty( $this->driver->table );
		$this->assertNotEmpty( $this->driver->table_meta );

		global $wpdb;
		$this->assertEquals( $this->driver->table, $wpdb->stream );
		$this->assertEquals( $this->driver->table_meta, $wpdb->streammeta );
		$this->assertEquals( $this->driver->table_meta, $wpdb->recordmeta );
	}

	/*
	 * Also tests the insert_meta method
	 */
	public function test_insert() {
		$dummy_data         = $this->dummy_stream_data();
		$dummy_data['meta'] = $this->dummy_meta_data();

		$stream_id = $this->driver->insert_record( $dummy_data );

		$this->assertNotFalse( $stream_id );
		$this->assertGreaterThan( 0, $stream_id );

		$this->assertEquals( 0, did_action( 'wp_stream_record_insert_error' ) );
		$this->assertGreaterThan( 0, did_action( 'wp_stream_record_inserted' ) );

		global $wpdb;

		// Check that records exist
		$stream_result = $wpdb->get_row( "SELECT * FROM {$wpdb->stream} WHERE ID = $stream_id", ARRAY_A );
		$this->assertNotEmpty( $stream_result );

		foreach ( $this->dummy_stream_data() as $dummy_key => $dummy_value ) {
			$this->assertArrayHasKey( $dummy_key, $stream_result );
			if ( 'created' === $dummy_key ) {
				// It may take up to two seconds to insert a record, so check the time difference
				$dummy_time  = strtotime( $dummy_value );
				$result_time = strtotime( $stream_result[ $dummy_key ] );
				$this->assertTrue( $dummy_time > 0 );
				$this->assertTrue( $result_time > 0 );
				$this->assertTrue( $result_time - $dummy_time < 2 );
				$this->assertTrue( $result_time - $dummy_time >= -2 );
			} else {
				$this->assertEquals( $dummy_value, $stream_result[ $dummy_key ] );
			}
		}

		// Check that meta exists
		$meta_result = $wpdb->get_results( "SELECT * FROM {$wpdb->streammeta} WHERE record_id = $stream_id", ARRAY_A );
		$this->assertNotEmpty( $meta_result );

		$found_all_keys = true;
		foreach ( $meta_result as $meta_row ) {
			$key   = $meta_row['meta_key'];
			$value = $meta_row['meta_value'];
			if ( ! isset( $dummy_data['meta'][ $key ] ) || $value !== $dummy_data['meta'][ $key ] ) {
				$found_all_keys = false;
			}
		}

		$this->assertTrue( $found_all_keys );
	}

	public function test_get_column_values() {
		$summaries = $this->driver->get_column_values( 'summary' );
		$this->assertNotEmpty( $summaries );

		global $wpdb;
		$wpdb->suppress_errors( true );

		$bad_column = $this->driver->get_column_values( 'daisy' );
		$this->assertEmpty( $bad_column );

		$wpdb->suppress_errors( false );
	}

	public function test_table_names() {
		$table_names = $this->driver->get_table_names();

		$this->assertNotEmpty( $table_names );
		$this->assertInternalType( 'array', $table_names );
		$this->assertEquals( array( $this->driver->table, $this->driver->table_meta ), $table_names );
	}

	// Test that the purge_storage() function removes database records as expected.
	public function test_purge_storage() {
		global $wpdb;

		if ( is_multisite() ) {
			$this->markTestSkipped( 'This test requires single site.' );
		}

		// Test cases override table dropping queries to prevent them
		// from being executed, but we need actual dropping here.
		foreach ( $GLOBALS['wp_filter']['query'] as $priority => $filters ) {
			foreach ( $filters as $filter => $data ) {
				if ( false !== strpos( $filter, '_drop_temporary_table' ) ) {
					unset( $filters[$filter] );
					$GLOBALS['wp_filter']['query'][$priority] = $filters;
				}
			}
		}

		// Ensure that both the stream as well as the stream_meta tables currently exist.
		$this->assertNotEmpty( $this->driver->table );
		$stream_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table}'", ARRAY_A );
		$this->assertNotEmpty( $stream_result );

		$this->assertNotEmpty( $this->driver->table_meta );
		$stream_meta_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table_meta}'", ARRAY_A );
		$this->assertNotEmpty( $stream_meta_result );

		// Trigger purge operation directly.
		$uninstall = $this->driver->purge_storage( wp_stream_get_instance() );
		$uninstall->uninstall();

		// Check that the stream table was deleted.
		$stream_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table}'", ARRAY_A );
		$this->assertEmpty( $stream_result );

		// Check that the stream_meta table was deleted.
		$stream_meta_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table_meta}'", ARRAY_A );
		$this->assertEmpty( $stream_meta_result );
	}

	// Test that the purge_storage() function removes database records as expected.
	public function test_purge_storage_multisite() {
		global $wpdb;

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		// Test cases override table dropping queries to prevent them
		// from being executed, but we need actual dropping here.
		foreach ( $GLOBALS['wp_filter']['query'] as $priority => $filters ) {
			foreach ( $filters as $filter => $data ) {
				if ( false !== strpos( $filter, '_drop_temporary_table' ) ) {
					unset( $filters[$filter] );
					$GLOBALS['wp_filter']['query'][$priority] = $filters;
				}
			}
		}

		$this->assertNotEmpty( $this->driver->table );
		$stream_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table}'", ARRAY_A );
		$this->assertNotEmpty( $stream_result );
		$this->assertNotEmpty( $this->driver->table_meta );
		$stream_meta_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table_meta}'", ARRAY_A );
		$this->assertNotEmpty( $stream_meta_result );

		// Ensure the tables for the current site are not empty.
		$blog_id = get_current_blog_id();
		$stream_ids = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$this->driver->table} WHERE site_id = %d", $blog_id ) );
		$this->assertGreaterThan( 0, count( $stream_ids ) );
		foreach ( $stream_ids as $stream_id ) {
			$stream_meta_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->driver->table_meta} WHERE record_id = %d", $stream_id ) );
			$this->assertGreaterThan( 0, $stream_meta_count );
		}

		// Trigger purge operation directly.
		$uninstall = $this->driver->purge_storage( wp_stream_get_instance() );
		$uninstall->uninstall();

		// On multisite, the tables are not deleted, but the records are.
		$stream_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table}'", ARRAY_A );
		$this->assertNotEmpty( $stream_result );
		$stream_meta_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table_meta}'", ARRAY_A );
		$this->assertNotEmpty( $stream_meta_result );

		$stream_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->driver->table} WHERE site_id = %d", $blog_id ) );
		$this->assertEquals( 0, $stream_count );
		$stream_id_placeholders = implode( ', ', array_fill( 0, count( $stream_ids ), '%d' ) );
		$stream_meta_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->driver->table_meta} WHERE record_id IN ( $stream_id_placeholders )", $stream_ids ) );
		$this->assertEquals( 0, $stream_meta_count );
	}

	// Test that the purge_storage() function can only be called with admin capabilities.
	// TODO: This throws a notice: "Test code or tested code did not (only) close its own output buffers".
	public function test_purge_storage_requires_admin_caps() {
		$uninstall = $this->driver->purge_storage( wp_stream_get_instance() );
		$_REQUEST['nonce'] = wp_create_nonce( 'stream_uninstall_nonce' );

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( 'You don&#039;t have sufficient privileges to do this action.' );
		$uninstall->uninstall();
	}

	// Test that the purge_storage() function removes database records as expected.
	public function test_purge_storage_ajax() {
		global $wpdb;

		if ( is_multisite() ) {
			$this->markTestSkipped( 'This test requires single site.' );
		}

		// Test cases override table dropping queries to prevent them
		// from being executed, but we need actual dropping here.
		foreach ( $GLOBALS['wp_filter']['query'] as $priority => $filters ) {
			foreach ( $filters as $filter => $data ) {
				if ( false !== strpos( $filter, '_drop_temporary_table' ) ) {
					unset( $filters[$filter] );
					$GLOBALS['wp_filter']['query'][$priority] = $filters;
				}
			}
		}

		// Ensure that both the stream as well as the stream_meta tables currently exist.
		$this->assertNotEmpty( $this->driver->table );
		$stream_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table}'", ARRAY_A );
		$this->assertNotEmpty( $stream_result );

		$this->assertNotEmpty( $this->driver->table_meta );
		$stream_meta_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table_meta}'", ARRAY_A );
		$this->assertNotEmpty( $stream_meta_result );

		// Trigger purge operation directly.
		$uninstall = $this->driver->purge_storage( wp_stream_get_instance() );
		$uninstall->uninstall();

		// Check that the stream table was deleted.
		$stream_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table}'", ARRAY_A );
		$this->assertEmpty( $stream_result, sprintf( 'Table %s removed', $this->driver->table ) );

		// Check that the stream_meta table was deleted.
		$stream_meta_result = $wpdb->get_results( "SHOW TABLES LIKE '{$this->driver->table_meta}'", ARRAY_A );
		$this->assertEmpty( $stream_meta_result, sprintf( 'Table %s removed', $this->driver->table_meta ) );
	}

	// Test that the purge_storage() function requires a nonce when triggered via AJAX.
	// TODO: This throws a notice: "Test code or tested code did not (only) close its own output buffers".
	public function test_purge_storage_ajax_without_nonce() {
		$this->driver->purge_storage( wp_stream_get_instance() );

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		do_action( 'wp_ajax_wp_stream_uninstall' );
	}

	// Test that the purge_storage() function requires the correct nonce when triggered via AJAX.
	// TODO: This throws a notice: "Test code or tested code did not (only) close its own output buffers".
	public function test_purge_storage_ajax_with_mismatched_nonce() {
		$this->driver->purge_storage( wp_stream_get_instance() );
		$_REQUEST['nonce'] = wp_create_nonce( 'save_nonce' );

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		do_action( 'wp_ajax_wp_stream_uninstall' );
	}

	private function dummy_stream_data() {
		return array(
				'object_id' => 10,
				'site_id' => '1',
				'blog_id' => get_current_blog_id(),
				'user_id' => '1',
				'user_role' => 'administrator',
				'created' => gmdate( 'Y-m-d h:i:s' ),
				'summary' => '"Hello Dave" plugin activated',
				'ip' => '192.168.0.1',
				'connector' => 'installer',
				'context' => 'plugins',
				'action' => 'activated',
		);
	}

	private function dummy_meta_data() {
		return array(
				'space_helmet' => 'false',
		);
	}
}
