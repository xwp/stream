<?php

class WP_Stream_Legacy_Update {

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
	public static $limit = 500;

	/**
	 * Hold a temporary cache of records to preserve IDs which are required for deletion
	 *
	 * @var array
	 */
	public static $records_raw = array();

	/**
	 * Check that legacy data exists before doing anything
	 *
	 * @return void
	 */
	public static function load() {
		if ( ! is_multisite() && false === get_option( 'wp_stream_db' ) ) {
			return;
		}

		global $wpdb;

		// If there are no legacy tables, then attempt to clear all legacy data and exit early
		if ( null === $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->base_prefix}stream'" ) ) {
			self::drop_legacy_data();

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

		if ( self::show_sync_notice() ) {
			add_action( 'admin_notices', array( __CLASS__, 'sync_notice' ) );
		}

		add_action( 'admin_init', array( __CLASS__, 'process_sync_actions' ) );
	}

	/**
	 * Give the user options for how to handle their legacy Stream records
	 *
	 * @action admin_notices
	 * @return void
	 */
	public static function show_sync_notice() {
		if (
			! isset( $_GET['sync_action'] )
			&&
			WP_Stream::is_connected()
			&&
			WP_Stream_Admin::is_stream_screen()
			&&
			0 !== self::$record_count
			&&
			false === get_transient( self::SYNC_DELAY_TRANSIENT )
		) {
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
		$nonce  = wp_create_nonce( 'stream_sync_action-' . get_current_blog_id() );
		$notice = sprintf(
			'<strong>%s</strong></p><p>%s</p><div id="stream-sync-progress">test</div><p class="stream-sync-actions"><a href="%s" id="stream-start-sync" class="button button-primary">%s</a> <a href="%s" id="stream-sync-reminder" class="button button-secondary">%s</button> <a href="%s" id="stream-delete-records" class="delete">%s</a>',
			esc_html__( 'You have successfully connected to Stream!', 'stream' ),
			esc_html__( 'We found existing Stream records in your database that need to be synced to your Stream account.', 'stream' ),
			add_query_arg(
				array(
					'page'        => WP_Stream_Admin::RECORDS_PAGE_SLUG,
					'sync_action' => 'sync',
					'nonce'       => $nonce,
				),
				admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
			),
			esc_html__( 'Start Syncing Now', 'stream' ),
			add_query_arg(
				array(
					'page'        => WP_Stream_Admin::RECORDS_PAGE_SLUG,
					'sync_action' => 'delay',
					'nonce'       => $nonce,
				),
				admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
			),
			esc_html__( 'Remind Me Later', 'stream' ),
			add_query_arg(
				array(
					'page'        => WP_Stream_Admin::RECORDS_PAGE_SLUG,
					'sync_action' => 'delete',
					'nonce'       => $nonce,
				),
				admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
			),
			esc_html__( 'Delete Existing Records', 'stream' )
		);

		WP_Stream::notice( $notice, false );
	}

	/**
	 * Listens for sync action to process
	 *
	 * @action admin_init
	 * @return void
	 */
	public static function process_sync_actions() {
		$action = wp_stream_filter_input( INPUT_GET, 'sync_action' );
		$nonce  = wp_stream_filter_input( INPUT_GET, 'nonce' );

		if ( ! wp_verify_nonce( $nonce, 'stream_sync_action-' . get_current_blog_id() ) ) {
			return;
		}

		if ( 'sync' === $action ) {
			self::create_chunks();
		}

		if ( 'delay' === $action ) {
			set_transient( self::SYNC_DELAY_TRANSIENT, '1', HOUR_IN_SECONDS * 3 );
		}

		if ( 'delete' === $action ) {
			self::delete_records( self::get_records() );
		}
	}

	/**
	 * Break down the total number of records found into reasonably-sized chunks
	 * and send each of those chunks to the Stream API
	 *
	 * Drops the legacy Stream data from the DB once the API has consumed everything
	 *
	 * @return void
	 */
	public static function create_chunks() {
		$output = array();
		$max    = ceil( self::$record_count / self::$limit );

		// @TODO: Create AJAX callback that returns the success of each chunk to update the progress bar

		for ( $i = 0; $i < $max; $i++ ) {
			$records = self::get_records( absint( self::$limit * $i ) );

			// echo json_encode( $records, JSON_PRETTY_PRINT ); // @TODO Remove this, for testing only

			// self::send_chunk( $records );

			// self::delete_records( self::$records_raw );
		}

		self::drop_legacy_data();
	}

	/**
	 * Get a chunk of records formatted for Stream API ingestion
	 *
	 * @param  int    $offset  The number of rows to skip
	 *
	 * @return array           An array of record arrays
	 */
	public static function get_records( $offset = 0 ) {
		self::$records_raw = array();

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
				self::$limit
			),
			ARRAY_A
		);

		self::$records_raw = $records;

		foreach ( $records as $record => $data ) {
			$stream_meta        = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->base_prefix}stream_meta WHERE record_id = %d", $records[ $record ]['ID'] ), ARRAY_A );
			$stream_meta_output = array();

			foreach ( $stream_meta as $meta => $value ) {
				$stream_meta_output[ $value['meta_key'] ] = maybe_unserialize( $value['meta_value'] );
			}

			$records[ $record ]['stream_meta'] = $stream_meta_output;
			$records[ $record ]['created']     = wp_stream_get_iso_8601_extended_date( strtotime( $records[ $record ]['created'] ) );

			unset( $records[ $record ]['ID'] );
			unset( $records[ $record ]['parent'] );
		}

		return $records;
	}

	/**
	 * Send a JSON chunk of records to the Stream API
	 *
	 * @param  array $records  An array of record arrays
	 *
	 * @return void
	 */
	public static function send_chunk( $records ) {
		// @TODO: Send each chunk to the API via bulk ingestion endpoint
		// @TODO: Create AJAX callback that returns the success of each chunk to update the progress bar
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
			$wpdb->delete( 'stream', array( 'ID' => $record['ID'] ), array( '%d' ) );
			$wpdb->delete( 'stream_context', array( 'record_id' => $record['ID'] ), array( '%d' ) );
			$wpdb->delete( 'stream_meta', array( 'record_id' => $record['ID'] ), array( '%d' ) );
		}
	}

	/**
	 * Drop the legacy Stream tables and options from the database
	 *
	 * @return void
	 */
	public static function drop_legacy_data() {
		global $wpdb;

		if ( is_multisite() ) {
			$stream_site_blog_pairs = $wpdb->get_results( "SELECT site_id, blog_id FROM {$wpdb->base_prefix}stream WHERE type = 'stream'", ARRAY_A );
			$stream_site_blog_pairs = array_unique( array_map( 'self::implode_key_value', $stream_site_blog_pairs ) );
			$wp_site_blog_pairs     = $wpdb->get_results( "SELECT site_id, blog_id FROM {$wpdb->base_prefix}blogs", ARRAY_A );
			$wp_site_blog_pairs     = array_unique( array_map( 'self::implode_key_value', $wp_site_blog_pairs ) );
			$records_exist          = ( array_intersect( $stream_site_blog_pairs, $wp_site_blog_pairs ) ) ? true : false;
		} else {
			$records_exist = $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}stream`" );
		}

		// If records exist for other sites/blogs then don't proceed, unless those sites/blogs have been deleted
		if ( $records_exist ) {
			return;
		}

		// Drop legacy tables
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->base_prefix}stream, {$wpdb->base_prefix}stream_context, {$wpdb->base_prefix}stream_meta" );

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
