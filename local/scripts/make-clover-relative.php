<?php
/**
 * Helper script to get around PHPunit generating code
 * coverage reports with file paths relative to the Docker containers.
 */

$clover_xml_file = $argv[1]; // First argument must be path to the XML coverage file.
$clover_root_dir = sprintf( '%s/', getcwd() );

$xml = file_get_contents( $clover_xml_file );

file_put_contents(
	$clover_xml_file,
	str_replace( $clover_root_dir, '', $xml ) // Make coverage path relative to the project root.
);
