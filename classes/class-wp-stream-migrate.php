<?php

class WP_Stream_Migrate {

	/**
	 * Sync delay transient name/identifier used when user wants to be reminded to sync later
	 */
	const SYNC_DELAY_TRANSIENT = 'wp_stream_sync_delayed';

	/**
	 * Hold the current site ID
	 *
	 * @var int
	 */
	public static $site_id = 1;

	/**
	 * Hold the current blog ID
	 *
	 * @var int
	 */
	public static $blog_id = 1;

	/**
	 * Hold the total number of legacy records found in the DB
	 *
	 * @var int
	 */
	public static $record_count = 0;

	/**
	 * Limit payload chunks to a certain number of records
	 *
	 * @var int
	 */
	public static $limit = 1000;

	/**
	 * Check that legacy data exists before doing anything
	 *
	 * @return void
	 */
	public static function load() {
		// Exit early if there is no option holding the DB version
		if ( false === get_site_option( 'wp_stream_db' ) ) {
			return;
		}

		global $wpdb;

		// If there are no legacy tables found, then attempt to clear all legacy data and exit early
		if ( null === $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->base_prefix}stream'" ) ) {
			self::drop_legacy_data( false );
			return;
		}

		self::$site_id      = is_multisite() ? get_current_site()->id : 1;
		self::$blog_id      = is_network_admin() ? 0 : get_current_blog_id();
		self::$record_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->base_prefix}stream` WHERE site_id = %d AND blog_id = %d AND type = 'stream'", self::$site_id, self::$blog_id ) );

		// If there are no legacy records for this site/blog, then attempt to clear all legacy data and exit early
		if ( 0 === self::$record_count ) {
			self::drop_legacy_data();
			return;
		}

		add_action( 'admin_notices', array( __CLASS__, 'sync_notice' ), 9 );

		add_action( 'wp_ajax_wp_stream_sync_action', array( __CLASS__, 'process_sync_action' ) );
	}

	/**
	 * Give the user options for how to handle their legacy Stream records
	 *
	 * @action admin_notices
	 * @return void
	 */
	public static function show_sync_notice() {
		if ( ! isset( $_GET['sync_action'] ) && WP_Stream::is_connected() && WP_Stream_Admin::is_stream_screen() && ! empty( self::$record_count ) && false === get_transient( self::SYNC_DELAY_TRANSIENT ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Give the user options for how to handle their legacy Stream records
	 *
	 * @action admin_notices
	 * @return void
	 */
	public static function sync_notice() {
		if ( ! self::show_sync_notice() ) {
			return;
		}

		$notice = sprintf(
			'<strong>%s</strong></p><p>%s</p><div id="stream-sync-progress"><span class="spinner"></span> <strong>%s</strong> <em></em> <button id="stream-sync-actions-close" class="button button-secondary">%s</button><div class="clear"></div></div><p id="stream-sync-actions"><button id="stream-start-sync" class="button button-primary">%s</button> <button id="stream-sync-reminder" class="button button-secondary">%s</button> <a href="#" id="stream-delete-records" class="delete">%s</a>',
			__( 'Sync Stream Records', 'stream' ),
			__( 'We found existing Stream records in your database that need to be synced to your Stream account.', 'stream' ),
			__( 'Please do not exit this page until the process has completed.', 'stream' ),
			__( 'Close', 'stream' ),
			__( 'Start Syncing Now', 'stream' ),
			__( 'Remind Me Later', 'stream' ),
			__( 'Delete Existing Records', 'stream' )
		);

		WP_Stream::notice( $notice, false );
	}

	/**
	 * Ajax callback for processing sync actions
	 *
	 * @action wp_ajax_wp_stream_sync_action
	 * @return void
	 */
	public static function process_sync_action() {
		$action = wp_stream_filter_input( INPUT_POST, 'sync_action' );
		$nonce  = wp_stream_filter_input( INPUT_POST, 'nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_stream_sync-' . absint( get_current_blog_id() ) . absint( get_current_user_id() ) ) ) {
			return;
		}

		set_time_limit( 0 ); // These could take a while

		if ( 'sync' === $action ) {
			self::migrate_notification_rules();
			self::process_chunks( 'sync' );

			wp_send_json_success( __( 'Syncing complete!', 'stream' ) );
		}

		if ( 'delay' === $action ) {
			set_transient( self::SYNC_DELAY_TRANSIENT, "Don't nag me, bro", HOUR_IN_SECONDS * 3 );

			wp_send_json_success( __( "OK, we'll remind you again in a few hours.", 'stream' ) );
		}

		if ( 'delete' === $action ) {
			self::process_chunks( 'delete' );

			wp_send_json_success( __( 'All existing records have been deleted from the database.', 'stream' ) );
		}

		die();
	}

	public static function migrate_notification_rules() {
		global $wpdb;

		$rules = $wpdb->get_results(
			$wpdb->prepare( "
				SELECT *
				FROM {$wpdb->base_prefix}stream
				WHERE site_id = %d
					AND blog_id = %d
					AND type = 'notification_rule'
				ORDER BY created DESC
				",
				self::$site_id,
				self::$blog_id
			),
			ARRAY_A
		);

		if ( empty( $rules ) ) {
			return;
		}

		foreach ( $rules as $rule => $data ) {
			$rule_post_args = array();
			$rule_post_meta = array();

			// Set args for the new rule post
			$rule_post_args['post_title']     = $rules[ $rule ]['summary'];
			$rule_post_args['post_type']      = WP_Stream_Notifications_Post_Type::POSTTYPE;
			$rule_post_args['post_status']    = ( 'active' === $rules[ $rule ]['visibility'] ) ? 'publish' : 'draft';
			$rule_post_args['post_date']      = get_date_from_gmt( $rules[ $rule ]['created'] );
			$rule_post_args['post_date_gmt']  = $rules[ $rule ]['created']; // May not work, known bug in WP, see workaround below
			$rule_post_args['comment_status'] = 'closed';
			$rule_post_args['ping_status']    = 'closed';

			// Get rule meta
			$stream_rule_meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->base_prefix}stream_meta WHERE record_id = %d", $rules[ $rule ]['ID'] ), ARRAY_A );

			// Prepare meta values for rule post meta
			foreach ( $stream_rule_meta as $meta => $value ) {
				$rule_post_meta[ $value['meta_key'] ] = maybe_unserialize( $value['meta_value'] );
			}

			// Get rule option, which is automatically unserialized
			$stream_rule_option = get_option( 'stream_notifications_' . absint( $rules[ $rule ]['ID'] ) );

			// Prepare option values for rule post meta
			$rule_post_meta['triggers'] = isset( $stream_rule_option['triggers'] ) ? $stream_rule_option['triggers'] : array();
			$rule_post_meta['groups']   = isset( $stream_rule_option['groups'] )   ? $stream_rule_option['groups']   : array();
			$rule_post_meta['alerts']   = isset( $stream_rule_option['alerts'] )   ? $stream_rule_option['alerts']   : array();

			// Insert rule as a new post
			$post_id = wp_insert_post( $rule_post_args );

			// Workaround to fix bug in wp_insert_post() not honoring the `post_date_gmt` arg
			// See: https://core.trac.wordpress.org/ticket/15946
			$wpdb->update( $wpdb->prefix . 'posts', array( 'post_date_gmt' => $rules[ $rule ]['created'] ), array( 'ID' => $post_id ), array( '%s' ), array( '%d' ) );

			// Save the rule post meta
			foreach ( $rule_post_meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}

			// Delete the old option
			delete_option( 'stream_notifications_' . absint( $rules[ $rule ]['ID'] ) );
		}

		// No need for chunks since there likely won't be more than a few dozen rules
		self::delete_records( $rules );
	}

	/**
	 * Break down the total number of records found into reasonably-sized chunks
	 * and send each of those chunks to the Stream API
	 *
	 * Drops the legacy Stream data from the DB once the API has consumed everything
	 *
	 * @param  string $action  How the chunks should be processed
	 *
	 * @return void
	 */
	public static function process_chunks( $action ) {
		$max = ceil( self::$record_count / self::$limit );

		for ( $i = 1; $i <= $max; $i++ ) {
			// Send records in chunks
			if ( 'sync' === $action ) {
				$records = self::get_records( self::$limit );

				WP_Stream::$db->store( $records );

				self::delete_records( $records );
			}

			// Delete records in chunks
			if ( 'delete' === $action ) {
				$records = self::get_records( self::$limit, 0, false );

				self::delete_records( $records );
			}
		}

		self::drop_legacy_data();
	}

	/**
	 * Get a chunk of records formatted for Stream API ingestion
	 *
	 * @param  int  $limit   The number of rows to query, 500 by default
	 * @param  int  $offset  The number of rows to skip, 0 by default
	 * @param  bool $format  Whether or not the output should be formatted for cloud ingestion, true by default
	 *
	 * @return array  An array of record arrays
	 */
	public static function get_records( $limit = 1000, $offset = 0, $format = true ) {
		$limit = is_int( $limit ) ? $limit : self::$limit;

		global $wpdb;

		$records = $wpdb->get_results(
			$wpdb->prepare( "
				SELECT s.*, sc.connector, sc.context, sc.action
				FROM {$wpdb->base_prefix}stream AS s, {$wpdb->base_prefix}stream_context AS sc
				WHERE s.site_id = %d
					AND s.blog_id = %d
					AND s.type = 'stream'
					AND sc.record_id = s.ID
				ORDER BY s.created DESC
				LIMIT %d, %d
				",
				self::$site_id,
				self::$blog_id,
				$offset,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $records ) ) {
			return;
		}

		foreach ( $records as $record => $data ) {
			$stream_meta        = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->base_prefix}stream_meta WHERE record_id = %d", $records[ $record ]['ID'] ), ARRAY_A );
			$stream_meta_output = array();

			foreach ( $stream_meta as $meta => $value ) {
				$stream_meta_output[ $value['meta_key'] ] = maybe_unserialize( $value['meta_value'] );
			}

			$records[ $record ]['stream_meta'] = $stream_meta_output;

			if ( $format ) {
				$records[ $record ]['created'] = wp_stream_get_iso_8601_extended_date( strtotime( $records[ $record ]['created'] ) );

				unset( $records[ $record ]['ID'] );
				unset( $records[ $record ]['parent'] );

				// If the object_id is null, set it to 0
				$records[ $record ]['object_id'] = is_null( $records[ $record ]['object_id'] ) ? 0 : $records[ $record ]['object_id'];

				// If the array value is numeric then sanitize it from a string to an int
				array_walk_recursive(
					$records[ $record ],
					function( &$v ) {
						$v = is_numeric( $v ) ? intval( $v ) : $v; // A negative int could potentially exist as meta
					}
				);
			}
		}

		return $records;
	}

	/**
	 * Drop the legacy Stream records from the database for the current site/blog
	 *
	 * @param  array $records  An array of record arrays
	 *
	 * @return void
	 */
	public static function delete_records( $records ) {
		if ( empty( $records ) ) {
			return;
		}

		global $wpdb;

		// Delete legacy rows from each Stream table for these records only
		foreach ( $records as $record ) {
			if ( ! isset( $record['ID'] ) ) {
				continue;
			}

			$wpdb->delete( $wpdb->base_prefix . 'stream', array( 'ID' => $record['ID'] ), array( '%d' ) );
			$wpdb->delete( $wpdb->base_prefix . 'stream_context', array( 'record_id' => $record['ID'] ), array( '%d' ) );
			$wpdb->delete( $wpdb->base_prefix . 'stream_meta', array( 'record_id' => $record['ID'] ), array( '%d' ) );
		}
	}

	/**
	 * Drop the legacy Stream tables and options from the database
	 *
	 * @param bool $drop_tables  If true, attempt to drop the legacy Stream tables
	 *
	 * @return void
	 */
	public static function drop_legacy_data( $drop_tables = true ) {
		global $wpdb;

		if ( $drop_tables ) {
			if ( is_multisite() ) {
				$stream_site_blog_pairs = $wpdb->get_results( "SELECT site_id, blog_id FROM {$wpdb->base_prefix}stream WHERE type = 'stream'", ARRAY_A );
				$stream_site_blog_pairs = array_unique( array_map( 'self::implode_key_value', $stream_site_blog_pairs ) );
				$wp_site_blog_pairs     = $wpdb->get_results( "SELECT site_id, blog_id FROM {$wpdb->base_prefix}blogs", ARRAY_A );
				$wp_site_blog_pairs     = array_unique( array_map( 'self::implode_key_value', $wp_site_blog_pairs ) );
				$records_exist          = ( array_intersect( $stream_site_blog_pairs, $wp_site_blog_pairs ) ) ? true : false;
			} else {
				$records_exist = $wpdb->get_var( "SELECT * FROM `{$wpdb->prefix}stream` LIMIT 1" );
			}

			// If records exist for other sites/blogs then don't proceed, unless those sites/blogs have been deleted
			if ( $records_exist ) {
				return;
			}

			// Drop legacy tables
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->base_prefix}stream, {$wpdb->base_prefix}stream_context, {$wpdb->base_prefix}stream_meta" );
		}

		// Delete legacy multisite options
		if ( is_multisite() ) {
			$blogs = wp_get_sites();

			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				delete_option( plugin_basename( WP_STREAM_DIR ) . '_db' ); // Deprecated option key
				delete_option( 'wp_stream_db' );
				delete_option( 'wp_stream_license' );
				delete_option( 'wp_stream_licensee' );
			}

			restore_current_blog();
		}

		// Delete legacy options
		delete_site_option( plugin_basename( WP_STREAM_DIR ) . '_db' ); // Deprecated option key
		delete_site_option( 'wp_stream_db' );
		delete_site_option( 'wp_stream_license' );
		delete_site_option( 'wp_stream_licensee' );

		// Delete legacy transients
		delete_transient( 'wp_stream_extensions_' );

		// Delete legacy cron event hooks
		wp_clear_scheduled_hook( 'stream_auto_purge' ); // Deprecated hook
		wp_clear_scheduled_hook( 'wp_stream_auto_purge' );
	}

	/**
	 * Callback to impode key/value pairs from an associative array into a specially-formatted string
	 *
	 * @param  array  $array  An associate array
	 *
	 * @return string $output
	 */
	public static function implode_key_value( $array ) {
		$output = implode( ', ',
			array_map(
				function ( $v, $k ) {
					return sprintf( '%s:%s', $k, $v );
				},
				$array,
				array_keys( $array )
			)
		);

		return $output;
	}

}
