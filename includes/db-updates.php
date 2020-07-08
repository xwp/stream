<?php
/**
 * Defines DB migrations.
 *
 * @package WP_Stream
 */

/**
 * Version 3.0.8
 *
 * Force update for older versions to call \dbdelta in install() method to fix column widths.
 *
 * @param string $db_version       New database version.
 * @param string $current_version  Current database version.
 *
 * @return string
 */
function wp_stream_update_auto_308( $db_version, $current_version ) {
	$plugin = wp_stream_get_instance();
	$plugin->install->install( $current_version );

	return $current_version;
}

/**
 * Version 3.0.2
 *
 * @param string $db_version       New database version.
 * @param string $current_version  Current database version.
 *
 * @return string
 */
function wp_stream_update_302( $db_version, $current_version ) {
	global $wpdb;

	$stream_entries = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}stream" );
	foreach ( $stream_entries as $entry ) {
		$class = 'Connector_' . $entry->context;
		if ( class_exists( $class ) ) {
			$connector = new $class();
			$wpdb->update(
				$wpdb->base_prefix . 'stream',
				array(
					'connector' => $connector->name,
				),
				array(
					'ID' => $entry->ID,
				)
			);
		} else {
			$wpdb->update(
				$wpdb->base_prefix . 'stream',
				array(
					'connector' => strtolower( $entry->connector ),
				),
				array(
					'ID' => $entry->ID,
				)
			);
		}
	}

	return $current_version;
}

/**
 * Version 3.0.0
 *
 * Update from 1.4.9
 *
 * @param string $db_version       New database version.
 * @param string $current_version  Current database version.
 *
 * @return string
 */
function wp_stream_update_auto_300( $db_version, $current_version ) {
	global $wpdb;

	// Get only the author_meta values that are double-serialized.
	$wpdb->query( "RENAME TABLE {$wpdb->base_prefix}stream TO {$wpdb->base_prefix}stream_tmp, {$wpdb->base_prefix}stream_context TO {$wpdb->base_prefix}stream_context_tmp" );

	$plugin = wp_stream_get_instance();
	$plugin->install->install( $current_version );

	$starting_row   = 0;
	$rows_per_round = 5000;

	$stream_entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}stream_tmp LIMIT %d, %d", $starting_row, $rows_per_round ) );

	while ( ! empty( $stream_entries ) ) {
		foreach ( $stream_entries as $entry ) {
			$context = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}stream_context_tmp WHERE record_id = %s LIMIT 1", $entry->ID )
			);

			$new_entry = array(
				'site_id'   => $entry->site_id,
				'blog_id'   => $entry->blog_id,
				'user_id'   => $entry->author,
				'user_role' => $entry->author_role,
				'summary'   => $entry->summary,
				'created'   => $entry->created,
				'connector' => $context->connector,
				'context'   => $context->context,
				'action'    => $context->action,
				'ip'        => $entry->ip,
			);

			if ( $entry->object_id && 0 !== $entry->object_id ) {
				$new_entry['object_id'] = $entry->object_id;
			}

			$wpdb->insert( $wpdb->base_prefix . 'stream', $new_entry );
		}

		$starting_row += $rows_per_round;

		$stream_entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}stream_tmp LIMIT %d, %d", $starting_row, $rows_per_round ) );
	}

	$wpdb->query( "DROP TABLE {$wpdb->base_prefix}stream_tmp, {$wpdb->base_prefix}stream_context_tmp" );

	return $current_version;
}
