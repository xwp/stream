<?php
/**
 * Manages the uninstallation of the plugin.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Uninstall
 */
class Uninstall {
	/**
	 * Holds Instance of plugin object
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Hold the array of option keys to uninstall
	 *
	 * @var array
	 */
	public $options;

	/**
	 * Hold the array of user meta keys to uninstall
	 *
	 * @var array
	 */
	public $user_meta;

	/**
	 * Class constructor
	 *
	 * @param Plugin $plugin  Instance of plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->user_meta = array(
			'edit_stream_per_page',
			'stream_last_read', // Deprecated.
			'stream_unread_count', // Deprecated.
			'stream_user_feed_key', // Deprecated.
		);
	}

	/**
	 * Uninstall Stream by deleting its data
	 */
	public function uninstall() {
		$this->options = array(
			$this->plugin->install->option_key,
			$this->plugin->settings->option_key,
			$this->plugin->settings->network_options_key,
		);

		// Verify current user's permissions before proceeding.
		if ( ! current_user_can( $this->plugin->admin->settings_cap ) ) {
			wp_die(
				esc_html__( "You don't have sufficient privileges to do this action.", 'stream' )
			);
		}

		if ( defined( 'DISALLOW_FILE_MODS' ) && true === DISALLOW_FILE_MODS ) {
			wp_die(
				esc_html__( "You don't have sufficient file permissions to do this action.", 'stream' )
			);
		}

		// Prevent this action from firing.
		remove_action( 'deactivate_plugin', array( 'Connector_Installer', 'callback' ), null );

		// Just in case.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		/**
		 * Drop everything on single site installs or when network activated
		 * Otherwise only delete data relative to the current blog.
		 */
		if ( ! is_multisite() || $this->plugin->is_network_activated() ) {
			$this->delete_all_records();
			$this->delete_all_options();
			$this->delete_all_user_meta();
		} else {
			$blog_id = get_current_blog_id();

			$this->delete_blog_records( $blog_id );
			$this->delete_blog_options( $blog_id );
			$this->delete_blog_user_meta( $blog_id );
		}

		$this->delete_all_cron_events();

		$this->deactivate();
	}

	/**
	 * Delete the Stream database tables
	 */
	private function delete_all_records() {
		global $wpdb;

		$wpdb->query( "DROP TABLE {$wpdb->stream}" );
		$wpdb->query( "DROP TABLE {$wpdb->streammeta}" );
	}

	/**
	 * Delete records and record meta from a specific blog
	 *
	 * @param int $blog_id  Blog ID (optional).
	 */
	private function delete_blog_records( $blog_id = 1 ) {
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
	 */
	private function delete_all_options() {
		global $wpdb;

		// Wildcard matches.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wp_stream%';" );

		// Specific options.
		foreach ( $this->options as $option ) {
			delete_site_option( $option ); // Supports both multisite and single site installs.
		}

		// Single site installs can stop here.
		if ( ! is_multisite() ) {
			return;
		}

		// Wildcard matches on network options.
		$wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%wp_stream%';" );

		// Delete options from each blog on network.
		foreach ( wp_stream_get_sites() as $blog ) {
			$this->delete_blog_options( absint( $blog->blog_id ) );
		}
	}

	/**
	 * Delete options from a specific blog
	 *
	 * @param int $blog_id  Blog ID (optional).
	 */
	private function delete_blog_options( $blog_id = 1 ) {
		if ( empty( $blog_id ) || ! is_int( $blog_id ) ) {
			return;
		}

		global $wpdb;

		// Wildcard matches.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '%wp_stream%';" );

		// Specific options.
		foreach ( $this->options as $option ) {
			delete_blog_option( $blog_id, $option );
		}
	}

	/**
	 * Delete all user meta
	 */
	private function delete_all_user_meta() {
		global $wpdb;

		// Wildcard matches.
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '%wp_stream%';" );

		// Specific user meta.
		foreach ( $this->user_meta as $meta_key ) {
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s;", $meta_key )
			);
		}
	}

	/**
	 * Delete user meta from a specific blog
	 *
	 * @param int $blog_id Blog ID (optional).
	 */
	private function delete_blog_user_meta( $blog_id = 1 ) {
		if ( empty( $blog_id ) || ! is_int( $blog_id ) ) {
			return;
		}

		global $wpdb;

		// Wildcard matches.
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '{$wpdb->prefix}%wp_stream%';" );

		// Specific user meta.
		foreach ( $this->user_meta as $meta_key ) {
			$wpdb->query(
				$wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = {$wpdb->prefix}%s;", $meta_key )
			);
		}
	}

	/**
	 * Delete scheduled cron event hooks
	 */
	private function delete_all_cron_events() {
		wp_clear_scheduled_hook( 'wp_stream_auto_purge' );
	}

	/**
	 * Deactivate the plugin and redirect to the plugins screen
	 */
	private function deactivate() {
		deactivate_plugins( $this->plugin->locations['plugin'] );

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
