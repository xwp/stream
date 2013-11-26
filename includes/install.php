<?php

class WP_Stream_Install {

	/**
	 * Check db version, create/update table schema accordingly
	 * 
	 * @return void
	 */
	public static function check() {

		$current = self::get_version();

		$db_version = get_option( plugin_basename( WP_STREAM_DIR ) . '_db' );

		if ( empty( $db_version ) ) {
			self::install();
		}
		elseif ( $db_version != $current ) {
			self::update( $db_version, $current );
		}
		else {
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

		$table_name = $wpdb->base_prefix . 'stream';
		$sql = "CREATE TABLE $table_name (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id bigint(20) unsigned NOT NULL DEFAULT '1',
			record_author bigint(20) unsigned NOT NULL DEFAULT '0',
			record_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			record_summary longtext NOT NULL,
			record_visibility varchar(20) NOT NULL DEFAULT 'publish',
			record_parent bigint(20) unsigned NOT NULL DEFAULT '0',
			record_type varchar(20) NOT NULL DEFAULT 'stream',
			PRIMARY KEY (ID),
			KEY site_id (site_id),
			KEY record_parent (record_parent),
			KEY record_author (record_author),
			KEY record_date (record_date)
		);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$table_name = $wpdb->base_prefix . 'stream_meta';
		$sql = "CREATE TABLE $table_name (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned NOT NULL,
			context varchar(100) NOT NULL,
			action varchar(100) NOT NULL,
			connector varchar(100) NOT NULL,
			PRIMARY KEY (meta_id),
			KEY context (context),
			KEY action (action),
			KEY connector (connector)
		);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public static function update() {
		// Reserved for future
	}

}