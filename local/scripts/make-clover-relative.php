<?php
/**
 * Helper script to get around PHPunit generating code
 * coverage reports with file paths relative to the Docker containers.
 */

$clover_xml_file = $argv[1]; // First argument must be path to the XML coverage file.
$clover_root_dir = sprintf( '%s/', getcwd() );

$xml = file_get_contents( $clover_xml_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, used only during dev.

file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents, used only during dev.
	$clover_xml_file,
	str_replace( $clover_root_dir, '', $xml ) // Make coverage path relative to the project root.
);
