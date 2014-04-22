<?php

/**
 * Version 1.4.0
 *
 * Create `author_role` column in `stream` table if it doesn't already exist.
 * Create `blog_id` column in `stream` table if it doesn't already exist.
 * Merge multisite Stream tables.
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

	// Check to see if the blog_id column already exists
	$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$prefix}stream` WHERE field = 'blog_id'" );

	// If the blog_id doesn't exist, then create it and update records retroactively
	if ( empty( $rows ) ) {
		$wpdb->query( "ALTER TABLE {$prefix}stream ADD blog_id bigint(20) unsigned NOT NULL DEFAULT '0' AFTER site_id" );
	}

	// If multisite, merge the site stream tables into one
	if ( is_multisite() ) {
		$blogs = wp_get_sites();

		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog['blog_id'] );

			// No need to merge the primary site, but update the blog_id
			if ( $wpdb->prefix === $prefix ) {
				$wpdb->update(
					$prefix . 'stream',
					array( 'blog_id' => $blog['blog_id'] ),
					array( 'blog_id' => '0' ),
					array( '%d' ),
					array( '%d' )
				);
				continue;
			}

			$sql = "SELECT * FROM {$wpdb->prefix}stream";

			$blog_stream = $wpdb->get_results( $sql, ARRAY_A );

			foreach ( $blog_stream as $key => $stream_entry ) {
				$prev_entry_id = $stream_entry['ID'];

				unset( $stream_entry['ID'] );
				$stream_entry['blog_id'] = $blog['blog_id'];

				$wpdb->insert( $wpdb->base_prefix . 'stream', $stream_entry );
				$stream_entry_id = $wpdb->insert_id;

				$sql = "SELECT * FROM {$wpdb->prefix}stream_context WHERE record_id = $prev_entry_id";

				$blog_stream_context = $wpdb->get_results( $sql, ARRAY_A );

				foreach ( $blog_stream_context as $key => $stream_context ) {
					unset( $stream_context['meta_id'] );
					$stream_context['record_id'] = $stream_entry_id;

					$wpdb->insert( $wpdb->base_prefix . 'stream_context', $stream_context );
				}

				$sql = "SELECT * FROM {$wpdb->prefix}stream_meta WHERE record_id = $prev_entry_id";

				$blog_stream_meta = $wpdb->get_results( $sql, ARRAY_A );

				foreach ( $blog_stream_meta as $key => $stream_meta ) {
					unset( $stream_meta['meta_id'] );
					$stream_meta['record_id'] = $stream_entry_id;

					$wpdb->insert( $wpdb->base_prefix . 'stream_meta', $stream_meta );
				}
			}

			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}stream, {$wpdb->prefix}stream_context, {$wpdb->prefix}stream_meta" );
		}

		restore_current_blog();
	}

	if ( $wpdb->last_error ) {
		return __( 'Database Update Error', 'stream' );
	}

	// Check to see if the author_role column already exists
	$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$prefix}stream` WHERE field = 'author_role'" );

	// If the author_role doesn't exist, then create it and update records retroactively
	if ( empty( $rows ) ) {
		$wpdb->query( "ALTER TABLE {$prefix}stream ADD author_role varchar(50) NULL AFTER author" );

		$records = wp_stream_query( array( 'records_per_page' => -1 ) );

		foreach ( $records as $record ) {
			$user = get_user_by( 'id', $record->author );

			if ( ! $user || ! isset( $user->roles[0] ) ) {
				continue;
			}

			$wpdb->update(
				$wpdb->stream,
				array(
					'author_role' => $user->roles[0],
				),
				array( 'ID' => $record->ID )
			);
		}
	}

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	if ( $wpdb->last_error ) {
		return __( 'Database Update Error', 'stream' );
	}

	// Clear an old cron event hook that is lingering, replaced by `wp_stream_auto_purge`
	wp_clear_scheduled_hook( 'stream_auto_purge' );

	// Clear out this cron event hook too since we're changing the interval in this release
	// and want the new schedule to take affect immediately after updating.
	wp_clear_scheduled_hook( 'wp_stream_auto_purge' );

	return $current_version;
}

/**
 * Version 1.3.1
 *
 * Migrate theme file edit records from Installer connector to the Theme Editor connector and update summaries.
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
 * @param $labels array connectors terms labels
 *
 * @action wp_stream_after_connectors_registration
 * @return string $current_version if updated correctly
 */
function migrate_installer_edits_to_theme_editor_connector() {
	global $wpdb;

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
 * Version 1.3.0
 *
 * Migrate old options from the General and Connectors tabs into the new Exclude tab.
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return string $current_version if updated correctly
 */
function wp_stream_update_130( $db_version, $current_version ) {
	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	add_action( 'wp_stream_after_connectors_registration', 'migrate_old_options_to_exclude_tab' );

	WP_Stream_Connectors::load();

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, false );

	return $current_version;
}

/**
 * @param $labels array connectors terms labels
 *
 * @action wp_stream_after_connectors_registration
 * @return string $current_version if updated correctly
 */
function migrate_old_options_to_exclude_tab( $labels ) {
	global $wpdb;

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
 * Version 1.2.8
 *
 * Update existing Media records so that the context is the media's attachment type.
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return string $current_version if updated correctly
 */
function wp_stream_update_128( $db_version, $current_version ) {
	global $wpdb;

	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	$sql = "SELECT r.ID id, r.object_id pid, c.meta_id mid
		FROM $wpdb->stream r
		JOIN $wpdb->streamcontext c
			ON r.ID = c.record_id AND c.connector = 'media' AND c.context = 'media'
	";

	$records = $wpdb->get_results( $sql ); // db call ok

	foreach ( $records as $record ) {
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
		return esc_html__( 'Database Update Error', 'stream' );
	}

	return $current_version;
}

/**
 * Version 1.2.5
 *
 * Taxonomy records switch from term_id to term_taxonomy_id.
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return string $current_version if updated correctly
 */
function wp_stream_update_125( $db_version, $current_version ) {
	global $wpdb;

	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	$sql = "SELECT r.ID id, tt.term_taxonomy_id tt
		FROM $wpdb->stream r
		JOIN $wpdb->streamcontext c
			ON r.ID = c.record_id AND c.connector = 'taxonomies'
		JOIN $wpdb->streammeta m
			ON r.ID = m.record_id AND m.meta_key = 'term_id'
		JOIN $wpdb->streammeta m2
			ON r.ID = m2.record_id AND m2.meta_key = 'taxonomy'
		JOIN $wpdb->term_taxonomy tt
			ON tt.term_id = m.meta_value
			AND tt.taxonomy = m2.meta_value
	";

	$records = $wpdb->get_results( $sql ); // db call ok

	foreach ( $records as $record ) {
		if ( ! empty( $record->tt ) ) {
			$wpdb->update(
				$wpdb->stream,
				array( 'object_id' => $record->tt ),
				array( 'ID' => $record->id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	if ( $wpdb->last_error ) {
		return esc_html__( 'Database Update Error', 'stream' );
	}

	return $current_version;
}

/**
 * Version 1.1.7
 *
 * Update the IP column to be compatible with IPv6 addresses.
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return string $current_version if updated correctly
 */
function wp_stream_update_117( $db_version, $current_version ) {
	global $wpdb;

	$prefix = WP_Stream_Install::$table_prefix;

	do_action( 'wp_stream_before_db_update_' . $db_version, $current_version );

	$wpdb->query( "ALTER TABLE {$prefix}stream MODIFY ip varchar(39) NULL AFTER created" );

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	if ( $wpdb->last_error ) {
		return esc_html__( 'Database Update Error', 'stream' );
	}

	return $current_version;
}

/**
 * Version 1.1.4
 *
 * Update the charset and collation in all stream tables.
 *
 * @param string $db_version Database version updating from
 * @param string $current_version Database version updating to
 *
 * @return string $current_version if updated correctly
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

	do_action( 'wp_stream_after_db_update_' . $db_version, $current_version, $wpdb->last_error );

	if ( $wpdb->last_error ) {
		return esc_html__( 'Database Update Error', 'stream' );
	}

	return $current_version;
}
