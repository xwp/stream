<?php
namespace WP_Stream;

class Install {
	/**
	 * Hold Plugin class
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
	 * Holds the database table prefix
	 *
	 * @var string
	 */
	public $table_prefix;

	/**
	 * Holds version of database at last update
]	 *
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
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->db_version = $this->get_db_version();
		$this->stream_url = self_admin_url( $this->plugin->admin->admin_parent_page . '&page=' . $this->plugin->admin->settings_page_slug );

		global $wpdb;

		/**
		 * Allows devs to alter the tables prefix, default to base_prefix
		 *
		 * @param string $prefix
		 *
		 * @return string
		 */
		$this->table_prefix = apply_filters( 'wp_stream_db_tables_prefix', $wpdb->base_prefix );

		$this->check();
	}

	/**
	 * Check db version, create/update table schema accordingly
	 * If database update required admin notice will be given
	 * on the plugin update screen
	 *
	 * @return void
	 */
	private function check() {
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

		$update = isset( $_REQUEST['wp_stream_update'] ) ? $_REQUEST['wp_stream_update'] : null;

		if ( ! $update ) {
			$this->update_required = true;
			$this->success_db      = $this->update( $this->db_version, $this->plugin->get_version(), array( 'type' => 'auto' ) );

			return;
		}

		if ( 'update_and_continue' === $update ) {
			$this->$success_db = $this->update( $this->db_version, $this->plugin->get_version(), array( 'type' => 'user' ) );
		}

		$versions = $this->db_update_versions();

		if ( version_compare( end( $versions ), $this->db_version, '>' ) ) {
			add_action( 'all_admin_notices', array( $this, 'update_notice_hook' ) );

			return;
		}

		$this->update_db_option();
	}

	/**
	 * @return string
	 */
	public function get_db_version() {
		global $wpdb;

		$version = get_site_option( $this->option_key );

		if ( ! empty( $version ) ) {
			return $version;
		}

		$old_key = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '%stream%_db'" );

		if ( ! empty( $old_key ) && is_array( $old_key ) ) {
			$version = get_option( $old_key[0] );

			update_site_option( self::KEY, $version );

			delete_option( $old_key[0] );
		}

		return $version;
	}

	/**
	 * @return void
	 */
	public function update_db_option() {
		if ( $this->success_db ) {
			$success_op = update_site_option( $this->option_key, $this->plugin->get_version() );
		}

		if ( ! empty( $this->success_db ) && ! empty( $success_op ) ) {
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

		$update = isset( $_REQUEST['wp_stream_update'] ) ? $_REQUEST['wp_stream_update'] : null;

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
			<form method="post" action="<?php echo esc_url( remove_query_arg( 'wp_stream_update' ) ) ?>">
				<?php wp_nonce_field( 'wp_stream_update_db' ) ?>
				<input type="hidden" name="wp_stream_update" value="update_and_continue"/>
				<p><strong><?php esc_html_e( 'Stream Database Update Required', 'stream' ) ?></strong></p>
				<p><?php esc_html_e( 'Stream has updated! Before we send you on your way, we need to update your database to the newest version.', 'stream' ) ?></p>
				<p><?php esc_html_e( 'This process could take a little while, so please be patient.', 'stream' ) ?></p>
				<?php submit_button( esc_html__( 'Update Database', 'stream' ), 'primary', 'stream-update-db-submit' ) ?>
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
			<form method="post" action="<?php echo esc_url( remove_query_arg( 'wp_stream_update' ) ) ?>" style="display:inline;">
				<p><strong><?php esc_html_e( 'Update Complete', 'stream' ) ?></strong></p>
				<p><?php esc_html_e( sprintf( 'Your Stream database has been successfully updated from %1$s to %2$s!', esc_html( self::$db_version ), esc_html( WP_Stream::VERSION ) ), 'stream' ) ?></p>
				<?php submit_button( esc_html__( 'Continue', 'stream' ), 'secondary', false ) ?>
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
			'1.1.4' /* @version 1.1.4 Fix mysql character set issues */,
			'1.1.7' /* @version 1.1.7 Modified the ip column to varchar(39) */,
			'1.2.8' /* @version 1.2.8 Change the context for Media connectors to the attachment type */,
			'1.3.0' /* @version 1.3.0 Backward settings compatibility for old version plugins */,
			'1.3.1' /* @version 1.3.1 Update records of Installer to Theme Editor connector */,
			'1.4.0' /* @version 1.4.0 Add the author_role column and prepare tables for multisite support */,
			'1.4.2' /* @version 1.4.2 Patch to fix rare multisite upgrade not triggering */,
			'1.4.5' /* @version 1.4.5 Patch to fix author_meta broken values */,
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
	 * @param int   $db_version
	 * @param int   $current_version
	 * @param array $update_args
	 *
	 * @return mixed Version number on success, true on no update needed, mysql error message on error
	 */
	public function update( $db_version, $current_version, $update_args ) {
		$versions = $this->db_update_versions();

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
	 * @param string $current_version
	 *
	 * @return string
	 */
	private function install( $current_version ) {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$prefix = $this->table_prefix;

		$sql = "CREATE TABLE {$prefix}stream (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id bigint(20) unsigned NOT NULL DEFAULT '1',
			blog_id bigint(20) unsigned NOT NULL DEFAULT '1',
			object_id bigint(20) unsigned NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT '0',
			user_role varchar(20) NOT NULL DEFAULT '',
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

		$sql = "CREATE TABLE {$prefix}stream_meta (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned NOT NULL,
			meta_key varchar(200) NOT NULL,
			meta_value varchar(200) NOT NULL,
			PRIMARY KEY  (meta_id),
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

		\dbDelta( $sql );

		update_site_option( $this->option_key, $this->plugin->get_version() );

		return $current_version;
	}
}
