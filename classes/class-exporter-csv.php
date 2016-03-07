<?php
namespace WP_Stream;

class Exporter_CSV extends Exporter{

	public $name = 'csv';

	public function output_file( $data ) {

		header( 'Content-type: text/csv' );
		header( 'Content-Disposition: attachment; filename="stream.csv"' );

		$output = '';
		foreach ( $data as $row ) {
			$output .= join( ',', $row ) . "\n";
		}

		die( $output ); // @codingStandardsIgnoreLine text-only output

	}
}
