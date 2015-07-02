<?php

class WP_Stream_Install {

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
	 * @static
	 *
	 * @var string
	 */
	public static $table_prefix;

	/**
	 * Holds version of database at last update
	 *
	 * @access public
	 * @static
	 *
	 * @var string
	 */
	public static $db_version;

	/**
	 * URL to the Stream Admin settings page.
	 *
	 * @access public
	 * @static
	 *
	 * @var string
	 */
	public static $stream_url;

	/**
	 * Array of version numbers that require database update
	 *
	 * @access public
	 * @static
	 *
	 * @var array
	 */
	public static $update_versions;

	/**
	 * Holds status of whether it's safe to run Stream or not
	 *
	 * @access public
	 * @static
	 *
	 * @var bool
	 */
	public static $update_required = false;

	/**
	 * Holds status of whether the database update worked
	 *
	 * @access public
	 * @static
	 *
	 * @var bool
	 */
	public static $success_db;

	/**
	 * Hold class instance
	 *
	 * @access private
	 * @static
	 *
	 * @var WP_Stream_Install
	 */
	private static $instance;

	/**
	 * Return an active instance of this class, and create one if it doesn't exist
	 *
	 * @access public
	 * @static
	 *
	 * @return WP_Stream_Install
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor
	 *
	 * @access private
	 */
	private function __construct() {
		self::$db_version = self::get_db_version();
		self::$stream_url = self_admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE . '&page=' . WP_Stream_Admin::SETTINGS_PAGE_SLUG );

		global $wpdb;

		/**
		 * Allows devs to alter the tables prefix, default to base_prefix
		 *
		 * @param string $prefix
		 *
		 * @return string
		 */
		self::$table_prefix = apply_filters( 'wp_stream_db_tables_prefix', $wpdb->base_prefix );

		self::check();
	}

	/**
	 * Check db version, create/update table schema accordingly
	 * If database update required admin notice will be given
	 * on the plugin update screen
	 *
	 * @access private
	 * @static
	 *
	 * @return void
	 */
	private static function check() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( empty( self::$db_version ) ) {
			self::install( WP_Stream::VERSION );

			return;
		}

		if ( self::$db_version === WP_Stream::VERSION ) {
			return;
		}

		$update = isset( $_REQUEST['wp_stream_update'] ) ? $_REQUEST['wp_stream_update'] : null;

		if ( ! $update ) {
			self::$update_required = true;
			self::$success_db      = self::update( self::$db_version, WP_Stream::VERSION, array( 'type' => 'auto' ) );

			return;
		}

		if ( 'update_and_continue' === $update ) {
			self::$success_db = self::update( self::$db_version, WP_Stream::VERSION, array( 'type' => 'user' ) );
		}

		$versions = self::db_update_versions();

		if ( version_compare( end( $versions ), self::$db_version, '>' ) ) {
			add_action( 'all_admin_notices', array( __CLASS__, 'update_notice_hook' ) );

			return;
		}

		self::update_db_option();
	}

	/**
	 *
	 *
	 * @access public
	 * @static
	 *
	 * @return string
	 */
	public static function get_db_version() {
		global $wpdb;

		$version = get_site_option( self::KEY );

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
	 *
	 *
	 * @access public
	 * @static
	 *
	 * @return void
	 */
	public static function update_db_option() {
		if ( self::$success_db ) {
			$success_op = update_site_option( self::KEY, WP_Stream::VERSION );
		}

		if ( ! empty( self::$success_db ) && ! empty( $success_op ) ) {
			return;
		}

		wp_die(
			__( 'There was an error updating the Stream database. Please try again.', 'stream' ),
			__( 'Database Update Error', 'stream' ),
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}

	/**
	 * Added to the admin_notices hook when file plugin version is higher than database plugin version
	 *
	 * @action admin_notices
	 *
	 * @access public
	 * @static
	 *
	 * @return void
	 */
	public static function update_notice_hook() {
		if ( ! current_user_can( WP_Stream_Admin::VIEW_CAP ) ) {
			return;
		}

		$update = isset( $_REQUEST['wp_stream_update'] ) ? $_REQUEST['wp_stream_update'] : null;

		if ( ! $update ) {
			self::prompt_update();

			return;
		}

		if ( 'update_and_continue' === $update ) {
			self::prompt_update_status();
		}
	}

	/**
	 * Action hook callback function
	 *
	 * Adds the user controlled database upgrade routine to the plugins updated page.
	 * When database update is complete page will refresh with dismissible message to user.
	 *
	 * @access public
	 * @static
	 *
	 * @return void
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
	 * @access public
	 * @static
	 *
	 * @return void
	 */
	public static function prompt_update_status() {
		check_admin_referer( 'wp_stream_update_db' );

		self::update_db_option();
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
	 * @access public
	 * @static
	 *
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
	 * @access public
	 * @static
	 *
	 * @param int   $db_version
	 * @param int   $current_version
	 * @param array $update_args
	 *
	 * @return mixed Version number on success, true on no update needed, mysql error message on error
	 */
	public static function update( $db_version, $current_version, $update_args ) {
		$versions = self::db_update_versions();

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
	 * @access private
	 * @static
	 * @uses dbDelta()
	 *
	 * @param string $current_version
	 *
	 * @return string
	 */
	private static function install( $current_version ) {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$prefix = self::$table_prefix;

		$sql = "CREATE TABLE {$prefix}stream (
			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id bigint(20) unsigned NOT NULL DEFAULT '1',
			blog_id bigint(20) unsigned NOT NULL DEFAULT '0',
			object_id bigint(20) unsigned NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT '0',
			user_role varchar(20) NOT NULL DEFAULT '',
			summary longtext NOT NULL,
			visibility varchar(20) NOT NULL DEFAULT 'publish',
			parent bigint(20) unsigned NOT NULL DEFAULT '0',
			type varchar(20) NOT NULL DEFAULT 'record',
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			connector varchar(100) NOT NULL,
			context varchar(100) NOT NULL,
			action varchar(100) NOT NULL,
			ip varchar(39) NULL,
			PRIMARY KEY  (ID),
			KEY site_id (site_id),
			KEY blog_id (blog_id),
			KEY user_id (user_id),
			KEY parent (parent),
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

		update_site_option( self::KEY, WP_Stream::VERSION );

		return $current_version;
	}
}
