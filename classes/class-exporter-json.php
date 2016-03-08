<?php
namespace WP_Stream;

class Exporter_JSON extends Exporter {

	/**
	 * Exporter slug
	 *
	 * @var string
	 */
	public $name = 'json';

	/**
	 * Outputs JSON data for download
	 *
	 * @param array $data Array of data to output.
	 * @param array $columns Column names included in data set.
	 * @return void
	 */
	public function output_file( $data, $columns ) {

		if ( ! defined( 'WP_STREAM_TESTS' ) || ( defined( 'WP_STREAM_TESTS' ) && ! WP_STREAM_TESTS ) ) {
			header( 'Content-type: text/json' );
			header( 'Content-Disposition: attachment; filename="stream.json"' );
		}

		$output = wp_json_encode( $data );
		echo $output; // @codingStandardsIgnoreLine text-only output

		if ( ! defined( 'WP_STREAM_TESTS' ) || ( defined( 'WP_STREAM_TESTS' ) && ! WP_STREAM_TESTS ) ) {
			exit;
		}
	}
}
