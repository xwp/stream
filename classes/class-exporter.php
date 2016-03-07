<?php
namespace WP_Stream;

abstract class Exporter {

	/**
	 * Exporter slug
	 *
	 * @var string
	 */
	public $name;

	public abstract function output_file( $data );

}
