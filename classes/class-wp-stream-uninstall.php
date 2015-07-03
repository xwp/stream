<?php

class WP_Stream_Uninstall {

	/**
	 * Hold the array of option keys to uninstall
	 *
	 * @access public
	 * @static
	 *
	 * @var array
	 */
	public static $options = array(
		WP_Stream_Install::OPTION_KEY,
		WP_Stream_Settings::OPTION_KEY,
		WP_Stream_Settings::NETWORK_OPTION_KEY,
	);

	/**
	 * Hold the array of user meta keys to uninstall
	 *
	 * @access public
	 * @static
	 *
	 * @var array
	 */
	public static $user_meta = array(
		WP_Stream_Feeds::USER_FEED_OPTION_KEY,
		'edit_stream_per_page',
		'stream_last_read', // Deprecated
		'stream_unread_count', // Deprecated
	);

	/**
	 * Uninstall Stream by deleting its data
	 *
	 * @access public
	 * @static
	 */
	public static function uninstall() {
		//check_ajax_referer( 'stream_nonce', 'wp_stream_nonce' );

		// Verify current user's permissions before proceeding
		if ( ! current_user_can( WP_Stream_Admin::SETTINGS_CAP ) ) {
			wp_die(
				esc_html__( "You don't have sufficient privileges to do this action.", 'stream' )
			);
		}

		// Prevent this action from firing
		remove_action( 'deactivate_plugin', array( 'WP_Stream_Connector_Installer', 'callback' ), null );

		// Just in case
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Drop everything on single site installs or when network activated
		// Otherwise only delete data relative to the current blog
		if ( ! is_multisite() || is_plugin_active_for_network( WP_STREAM_PLUGIN ) ) {
			self::delete_all_records();
			self::delete_all_options();
			self::delete_all_user_meta();
		} else {
			$blog_id = get_current_blog_id();

			self::delete_blog_records( $blog_id );
			self::delete_blog_options( $blog_id );
			self::delete_blog_user_meta( $blog_id );
		}

		self::delete_all_cron_events();

		self::deactivate();
	}

	/**
	 * Delete the Stream database tables
	 *
	 * @access private
	 * @static
	 */
	private static function delete_all_records() {
		global $wpdb;

		$wpdb->query( "DROP TABLE {$wpdb->stream}" );
		$wpdb->query( "DROP TABLE {$wpdb->streammeta}" );
	}

	/**
	 * Delete records and record meta from a specific blog
	 *
	 * @access private
	 * @static
	 *
	 * @param int $blog_id (optional)
	 */
	private static function delete_blog_records( $blog_id = 1 ) {
		if ( empty( $blog_id ) || ! is_int( $blog_id ) ) {
			return;
		}

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE `records`, `meta`
				FROM {$wpdb->stream} AS `records`
				LEFT JOIN {$wpdb->streammeta} AS `meta`
				ON `meta`.`record_id` = `records`.`ID`
				WHERE blog_id = %d;",
				$blog_id
			)
		);
	}

	/**
	 * Delete all options
	 *
	 * @access private
	 * @static
	 */
	private static function delete_all_options() {
		global $wpdb;

		// Wildcard matches
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wp_stream%';" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%stream-notification-rules%';" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%stream_unread_count%';" );

		// Specific options
		foreach ( self::$options as $option ) {
			delete_site_option( $option ); // Supports both multisite and single site installs
		}

		// Single site installs can stop here
		if ( ! is_multisite() ) {
			return;
		}

		// Wildcard matches on network options
		$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%wp_stream%';" );
		$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'stream_notifications%';" );

		// Delete options from each blog on network
		foreach ( wp_get_sites() as $blog ) {
			self::delete_blog_options( absint( $blog['blog_id'] ) );
		}
	}

	/**
	 * Delete options from a specific blog
	 *
	 * @access private
	 * @static
	 *
	 * @param int $blog_id (optional)
	 */
	private static function delete_blog_options( $blog_id = 1 ) {
		if ( empty( $blog_id ) || ! is_int( $blog_id ) ) {
			return;
		}

		global $wpdb;

		$blog_prefix = $wpdb->get_blog_prefix( $blog_id );

		// Wildcard matches
		$wpdb->query( "DELETE FROM {$blog_prefix}options WHERE option_name LIKE '%wp_stream%';" );

		// Specific options
		foreach ( self::$options as $option ) {
			delete_blog_option( $blog_id, $option );
		}
	}

	/**
	 * Delete all user meta
	 *
	 * @access private
	 * @static
	 */
	private static function delete_all_user_meta() {
		global $wpdb;

		// Wildcard matches
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '%wp_stream%';" );

		// Specific user meta
		foreach ( self::$user_meta as $meta_key ) {
			$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '{$meta_key}';" );
		}
	}

	/**
	 * Delete user meta from a specific blog
	 *
	 * @access private
	 * @static
	 *
	 * @param int $blog_id (optional)
	 */
	private static function delete_blog_user_meta( $blog_id = 1 ) {
		if ( empty( $blog_id ) || ! is_int( $blog_id ) ) {
			return;
		}

		global $wpdb;

		$blog_prefix = $wpdb->get_blog_prefix( $blog_id );

		// Wildcard matches
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '{$blog_prefix}%wp_stream%';" );

		// Specific user meta
		foreach ( self::$user_meta as $meta_key ) {
			$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '{$blog_prefix}{$meta_key}';" );
		}
	}

	/**
	 * Delete scheduled cron event hooks
	 *
	 * @access private
	 * @static
	 */
	private static function delete_all_cron_events() {
		wp_clear_scheduled_hook( 'wp_stream_auto_purge' );
	}

	/**
	 * Deactivate the plugin and redirect to the plugins screen
	 *
	 * @access private
	 * @static
	 */
	private static function deactivate() {
		deactivate_plugins( WP_STREAM_PLUGIN );

		wp_safe_redirect(
			add_query_arg(
				array(
					'deactivate' => true,
				),
				self_admin_url( 'plugins.php' )
			)
		);

		exit;
	}

}
