<?php
namespace WP_Stream;

class Test_Exporter_CSV extends WP_StreamTestCase {

	/**
	 * Test CSV exporter output
	 */
	public function test_csv_export() {
		$exporter = new Exporter_CSV;

		$array   = array( array( 'key' => 'value', 'key2' => 'value2' ) );
		$columns = array( 'key' => 'Key', 'key2' => 'Key2' );

		$this->expectOutputString( "Key,Key2\nvalue,value2\n" );
		$exporter->output_file( $array, $columns );
	}

}
