<?php

class WP_Stream_Install {

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
	 * Is plugin network activated
	 * 
	 * @var bool
	 */
	public static $network;

	/**
	 * Array of plugin version numbers that require db update
	 * 
	 * @var array
	 */
	public static $db_update_versions;

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
	 * @return bool|object|WP_Stream_Install
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
		self::$current = WP_Stream::VERSION;
		if ( is_plugin_active_for_network( WP_STREAM_DIR ) ) {
			self::$db_version = get_site_option( plugin_basename( WP_STREAM_DIR ) . '_db' );
			self::$network    = true;
		} else {
			self::$db_version = get_option( plugin_basename( WP_STREAM_DIR ) . '_db' );
			self::$network    = false;
		}
		self::$db_version = get_option( plugin_basename( WP_STREAM_DIR ) . '_db' );
		self::$stream_url = self_admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE . '&page=' . WP_Stream_Admin::SETTINGS_PAGE_SLUG );
		self::$db_update_versions = WP_Stream::get_update_versions();

		/**
		 * Allows devs to alter the tables prefix, default to base_prefix
		 *
		 * @var string $prefix  database prefix
		 * @var string $table_prefix udpated database prefix
		 */
		$prefix = $wpdb->prefix;

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
		if ( empty( self::$db_version ) ) {
			$current = self::install( self::$current );

		} elseif ( self::$db_version !== self::$current ) {
			add_action( 'admin_notices', array( __CLASS__, 'update_notice_hook' ) );
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
			<form method="post" action="<?php echo esc_url( remove_query_arg( 'wp_stream_update', wp_get_referer() ) ) ?>">
				<input type="hidden" name="wp_stream_update" value="update_and_continue"/>
				<p><strong><?php esc_html_e( 'Stream Database Update Required', 'stream' ) ?></strong></p>
				<p><?php esc_html_e( 'Before we send you on your way, we have to update your database to the newest version.', 'stream' ) ?></p>
				<p><?php esc_html_e( 'The update process may take a little while, so please be patient.', 'stream' ) ?></p>
				<?php submit_button( __( 'Update Database', 'stream' ) ) ?>
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
		$success_db = self::update( self::$db_version, self::$current );

		if ( $success_db && self::$current === $success_db ) {
			$success_op = update_option( plugin_basename( WP_STREAM_DIR ) . '_db', $success_db );
		}

		if ( empty( $success_db ) || empty( $success_op ) ) {
			wp_die( __( 'There was an error updating the Stream database. Please try again.', 'stream' ), 'Database Update Error', array( 'response' => 200, 'back_link' => true ) );
		}
		?>
		<div class="updated">
			<form method="post" action="<?php echo esc_url( remove_query_arg( 'wp_stream_update' ), wp_get_referer() ) ?>" style="display:inline;">
				<p><strong><?php esc_html_e( 'Update Complete', 'stream' ) ?></strong></p>
				<p><?php esc_html_e( sprintf( 'Your Stream database has been successfully updated from %1$s to %2$s!', self::$db_version, self::$current ), 'stream' ) ?></p>
				<?php submit_button( __( 'Continue', 'stream' ), 'submit', false ) ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Added to the admin_notices hook when file plugin version is higher than database plugin version
	 *
	 * @action admin_notices
	 * @return void
	 */
	public static function update_notice_hook() {
		if ( ! isset( $_REQUEST['wp_stream_update'] ) ) {
			self::prompt_update();

		} elseif ( 'user_action_required' === $_REQUEST['wp_stream_update' ] ) {
			self::prompt_update_status();

		} elseif ( 'update_and_continue' === $_REQUEST['wp_stream_update'] ) {
			self::prompt_update_status();
		}
	}

	/**
	 * Initial database install routine
	 * @uses dbDelta()
	 */
	public static function install( $current ) {
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

		return $current;
	}

	/**
	 * Database user controlled update routine
	 *
	 * @param int $db_version last updated version of database stored in plugin options
	 * @param int $current Current running plugin version
	 * @return int new database version to store
	 */
	public static function update( $db_version, $current ) {
		global $wpdb;
		$prefix = self::$table_prefix;

		foreach ( self::$db_update_versions as $version ) {

			if ( version_compare( $db_version, $version, '<' ) ) {
				$version_func = str_ireplace( '.', '', $version );
				call_user_func( array( __CLASS__, 'update'. $version_func ), $prefix, $wpdb );
			}
		}

		return $current;

	}


	public static function update_114( $prefix, $wpdb ) {
		global $wpdb;

		if ( ! empty( $wpdb->carset ) ) {
			return;
		}

		$tables  = array( 'stream', 'stream_context', 'stream_meta' );
		$collate = ( $wpdb->collate ) ? " COLLATE {$wpdb->collate}" : null;
		foreach ( $tables as $table ) {
			$wpdb->query( "ALTER TABLE {$prefix}{$table} CONVERT TO CHARACTER SET {$wpdb->charset}{$collate};" );
		}
	}

	public static function update_117( $prefix, $wpdb ) {
		if ( ! empty( $wpdb->carset ) ) {
			return;
		}
		$wpdb->query( "ALTER TABLE {$prefix}stream MODIFY ip varchar(39) NULL AFTER created" );
	}

	public static function update_125( $prefix, $wpdb ) {
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

	public static function update_128( $prefix, $wpdb ) {
		$sql = "SELECT r.ID id, r.object_id pid, c.meta_id mid
				FROM $wpdb->stream r
				JOIN $wpdb->streamcontext c
					ON r.ID = c.record_id AND c.connector = 'media' AND c.context = 'media'
				";
		$media_records = $wpdb->get_results( $sql ); // db call ok

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

	public static function update_130( $prefix, $wpdb ) {
		add_action( 'wp_stream_after_connectors_registration', array( __CLASS__, 'migrate_old_options_to_exclude_tab' ) );
	}

	public static function update_131( $prefix, $wpdb ) {
		add_action( 'wp_stream_after_connectors_registration', array( __CLASS__, 'migrate_installer_edits_to_theme_editor_connector' ) );
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
			$file_name = get_stream_meta( $record->ID, 'file', true );
			$theme_name = get_stream_meta( $record->ID, 'name', true );

			if ( '' !== $theme_name ) {
				$matched_themes = array_filter(
					wp_get_themes(),
					function ( $theme ) use ( $theme_name ) {
						return (string)$theme === $theme_name;
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
