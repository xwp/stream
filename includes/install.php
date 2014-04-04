<?php

class WP_Stream_Install {

	public static $table_prefix;

	/**
	 * Check db version, create/update table schema accordingly
	 *
	 * @return void
	 */
	public static function check() {
		global $wpdb;

		$current = WP_Stream::VERSION;

		$db_version = get_option( plugin_basename( WP_STREAM_DIR ) . '_db' );

		/**
		 * Allows devs to alter the tables prefix, default to base_prefix
		 *
		 * @param  string  database prefix
		 * @return string  udpated database prefix
		 */
		self::$table_prefix = apply_filters( 'wp_stream_db_tables_prefix', $wpdb->prefix );

		if ( empty( $db_version ) ) {
			self::install();
		} elseif ( $db_version !== $current ) {
			self::update( $db_version, $current );
		} else {
			return;
		}

		update_option( plugin_basename( WP_STREAM_DIR ) . '_db', $current );
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$prefix = self::$table_prefix;

		$sql = "CREATE TABLE {$prefix}stream (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id bigint(20) unsigned NOT NULL DEFAULT '1',
			object_id bigint(20) unsigned NULL,
			author bigint(20) unsigned NOT NULL DEFAULT '0',
			summary longtext NOT NULL,
			visibility varchar(20) NOT NULL DEFAULT 'publish',
			parent bigint(20) unsigned NOT NULL DEFAULT '0',
			type varchar(20) NOT NULL DEFAULT 'stream',
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			ip varchar(39) NULL,
			PRIMARY KEY (ID),
			KEY site_id (site_id),
			KEY parent (parent),
			KEY author (author),
			KEY created (created)
		)";

		if ( ! empty( $wpdb->charset ) ) {
			$sql .= " CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$sql .= " COLLATE $wpdb->collate";
		}

		$sql .= ';';

		dbDelta( $sql );

		$sql = "CREATE TABLE {$prefix}stream_context (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned NOT NULL,
			context varchar(100) NOT NULL,
			action varchar(100) NOT NULL,
			connector varchar(100) NOT NULL,
			PRIMARY KEY (meta_id),
			KEY context (context),
			KEY action (action),
			KEY connector (connector)
		)";

		if ( ! empty( $wpdb->charset ) ) {
			$sql .= " CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$sql .= " COLLATE $wpdb->collate";
		}

		$sql .= ';';

		dbDelta( $sql );

		$sql = "CREATE TABLE {$prefix}stream_meta (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned NOT NULL,
			meta_key varchar(200) NOT NULL,
			meta_value varchar(200) NOT NULL,
			PRIMARY KEY (meta_id),
			KEY record_id (record_id),
			KEY meta_key (meta_key),
			KEY meta_value (meta_value)
		)";

		if ( ! empty( $wpdb->charset ) ) {
			$sql .= " CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$sql .= " COLLATE $wpdb->collate";
		}

		$sql .= ';';

		dbDelta( $sql );
	}

	public static function update( $db_version, $current ) {
		global $wpdb;
		$prefix = self::$table_prefix;

		// If version is lower than 1.1.4, do the update routine
		if ( version_compare( $db_version, '1.1.4', '<' ) && ! empty( $wpdb->charset ) ) {
			$tables  = array( 'stream', 'stream_context', 'stream_meta' );
			$collate = ( $wpdb->collate ) ? " COLLATE {$wpdb->collate}" : null;
			foreach ( $tables as $table ) {
				$wpdb->query( "ALTER TABLE {$prefix}{$table} CONVERT TO CHARACTER SET {$wpdb->charset}{$collate};" );
			}
		}

		// If version is lower than 1.1.7, do the update routine
		if ( version_compare( $db_version, '1.1.7', '<' ) ) {
			$wpdb->query( "ALTER TABLE {$prefix}stream MODIFY ip varchar(39) NULL AFTER created" );
		}

		// If version is lower than 1.2.5, do the update routine
		// Taxonomy records switch from term_id to term_taxonomy_id
		if ( version_compare( $db_version, '1.2.5', '<' ) ) {
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
			$tax_records = $wpdb->get_results( $sql ); // db call ok
			foreach ( $tax_records as $record ) {
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
		}

		// If version is lower than 1.2.8, do the update routine
		// Change the context for Media connectors to the attachment type
		if ( version_compare( $db_version, '1.2.8', '<' ) ) {
			$sql = "SELECT r.ID id, r.object_id pid, c.meta_id mid
				FROM $wpdb->stream r
				JOIN $wpdb->streamcontext c
					ON r.ID = c.record_id AND c.connector = 'media' AND c.context = 'media'
				";
			$media_records = $wpdb->get_results( $sql ); // db call ok

			require_once WP_STREAM_INC_DIR . 'query.php';
			require_once WP_STREAM_CLASS_DIR . 'connector.php';
			require_once WP_STREAM_DIR . 'connectors/media.php';

			foreach ( $media_records as $record ) {
				$post = get_post( $record->pid );
				$guid = isset( $post->guid ) ? $post->guid : null;
				$url  = $guid ? $guid : get_stream_meta( $record->id, 'url', true );

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
		}

		// If version is lower than 1.3.0, do the update routine for site options
		// Backward settings compatibility for old version plugins
		if ( version_compare( $db_version, '1.3.0', '<' ) ) {
			add_action( 'wp_stream_after_connectors_registration', 'WP_Stream_Install::migrate_old_options_to_exclude_tab' );
		}

		// If version is lower than 1.3.1, do the update routine
		// Update records of Installer to Theme Editor connector
		if ( version_compare( $db_version, '1.3.1', '<' ) ) {
			add_action( 'wp_stream_after_connectors_registration', 'WP_Stream_Install::migrate_installer_edits_to_theme_editor_connector' );
		}
	}

	/**
	 * Function will migrate old options from the General and Connectors tabs into the new Exclude tab
	 *
	 * @param $labels array connectors terms labels
	 * @action wp_stream_after_connectors_registration
	 */
	public static function migrate_old_options_to_exclude_tab( $labels ) {
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
	}

	/**
	 * Function will migrate theme file edit records from Installer connector to the Theme Editor connector
	 *
	 * @action wp_stream_after_connectors_registration
	 */
	public static function migrate_installer_edits_to_theme_editor_connector() {
		global $wpdb;

		$args = array(
			'connector' => 'installer',
			'context'   => 'themes',
			'action'    => 'edited',
		);
		$records = stream_query( $args );

		foreach ( $records as $record ) {
			$file_name  = get_stream_meta( $record->ID, 'file', true );
			$theme_name = get_stream_meta( $record->ID, 'name', true );

			if ( '' !== $theme_name ) {
				$matched_themes = array_filter(
					wp_get_themes(),
					function( $theme ) use ( $theme_name ) {
						return (string) $theme === $theme_name;
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

				update_stream_meta( $record->ID, 'theme_name', $theme_name );

				if ( is_object( $theme ) ) {
					update_stream_meta( $record->ID, 'theme_slug', $theme->get_template() );
				}
			}
		}
	}

}
