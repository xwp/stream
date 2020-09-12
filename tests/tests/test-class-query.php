<?php
/**
 * Tests Query class functions.
 */

namespace WP_Stream;

class Test_Query extends WP_StreamTestCase {
	/**
	 * Runs before each test.
	 */
	public function setUp() {
		parent::setUp();

		$this->user_id = self::factory()->user->create();
		wp_set_current_user( $this->user_id );
	}

	public function test_query() {
		// Create log.
		$result = $this->plugin->log->log(
			'test_connector',
			'Test query',
			array(),
			0,
			'settings',
			'test',
			$this->user_id
		);

		$this->assertNotEmpty( $result );

		// Test some basic parameters.
		$records = $this->plugin->db->query( array( 'connector' => 'test_connector' ) );
		$this->assertEquals( $result, $records[0]->ID );

		$records = $this->plugin->db->query( array( 'context' => 'settings' ) );
		$this->assertEquals( $result, $records[0]->ID );

		// Test date parameters.
		$records = $this->plugin->db->query( array( 'date' => get_the_date( 'Y-m-d') ) );
		$this->assertEquals( $result, $records[0]->ID );

		$records = $this->plugin->db->query( array( 'date_before' => current_time( 'mysql' ) ) );
		$this->assertEquals( $result, $records[0]->ID );

		// Test __not_in parameters.
		$records = $this->plugin->db->query( array( 'ID__not_in' => $result ) );
		$ids = array_map(
			function ( $record ) {
				return $record->ID;
			},
			$records
		);
		$this->assertFalse( in_array( $result, $ids, true ) );

		// Test __in parameters.
		$records = $this->plugin->db->query( array( 'ID__in' => $result ) );
		$this->assertEquals( $result, $records[0]->ID );

		// Test order parameters.
		$result2 = $this->plugin->log->log(
			'test_connector',
			'Test query two',
			array(),
			0,
			'settings',
			'test',
			$this->user_id
		);

		$records = $this->plugin->db->query(
			array(
				'ID__in'  => array( $result, $result2 ),
				'order'   => 'DESC',
				'orderby' => 'created',
			)
		);
		$ids = array_map(
			function ( $record ) {
				return $record->ID;
			},
			$records
		);
		$this->assertEquals( $ids, array( $result, $result2 ) );
	}
}
