<?php
namespace WP_Stream;

class Test_Exporters extends WP_StreamTestCase {

	public function test_json_export() {
		$exporter = new Exporter_JSON;

		$array = array( 'key' => 'value' );
		$this->expectOutputString( json_encode( $array ) );
		$exporter->output_file( $array );
	}

}
