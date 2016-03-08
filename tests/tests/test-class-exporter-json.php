<?php
namespace WP_Stream;

class Test_Exporter_JSON extends WP_StreamTestCase {

	/**
	 * Test JSON exporter output
	 */
	public function test_json_export() {
		$exporter = new Exporter_JSON;

		$array = array( 'key' => 'value' );
		$this->expectOutputString( '{\"key\":\"value\"}' );
		$exporter->output_file( $array, array() );
	}
}
