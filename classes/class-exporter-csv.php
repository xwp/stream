<?php
namespace WP_Stream;

class Exporter_CSV extends Exporter{

	/**
	 * Exporter slug
	 *
	 * @var string
	 */
	public $name = 'csv';

	/**
	 * Outputs CSV data for download
	 *
	 * @param array $data Array of data to output.
	 * @return void
	 */
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
