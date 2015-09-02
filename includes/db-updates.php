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
function wp_stream_update_auto_300( $db_version, $current_version ) {
	global $wpdb;

	// Get only the author_meta values that are double-serialized
	$plugin = wp_stream_get_instance();
	$prefix = $plugin->install->table_prefix;

	$wpdb->query( "RENAME TABLE {$prefix}stream TO {$prefix}stream_tmp, {$prefix}stream_context TO {$prefix}stream_context_tmp" );

	$plugin->install->install( $current_version );

	$stream_entries = $wpdb->get_results( "SELECT * FROM {$prefix}stream_tmp" );

	foreach ( $stream_entries as $entry ) {
		$context = $wpdb->get_row( "SELECT * FROM {$prefix}stream_context_tmp WHERE record_id = {$entry->ID} LIMIT 1" );

		$new_entry = array(
			'site_id' => $entry->site_id,
			'blog_id' => $entry->blog_id,
			'user_id' => $entry->author,
			'user_role' => $entry->author_role,
			'summary' => $entry->summary,
			'created' => $entry->created,
			'connector' => $context->connector,
			'context' => $context->context,
			'action' => $context->action,
			'ip' => $entry->ip,
		);

		if ( $entry->object_id && 0 !== $entry->object_id ) {
			$new_entry['object_id'] = $entry->object_id;
		}

		$wpdb->insert( $prefix . 'stream', $new_entry );
	}

	$wpdb->query( "DROP TABLE {$prefix}stream_tmp, {$prefix}stream_context_tmp" );

	return $current_version;
}
