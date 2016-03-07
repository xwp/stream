<?php
namespace WP_Stream;

abstract class Exporter {

	/**
	 * Exporter slug
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Output formatted data for download
	 *
	 * @param array $data Array of data to output.
	 * @return void
	 */
	public abstract function output_file( $data );

}
