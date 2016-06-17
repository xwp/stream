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

		$this->driver = new DB_Driver_WPDB();
	}

	public function test_construct() {
		$this->assertNotEmpty( $this->driver->table );
		$this->assertNotEmpty( $this->driver->table_meta );

		global $wpdb;
		$this->assertEquals( $this->driver->table, $wpdb->stream );
		$this->assertEquals( $this->driver->table_meta, $wpdb->streammeta );
		$this->assertEquals( $this->driver->table_meta, $wpdb->recordmeta );
	}
}
