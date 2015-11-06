<?php
namespace WP_Stream;

class Test_DB extends WP_StreamTestCase {
	/**
	 * Holds the db base class
	 *
	 * @var DB
	 */
	protected $db;

	public function setUp() {
		parent::setUp();

		$this->db = $this->plugin->db;
		$this->assertNotEmpty( $this->db );
	}

	public function test_construct() {
		$this->assertNotEmpty( $this->db->plugin );
		$this->assertInstanceOf( '\WP_Stream\Plugin', $this->db->plugin );

		$this->assertNotEmpty( $this->db->query );
		$this->assertInstanceOf( '\WP_Stream\Query', $this->db->query );

		$this->assertNotEmpty( $this->db->table );
		$this->assertNotEmpty( $this->db->table_meta );

		global $wpdb;
		$this->assertEquals( $this->db->table, $wpdb->stream );
		$this->assertEquals( $this->db->table_meta, $wpdb->streammeta );
		$this->assertEquals( $this->db->table_meta, $wpdb->recordmeta );
	}

	public function test_get_table_names() {
		$table_names = $this->db->get_table_names();

		$this->assertNotEmpty( $table_names );
		$this->assertInternalType( 'array', $table_names );
		$this->assertEquals( array( $this->db->table, $this->db->table_meta ), $table_names );
	}

	/*
	 * Also tests the insert_meta method
	 */
	public function test_insert() {
		$dummy_data         = $this->dummy_stream_data();
		$dummy_data['meta'] = $this->dummy_meta_data();

		$stream_id = $this->db->insert( $dummy_data );

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

	public function test_existing_records() {
		$summaries = $this->db->existing_records( 'summary' );
		$this->assertNotEmpty( $summaries );

		global $wpdb;
		$wpdb->suppress_errors( true );

		$bad_column = $this->db->existing_records( 'daisy' );
		$this->assertEmpty( $bad_column );

		$wpdb->suppress_errors( false );
	}

	private function dummy_stream_data() {
		return array(
			'object_id' => 9,
			'site_id' => '1',
			'blog_id' => get_current_blog_id(),
			'user_id' => '1',
			'user_role' => 'administrator',
			'created' => date( 'Y-m-d h:i:s' ),
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
