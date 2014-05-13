<?php

class WP_Stream_Install_WPDB {

	/**
	 * Option key to store database version
	 *
	 * @var string
	 */
	const KEY = 'wp_stream_db';

	/**
	 * Holds the database table prefix
	 *
	 * @access public
	 * @var mixed|void
	 */
	public static $table_prefix;

	/**
	 * Holds version of database at last update
	 *
	 * @access public
	 * @var mixed|void
	 */
	public static $db_version;

	/**
	 * Current version of running plugin
	 *
	 * @access public
	 * @var string|int
	 */
	public static $current;

	/**
	 * URL to the Stream Admin settings page.
	 *
	 * @access public
	 * @var string
	 */
	public static $stream_url;

	/**
	 * Array of version numbers that require database update
	 *
	 * @access public
	 * @var array
	 */
	public static $update_versions;

	/**
	 * Holds status of whether it's safe to run Stream or not
	 *
	 * @access public
	 * @var bool
	 */
	public static $update_required = false;

	/**
	 * Holds status of whether the database update worked
	 *
	 * @access public
	 * @var bool|string
	 */
	public static $success_db;

	/**
	 * Initialized object of class
	 *
	 * @access private
	 * @var bool|object
	 */
	private static $instance = false;

	/**
	 * Gets instance of singleton class
	 *
	 * @access public
	 * @return bool|object|WP_Stream_Install_WPDB
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor
	 * Sets static class properties
	 */
	function __construct() {
		global $wpdb;

		self::$current    = WP_Stream::VERSION;
		self::$db_version = self::get_db_version();
		self::$stream_url = self_admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE . '&page=' . WP_Stream_Admin::SETTINGS_PAGE_SLUG );

		/**
		 * Allows developers to alter the tables prefix, default to base_prefix
		 *
		 * @var string $prefix  database prefix
		 * @var string $table_prefix updated database prefix
		 */
		$prefix = $wpdb->base_prefix;

		self::$table_prefix = apply_filters( 'wp_stream_db_tables_prefix', $prefix );
		self::check();
	}

	/**
	 * Check db version, create/update table schema accordingly
	 * If database update required admin notice will be given
	 * on the plugin update screen
	 *
	 * @return null
	 */
	private static function check() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( empty( self::$db_version ) ) {
			self::install( self::$current );
		} elseif ( self::$db_version !== self::$current ) {

			if ( ! isset( $_REQUEST['wp_stream_update'] ) ) {
				self::$update_required = true;
				$update_args = array( 'type' => 'auto' );
				self::$success_db = self::update( self::$db_version, self::$current, $update_args );
			} elseif ( 'update_and_continue' === $_REQUEST['wp_stream_update'] ) {
				$update_args = array( 'type' => 'user' );
				self::$success_db = self::update( self::$db_version, self::$current, $update_args );
			}

			// We need to check if there is a manual update needed between the current and last db version.
			$versions = self::db_update_versions();
			if ( version_compare( end( $versions ), self::$db_version, '>' ) ) {
				add_action( 'all_admin_notices', array( __CLASS__, 'update_notice_hook' ) );
			} else {
				self::update_db_option();
			}
		}
	}

	public static function get_db_version() {
		global $wpdb;

		$version = get_site_option( self::KEY );

		if ( ! $version ) {
			$old_key = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '%stream%_db'" );
			if ( ! empty( $old_key ) && is_array( $old_key ) ) {
				$version = get_option( $old_key[0] );

				update_site_option( self::KEY, $version );
				delete_option( $old_key[0] );
			}
		}

		return $version;
	}

	public static function update_db_option() {
		if ( self::$success_db ) {
			$success_op = update_site_option( self::KEY, self::$current );
		}

		if ( empty( self::$success_db ) || empty( $success_op ) ) {
			wp_die( __( 'There was an error updating the Stream database. Please try again.', 'stream' ), 'Database Update Error', array( 'response' => 200, 'back_link' => true ) );
		}
	}

	/**
	 * Added to the admin_notices hook when file plugin version is higher than database plugin version
	 *
	 * @action admin_notices
	 * @return void
	 */
	public static function update_notice_hook() {
		if ( ! current_user_can( WP_Stream_Admin::VIEW_CAP ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['wp_stream_update'] ) ) {
			self::prompt_update();
		} elseif ( 'update_and_continue' === $_REQUEST['wp_stream_update'] ) {
			self::prompt_update_status();
		}
	}

	/**
	 * Action hook callback function
	 * Adds the user controlled database upgrade routine to the plugins updated page
	 *
	 * When database update is complete page will refresh with dismissible message to user
	 */
	public static function prompt_update() {
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
	 */
	public static function prompt_update_status() {
		check_admin_referer( 'wp_stream_update_db' );

		self::update_db_option();
		?>
		<div class="updated">
			<form method="post" action="<?php echo esc_url( remove_query_arg( 'wp_stream_update' ) ) ?>" style="display:inline;">
				<p><strong><?php esc_html_e( 'Update Complete', 'stream' ) ?></strong></p>
				<p><?php esc_html_e( sprintf( 'Your Stream database has been successfully updated from %1$s to %2$s!', self::$db_version, self::$current ), 'stream' ) ?></p>
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
	 * @filter wp_stream_db_update_versions
	 *
	 * @access private
	 * @return array
	 */
	public static function db_update_versions() {
		$db_update_versions = array(
			'1.1.4' /* @version 1.1.4 Fix mysql character set issues */,
			'1.1.7' /* @version 1.1.7 Modified the ip column to varchar(39) */,
			'1.2.8' /* @version 1.2.8 Change the context for Media connectors to the attachment type */,
			'1.3.0' /* @version 1.3.0 Backward settings compatibility for old version plugins */,
			'1.3.1' /* @version 1.3.1 Update records of Installer to Theme Editor connector */,
			'1.4.0' /* @version 1.4.0 Add the author_role column and prepare tables for multisite support */,
			'1.4.2' /* @version 1.4.2 Patch to fix rare multisite upgrade not triggering */,
			'2.0.0' /* @version 2.0.0 Removing context table, adding columns to base table */,
		);

		return apply_filters( 'wp_stream_db_update_versions', $db_update_versions );
	}

	/**
	 * Database user controlled update routine
	 *
	 * @param int $db_version last updated version of database stored in plugin options
	 * @param int $current    Current running plugin version
	 * @param array $update_args
	 *
	 * @return mixed Version number on success, true on no update needed, mysql error message on error
	 */
	public static function update( $db_version, $current, $update_args ) {
		require_once WP_STREAM_INC_DIR . 'db/install/wpdb-updates.php';

		$versions = self::db_update_versions();

		foreach ( $versions as $version ) {
			if ( ! isset( $update_args['type'] ) ) {
				$update_args['type'] = 'user';
			}

			$function = 'wp_stream_update_' . ( 'user' === $update_args['type'] ? '' : $update_args['type'] . '_' ) . str_ireplace( '.', '', $version );

			if ( version_compare( $db_version, $version, '<' ) ) {
				$result = function_exists( $function ) ? call_user_func( $function, $db_version, $current ) : $current;
				if ( $current !== $result ) {
					return false;
				}
			}
		}

		return $current;
	}

	/**
	 * Initial database install routine
	 *
	 * @uses dbDelta()
	 * @param string $current Current version of plugin installed
	 * @return string Current version of plugin installed
	 * @todo  Test this after the 2.0 version change
	 */
	public static function install( $current ) {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$prefix = self::$table_prefix;

		$sql = "CREATE TABLE {$prefix}stream (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id bigint(20) unsigned NOT NULL DEFAULT '1',
			blog_id bigint(20) unsigned NOT NULL DEFAULT '0',
			object_id bigint(20) unsigned NULL,
			author bigint(20) unsigned NOT NULL DEFAULT '0',
			author_role varchar(20) NOT NULL DEFAULT '',
			summary longtext NOT NULL,
			visibility varchar(20) NOT NULL DEFAULT 'publish',
			parent bigint(20) unsigned NOT NULL DEFAULT '0',
			type varchar(20) NOT NULL DEFAULT 'stream',
			connector varchar(255) NOT NULL,
			context varchar(255) NOT NULL,
			action varchar(255) NOT NULL,
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			ip varchar(39) NULL,
			PRIMARY KEY  (ID),
			KEY site_id (site_id),
			KEY blog_id (blog_id),
			KEY parent (parent),
			KEY author (author),
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

		dbDelta( $sql );

		$sql = "CREATE TABLE {$prefix}stream_context (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			record_id bigint(20) unsigned NOT NULL,
			context varchar(100) NOT NULL,
			action varchar(100) NOT NULL,
			connector varchar(100) NOT NULL,
			PRIMARY KEY  (meta_id),
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

		dbDelta( $sql );

		update_site_option( self::KEY, self::$current );

		return $current;
	}
}
