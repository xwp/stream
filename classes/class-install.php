<?php
/**
 * Handles database migrations
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Install
 */
class Install {
	/**
	 * Holds Instance of plugin object
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Option key to store database version
	 *
	 * @var string
	 */
	public $option_key = 'wp_stream_db';

	/**
	 * Holds version of database at last update
	 *
	 * @var string
	 */
	public $db_version;

	/**
	 * URL to the Stream Admin settings page.
	 *
	 * @var string
	 */
	public $stream_url;

	/**
	 * Array of version numbers that require database update
	 *
	 * @var array
	 */
	public $update_versions;

	/**
	 * Holds status of whether it's safe to run Stream or not
	 *
	 * @var bool
	 */
	public $update_required = false;

	/**
	 * Holds status of whether the database update worked
	 *
	 * @var bool
	 */
	public $success_db;

	/**
	 * Class constructor
	 *
	 * @param Plugin $plugin  Instance of plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->db_version = $this->get_db_version();
		$this->stream_url = self_admin_url( $this->plugin->admin->admin_parent_page . '&page=' . $this->plugin->admin->settings_page_slug );

		// Check DB and display an admin notice if there are tables missing.
		add_action( 'init', array( $this, 'verify_db' ) );

		// Install the plugin.
		add_action( 'wp_stream_before_db_notices', array( $this, 'check' ) );

		register_activation_hook( $this->plugin->locations['plugin'], array( $this, 'check' ) );
	}

	/**
	 * Check db version, create/update table schema accordingly
	 * If database update required admin notice will be given
	 * on the plugin update screen
	 *
	 * @return void
	 */
	public function check() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( empty( $this->db_version ) ) {
			$this->install( $this->plugin->get_version() );
			return;
		}

		if ( $this->plugin->get_version() === $this->db_version ) {
			return;
		}

		$update = null;
		if ( isset( $_REQUEST['wp_stream_update'] ) && wp_verify_nonce( 'wp_stream_update_db' ) ) {
			$update = esc_attr( $_REQUEST['wp_stream_update'] );
		}

		if ( ! $update ) {
			$this->update_required = true;
			$this->success_db      = $this->update(
				$this->db_version,
				$this->plugin->get_version(),
				array(
					'type' => 'auto',
				)
			);
		}

		if ( 'update_and_continue' === $update ) {
			$this->success_db = $this->update(
				$this->db_version,
				$this->plugin->get_version(),
				array(
					'type' => 'user',
				)
			);
		}

		$versions = $this->db_update_versions();

		if ( ! $this->success_db && version_compare( end( $versions ), $this->db_version, '>' ) ) {
			add_action( 'all_admin_notices', array( $this, 'update_notice_hook' ) );
			return;
		}

		$this->update_db_option();
	}

	/**
	 * Verify that the required DB tables exists
	 *
	 * @return void
	 */
	public function verify_db() {
		/**
		 * Filter will halt install() if set to true
		 *
		 * @param bool
		 *
		 * @return bool
		 */
		if ( apply_filters( 'wp_stream_no_tables', false ) ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		/**
		 * Fires before admin notices are triggered for missing database tables.
		 */
		do_action( 'wp_stream_before_db_notices' );

		global $wpdb;

		$database_message  = '';
		$uninstall_message = '';

		// Check if all needed DB is present.
		$missing_tables = array();

		foreach ( $this->plugin->db->get_table_names() as $table_name ) {
			$table_search = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
			);
			if ( $table_search !== $table_name ) {
				$missing_tables[] = $table_name;
			}
		}

		if ( $missing_tables ) {
			$database_message .= sprintf(
				'%s <strong>%s</strong>',
				_n(
					'The following table is not present in the WordPress database:',
					'The following tables are not present in the WordPress database:',
					count( $missing_tables ),
					'stream'
				),
				esc_html( implode( ', ', $missing_tables ) )
			);
		}

		if ( $this->plugin->is_network_activated() && current_user_can( 'manage_network_plugins' ) ) {
			$uninstall_message = sprintf(
				/* translators: %#$s: HTML Link tags (e.g. "<a href="https://foo.com/wp-admin/">") */
				__( 'Please %1$suninstall%2$s the Stream plugin and activate it again.', 'stream' ),
				'<a href="' . network_admin_url( 'plugins.php#stream' ) . '">',
				'</a>'
			);
		} elseif ( current_user_can( 'activate_plugins' ) ) {
			$uninstall_message = sprintf(
				/* translators: %#$s: HTML Link tags (e.g. "<a href="https://foo.com/wp-admin/">") */
				__( 'Please %1$suninstall%2$s the Stream plugin and activate it again.', 'stream' ),
				'<a href="' . admin_url( 'plugins.php#stream' ) . '">',
				'</a>'
			);
		}

		if ( ! empty( $database_message ) ) {
			$this->plugin->admin->notice( $database_message );

			if ( ! empty( $uninstall_message ) ) {
				$this->plugin->admin->notice( $uninstall_message );
			}
		}
	}

	/**
	 * Register a routine to be called when stream or a stream connector has been updated
	 * It works by comparing the current version with the version previously stored in the database.
	 *
	 * @param string $file     A reference to the main plugin file.
	 * @param string $callback The function to run when the hook is called.
	 * @param string $version  The version to which the plugin is updating.
	 *
	 * @return void
	 */
	public function register_update_hook( $file, $callback, $version ) {
		if ( ! is_admin() ) {
			return;
		}

		$plugin = plugin_basename( $file );

		if ( $this->plugin->is_network_activated() ) {
			$current_versions = get_site_option( $this->option_key . '_connectors', array() );
			$network          = true;
		} elseif ( is_plugin_active( $plugin ) ) {
			$current_versions = get_option( $this->option_key . '_connectors', array() );
			$network          = false;
		} else {
			return;
		}

		if ( version_compare( $version, $current_versions[ $plugin ], '>' ) ) {
			call_user_func( $callback, $current_versions[ $plugin ], $network );

			$current_versions[ $plugin ] = $version;
		}

		if ( $network ) {
			update_site_option( $this->option_key . '_registered_connectors', $current_versions );
		} else {
			update_option( $this->option_key . '_registered_connectors', $current_versions );
		}
	}

	/**
	 * Returns the database version.
	 *
	 * @return string
	 */
	public function get_db_version() {
		return get_site_option( $this->option_key );
	}

	/**
	 * Checks if migration was successful.
	 */
	public function update_db_option() {
		if ( $this->success_db ) {
			$success_op = update_site_option( $this->option_key, $this->plugin->get_version() );
		}

		if ( ! empty( $this->success_db ) ) {
			return;
		}

		wp_die(
			esc_html__( 'There was an error updating the Stream database. Please try again.', 'stream' ),
			esc_html__( 'Database Update Error', 'stream' ),
			array(
				'response'  => 200,
				'back_link' => 1,
			)
		);
	}

	/**
	 * Added to the admin_notices hook when file plugin version is higher than database plugin version
	 *
	 * @action admin_notices
	 *
	 * @return void
	 */
	public function update_notice_hook() {
		if ( ! current_user_can( $this->plugin->admin->view_cap ) ) {
			return;
		}

		$update = null;
		if ( isset( $_REQUEST['wp_stream_update'] ) && wp_verify_nonce( 'wp_stream_update_db' ) ) {
			$update = esc_attr( $_REQUEST['wp_stream_update'] );
		}

		if ( ! $update ) {
			$this->prompt_update();

			return;
		}

		if ( 'update_and_continue' === $update ) {
			$this->prompt_update_status();
		}
	}

	/**
	 * Action hook callback function
	 *
	 * Adds the user controlled database upgrade routine to the plugins updated page.
	 * When database update is complete page will refresh with dismissible message to user.
	 *
	 * @return void
	 */
	public function prompt_update() {
		?>
		<div class="error">
			<form method="post" action="<?php echo esc_url( remove_query_arg( 'wp_stream_update' ) ); ?>">
				<?php wp_nonce_field( 'wp_stream_update_db' ); ?>
				<input type="hidden" name="wp_stream_update" value="update_and_continue"/>
				<p><strong><?php esc_html_e( 'Stream Database Update Required', 'stream' ); ?></strong></p>
				<p><?php esc_html_e( 'Stream has updated! Before we send you on your way, we need to update your database to the newest version.', 'stream' ); ?></p>
				<p><?php esc_html_e( 'This process could take a little while, so please be patient.', 'stream' ); ?></p>
				<?php submit_button( esc_html__( 'Update Database', 'stream' ), 'primary', 'stream-update-db-submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * When user initiates a database update this function calls the update methods, checks for success
	 * updates the stream_db version number in the database and outputs a success and continue message
	 *
	 * @return void
	 */
	public function prompt_update_status() {
		check_admin_referer( 'wp_stream_update_db' );

		$this->update_db_option();
		?>
		<div class="updated">
			<form method="post" action="<?php echo esc_url( remove_query_arg( 'wp_stream_update' ) ); ?>" style="display:inline;">
				<p><strong><?php esc_html_e( 'Update Complete', 'stream' ); ?></strong></p>
				<p>
					<?php
					printf(
						/* translators: %1$s: old version, %2$s: new version (e.g. "4.2") */
						esc_html__( 'Your Stream database has been successfully updated from %1$s to %2$s!', 'stream' ),
						esc_html( $this->db_version ),
						esc_html( $this->plugin->get_version() )
					);
					?>
				</p>
				<?php submit_button( esc_html__( 'Continue', 'stream' ), 'secondary', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Array of database versions that require and updates
	 *
	 * To add your own stream extension database update routine
	 * use the filter and return the version that requires an update
	 * You must also make the callback function available in the global namespace on plugins loaded
	 * use the wp_stream_update_{version_number} version number must be a string of characters that represent the version with no periods
	 *
	 * @return array
	 */
	public function db_update_versions() {
		$db_update_versions = array(
			'3.0.0', /* @version 3.0.0 Drop the stream_context table, changes to stream table */
			'3.0.2', /* @version 3.0.2 Fix uppercase values in stream table, connector column */
			'3.0.8', /* @version 3.0.8 Increase size of user role IDs, user_roll column */
		);

		/**
		 * Filter to alter the DB update versions array
		 *
		 * @param array $db_update_versions
		 *
		 * @return array
		 */
		return apply_filters( 'wp_stream_db_update_versions', $db_update_versions );
	}

	/**
	 * Database user controlled update routine
	 *
	 * @param int   $db_version      Next database version.
	 * @param int   $current_version Current database version.
	 * @param array $update_args     Update options.
	 *
	 * @return mixed Version number on success, true on no update needed, mysql error message on error
	 */
	public function update( $db_version, $current_version, $update_args ) {
		$versions = $this->db_update_versions();
		include_once $this->plugin->locations['inc_dir'] . 'db-updates.php';

		foreach ( $versions as $version ) {
			if ( ! isset( $update_args['type'] ) ) {
				$update_args['type'] = 'user';
			}

			$function = 'wp_stream_update_' . ( 'user' === $update_args['type'] ? '' : $update_args['type'] . '_' ) . str_ireplace( '.', '', $version );

			if ( version_compare( $db_version, $version, '<' ) ) {
				$result = function_exists( $function ) ? call_user_func( $function, $db_version, $current_version ) : $current_version;

				if ( $current_version !== $result ) {
					return false;
				}
			}
		}

		return $current_version;
	}

	/**
	 * Initial database install routine
	 *
	 * @param string $current_version  Current database version.
	 *
	 * @return string
	 */
	public function install( $current_version ) {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$wpdb->base_prefix}stream (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id bigint(20) unsigned NOT NULL DEFAULT '1',
			blog_id bigint(20) unsigned NOT NULL DEFAULT '1',
			object_id bigint(20) unsigned NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT '0',
			user_role varchar(50) NOT NULL DEFAULT '',
			summary longtext NOT NULL,
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			connector varchar(100) NOT NULL,
			context varchar(100) NOT NULL,
			action varchar(100) NOT NULL,
			ip varchar(39) NULL,
			PRIMARY KEY  (ID),
			KEY site_id (site_id),
			KEY blog_id (blog_id),
			KEY object_id (object_id),
			KEY user_id (user_id),
			KEY created (created),
			KEY connector (connector),
			KEY context (context),
			KEY action (action)
		)";

		if ( ! empty( $wpdb->charset ) ) {
			$sql .= " CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$sql .= " COLLATE $wpdb->collate";
		}

		$sql .= ';';

		\dbDelta( $sql );

		if ( ! empty( $wpdb->charset ) ) {
			$sql .= " CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$sql .= " COLLATE $wpdb->collate";
		}

		$sql .= ';';

		\dbDelta( $sql );

		$sql = "CREATE TABLE {$wpdb->base_prefix}stream_meta (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned NOT NULL,
			meta_key varchar(200) NOT NULL,
			meta_value varchar(200) NOT NULL,
			PRIMARY KEY  (meta_id),
			KEY record_id (record_id),
			KEY meta_key (meta_key(191)),
			KEY meta_value (meta_value(191))
		)";

		if ( ! empty( $wpdb->charset ) ) {
			$sql .= " CHARACTER SET $wpdb->charset";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$sql .= " COLLATE $wpdb->collate";
		}

		$sql .= ';';

		\dbDelta( $sql );

		update_site_option( $this->option_key, $this->plugin->get_version() );

		return $current_version;
	}
}
