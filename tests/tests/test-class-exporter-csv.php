<?php
namespace WP_Stream;

class Test_Exporter_CSV extends WP_StreamTestCase {

	public function test_csv_export() {
		$exporter = new Exporter_CSV;

		$array = array( array( 'key', 'value' ) );
		$this->expectOutputString( join( ',', $array[0] ) . "\n" );
		$exporter->output_file( $array );
	}

}
