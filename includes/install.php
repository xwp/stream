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
		} elseif ( $db_version != $current ) {
			self::update( $db_version, $current );
		} else {
			return;
		}

		update_option( plugin_basename( WP_STREAM_DIR ) . '_db', $current );
	}

	public static function install() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

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
		if ( version_compare( $db_version, '1.1.4' ) == -1 && ! empty( $wpdb->charset ) ) {
			$tables  = array( 'stream', 'stream_context', 'stream_meta' );
			$collate = ( $wpdb->collate ) ? " COLLATE {$wpdb->collate}" : null;
			foreach ( $tables as $table ) {
				$wpdb->query( "ALTER TABLE {$prefix}{$table} CONVERT TO CHARACTER SET {$wpdb->charset}{$collate};" );
			}
		}

		// If version is lower than 1.1.7, do the update routine
		if ( version_compare( $db_version, '1.1.7' ) == -1 ) {
			$wpdb->query( "ALTER TABLE {$prefix}stream MODIFY ip varchar(39) NULL AFTER created" );
		}

		// Taxonomy records switch from term_id to term_taxonomy_id
		if ( version_compare( $db_version, '1.2.5', '<=' ) ) {
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
			$tax_records = $wpdb->get_results( $sql ); // db call okay
			foreach ( $tax_records as $record ) {
				if ( ! empty( $record->tt ) ) {
					$wpdb->update(
						$wpdb->stream,
						array( 'object_id' => $record->tt ),
						array( 'ID' => $record->id )
					);
				}
			}
		}
	}

}
