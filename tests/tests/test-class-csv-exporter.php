<?php
namespace WP_Stream;

class Test_CSV_Exporters extends WP_StreamTestCase {

	public function test_csv_export() {
		$exporter = new Exporter_CSV;

		$array = array( 'key' => 'value' );
		$this->expectOutputString( join( ',', $array ) );
		$exporter->output_file( $array );
	}

}
