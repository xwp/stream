<?php

/**
 * Update Database to version 1.4.0
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return string $current_version if updated correctly
 */
function wp_stream_update_140( $db_version, $current_version ) {
	global $wpdb;

	$prefix = WP_Stream_Install::$table_prefix;

	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	// Check to see if the author_role column already exists
	$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$prefix}stream` WHERE field = 'author_role'" );

	// If the author_role doesn't exist, then create it
	if ( empty( $rows ) ) {
		$wpdb->query( "ALTER TABLE {$prefix}stream ADD author_role varchar(50) NOT NULL AFTER author" );
	}

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	if ( $wpdb->last_error ) {
		return __( 'Database Update Error', 'stream' );
	}

	return $current_version;
}

/**
 * Update Database to version 1.3.1
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return string $current_version if updated correctly
 */
function wp_stream_update_131( $db_version, $current_version ) {
	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	add_action( 'wp_stream_after_connectors_registration', 'migrate_installer_edits_to_theme_editor_connector' );

	WP_Stream_Connectors::load();

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, false );

	return $current_version;
}

/**
 * Function will migrate theme file edit records from Installer connector to the Theme Editor connector
 *
 * @action wp_stream_after_connectors_registration
 */
function migrate_installer_edits_to_theme_editor_connector() {
	global $wpdb;

	$prefix          = WP_Stream_Install::$table_prefix;
	$db_version      = WP_Stream_Install::$db_version;
	$current_version = WP_Stream_Install::$current;

	$args = array(
		'connector' => 'installer',
		'context'   => 'themes',
		'action'    => 'edited',
	);
	$records = wp_stream_query( $args );

	foreach ( $records as $record ) {
		$file_name  = wp_stream_get_meta( $record->ID, 'file', true );
		$theme_name = wp_stream_get_meta( $record->ID, 'name', true );

		if ( '' !== $theme_name ) {
			$matched_themes = array_filter(
				wp_get_themes(),
				function ( $theme ) use ( $theme_name ) {
					return (string)$theme === $theme_name;
				}
			);
			$theme = array_shift( $matched_themes );

			// `stream`
			$wpdb->update(
				$wpdb->stream,
				array(
					'summary' => sprintf( WP_Stream_Connector_Editor::get_message(), $file_name, $theme_name ),
				),
				array( 'ID' => $record->ID )
			);

			// `stream_context`
			$wpdb->update(
				$wpdb->streamcontext,
				array(
					'connector' => 'editor',
					'context'   => is_object( $theme ) ? $theme->get_template() : $theme_name,
					'action'    => 'updated',
				),
				array( 'record_id' => $record->ID )
			);

			wp_stream_update_meta( $record->ID, 'theme_name', $theme_name );

			if ( is_object( $theme ) ) {
				wp_stream_update_meta( $record->ID, 'theme_slug', $theme->get_template() );
			}
		}
	}

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	if ( $wpdb->last_error ) {
		return false;
	}

	return $current_version;
}

/**
 * Update Database to version 1.3.0
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return bool true if no wpdb errors
 */
function wp_stream_update_130( $db_version, $current_version ) {
	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	add_action( 'wp_stream_after_connectors_registration', 'migrate_old_options_to_exclude_tab' );

	WP_Stream_Connectors::load();

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, false );

	return $current_version;
}

/**
 *  Function will migrate old options from the General and Connectors tabs into the new Exclude tab
 *
 * @param $labels array connectors terms labels
 *
 * @action wp_stream_after_connectors_registration
 * @return bool|int|string
 */
function migrate_old_options_to_exclude_tab( $labels ) {
	global $wpdb;

	$prefix          = WP_Stream_Install::$table_prefix;
	$db_version      = WP_Stream_Install::$db_version;
	$current_version = WP_Stream_Install::$current;

	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	$old_options = get_option( WP_Stream_Settings::KEY, array() );

	// Stream > Settings > General > Log Activity for
	if ( isset( $old_options['general_log_activity_for'] ) ) {
		WP_Stream_Settings::$options['exclude_authors_and_roles'] = array_diff(
			array_keys( WP_Stream_Settings::get_roles() ),
			$old_options['general_log_activity_for']
		);
		unset( WP_Stream_Settings::$options['general_log_activity_for'] );
	}

	// Stream > Settings > Connectors > Active Connectors
	if ( isset( $old_options['connectors_active_connectors'] ) ) {
		WP_Stream_Settings::$options['exclude_connectors'] = array_diff(
			array_keys( $labels ),
			$old_options['connectors_active_connectors']
		);
		unset( WP_Stream_Settings::$options['connectors_active_connectors'] );
	}

	update_option( WP_Stream_Settings::KEY, WP_Stream_Settings::$options );

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	if ( $wpdb->last_error ) {
		return false;
	}

	return $current_version;
}

/**
 * Update Database to version 1.2.8
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return bool true if no wpdb errors
 */
function wp_stream_update_128( $db_version, $current_version ) {
	global $wpdb;

	$prefix = WP_Stream_Install::$table_prefix;

	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	$sql = "SELECT r.ID id, r.object_id pid, c.meta_id mid
		FROM $wpdb->stream r
		JOIN $wpdb->streamcontext c
			ON r.ID = c.record_id AND c.connector = 'media' AND c.context = 'media'
		";
	$media_records = $wpdb->get_results( $sql ); // db call ok

	foreach ( $media_records as $record ) {
		$post = get_post( $record->pid );
		$guid = isset( $post->guid ) ? $post->guid : null;
		$url  = $guid ? $guid : wp_stream_get_meta( $record->id, 'url', true );

		if ( ! empty( $url ) ) {
			$wpdb->update(
				$wpdb->streamcontext,
				array( 'context' => WP_Stream_Connector_Media::get_attachment_type( $url ) ),
				array( 'record_id' => $record->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	if ( $wpdb->last_error ) {
		return __( 'Database Update Error', 'stream' );
	}

	return $current_version;
}

/**
 * Update Database to version 1.2.5
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return bool true if no wpdb errors
 */
function wp_stream_update_125( $db_version, $current_version ) {
	global $wpdb;

	$prefix = WP_Stream_Install::$table_prefix;

	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	$sql = "SELECT r.ID id, r.object_id pid, c.meta_id mid
		FROM $wpdb->stream r
		JOIN $wpdb->streamcontext c
			ON r.ID = c.record_id AND c.connector = 'media' AND c.context = 'media'
		";
	$media_records = $wpdb->get_results( $sql ); // db call ok

	foreach ( $media_records as $record ) {
		$post = get_post( $record->pid );
		$guid = isset( $post->guid ) ? $post->guid : null;
		$url  = $guid ? $guid : wp_stream_get_meta( $record->id, 'url', true );

		if ( ! empty( $url ) ) {
			$wpdb->update(
				$wpdb->streamcontext,
				array( 'context' => WP_Stream_Connector_Media::get_attachment_type( $url ) ),
				array( 'record_id' => $record->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	if ( $wpdb->last_error ) {
		return __( 'Database Update Error', 'stream' );
	}

	return $current_version;
}

/**
 * Update Database to version 1.1.7
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return bool true if no wpdb errors
 */
function wp_stream_update_117( $db_version, $current_version ) {
	global $wpdb;

	$prefix = WP_Stream_Install::$table_prefix;

	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	$wpdb->query( "ALTER TABLE {$prefix}stream MODIFY ip varchar(39) NULL AFTER created" );

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	if ( $wpdb->last_error ) {
		return __( 'Database Update Error', 'stream' );
	}

	return $current_version;
}

/**
 * Update Database to version 1.1.4
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return bool true if no wpdb errors
 */
function wp_stream_update_114( $db_version, $current_version ) {
	global $wpdb;

	$prefix = WP_Stream_Install::$table_prefix;

	if ( ! empty( $wpdb->charset ) ) {
		return $current_version;
	}

	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	$tables  = array( 'stream', 'stream_context', 'stream_meta' );
	$collate = ( $wpdb->collate ) ? " COLLATE {$wpdb->collate}" : null;

	foreach ( $tables as $table ) {
		$wpdb->query( "ALTER TABLE {$prefix}{$table} CONVERT TO CHARACTER SET {$wpdb->charset}{$collate};" );
	}

	if ( $wpdb->last_error ) {
		return __( 'Database Update Error', 'stream' );
	}

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	return $current_version;
}
