<?php
/**
 * Version 3.0.0
 *
 * Update from 1.4.9
 *
 * @param string $db_version
 * @param string $current_version
 *
 * @return string
 */
function wp_stream_update_300( $db_version, $current_version ) {
	global $wpdb;

	// Get only the author_meta values that are double-serialized
	$plugin = wp_stream_get_instance();
	$prefix = $plugin->install->table_prefix;

	return $current_version;
}
