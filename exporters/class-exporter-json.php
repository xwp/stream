<?php
/**
 * JSON Exporter
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Exporter_JSON
 */
class Exporter_JSON extends Exporter {
	/**
	 * Exporter name
	 *
	 * @var string
	 */
	public $name = 'JSON';

	/**
	 * Exporter slug
	 *
	 * @var string
	 */
	public $slug = 'json';

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

		if ( function_exists( 'wp_json_encode' ) ) {
			$output = wp_json_encode( $data );
		} else {
			$output = json_encode( $data ); // @codingStandardsIgnoreLine fallback to discouraged function
		}

		echo $output; // @codingStandardsIgnoreLine text-only output

		if ( ! defined( 'WP_STREAM_TESTS' ) || ( defined( 'WP_STREAM_TESTS' ) && ! WP_STREAM_TESTS ) ) {
			exit;
		}
	}
}
