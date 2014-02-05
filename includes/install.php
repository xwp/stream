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

		$current = self::get_version();

		$db_version = get_option( plugin_basename( WP_STREAM_DIR ) . '_db' );

		self::$table_prefix = apply_filters( 'wp_stream_db_tables_prefix', $wpdb->prefix );

		if ( empty( $db_version ) ) {
			self::install();
		} elseif ( version_compare( $db_version, '1.1.4' ) == -1 ) {
			self::update_charset();
		} elseif ( $db_version != $current ) {
			self::update( $db_version, $current );
		} else {
			return;
		}

		update_option( plugin_basename( WP_STREAM_DIR ) . '_db', $current );
	}

	/**
	 * Get plugin version
	 * @return string  Plugin version
	 */
	public static function get_version() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		$name    = plugin_basename( WP_STREAM_DIR . 'stream.php' );
		return $plugins[$name]['Version'];
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
			ip varchar(20) NOT NULL,
			PRIMARY KEY (ID),
			KEY site_id (site_id),
			KEY parent (parent),
			KEY author (author),
			KEY created (created)
		) CHARACTER SET " . $wpdb->charset;

		if ( $wpdb->collate ) {
			$sql .= ' COLLATE ' . $wpdb->collate;
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
		) CHARACTER SET " . $wpdb->charset;

		if ( $wpdb->collate ) {
			$sql .= ' COLLATE ' . $wpdb->collate;
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
		) CHARACTER SET " . $wpdb->charset;

		if ( $wpdb->collate ) {
			$sql .= ' COLLATE ' . $wpdb->collate;
		}

		$sql .= ';';

		dbDelta( $sql );
	}

	/**
	 * Updates user's stream tables to include the correct
	 *
	 * @since  1.1.4
	 * @see    self::check()
	 * @return void
	 */
	public static function update_charset() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$prefix = self::$table_prefix;

		$tables = array( 'stream', 'stream_context', 'stream_meta' );
		foreach ( $tables as $table ) {
			//update table charset
			$sql = "ALTER TABLE {$prefix}{$table}
			CONVERT TO CHARACTER SET " . $wpdb->charset;
			//update table collation
			if ( $wpdb->collate ) {
				$sql .= ' COLLATE ' . $wpdb->collate;
			}
			$sql .= ';';
			$wpdb->query( $sql );
		}

	}

}
