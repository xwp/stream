<?php
/**
 * Tests for "die" alert type.
 *
 * @package WP_Stream
 */
namespace WP_Stream;

class Test_Alert_Type_Die extends WP_StreamTestCase {
	public function test_alert() {
		$record_id = $this->plugin->log->log(
			'post',
			'test message',
			array( 'test_key' => 'test_key' ),
			200,
			'test',
			'tested',
			null
		);

		$record_arr = $this->plugin->db->query( array( 'ID' => $record_id ) )[0];

		$alert_type = new Alert_Type_Die( $this->plugin );

		try {
			ob_start();
			$alert_type->alert( $record_id, $record_arr, null );
		} catch ( Die_Exception $e ) {
			$expected = '<pre>' . print_r( $record_arr, true ) . '</pre>'; // @codingStandardsIgnoreLine
			$dump = ob_get_clean();

			$this->assertEquals( $expected, $dump );
			$this->assertEquals( 'You have been notified!', $e->getMessage() );
		}
	}
}
