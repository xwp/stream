<?php
namespace WP_Stream;

class Exporter_JSON extends Exporter {

	public $name = 'json';

	public function output_file( $data ) {

		header( 'Content-type: text/json' );
		header( 'Content-Disposition: attachment; filename="stream.json"' );

		$output = wp_json_encode( $data );
		die( $output ); // @codingStandardsIgnoreLine text-only output

	}
}
