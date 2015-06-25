<?php

class WP_Stream_Migrate {

	/**
	 * Hold site API Key
	 *
	 * @var string
	 */
	public static $api_key;

	/**
	 * Hold site UUID
	 *
	 * @var string
	 */
	public static $site_uuid;

	/**
	 * Hold the total number of legacy records found in the cloud
	 *
	 * @var int
	 */
	public static $record_count = 0;

	/**
	 * Limit payload chunks to a certain number of records
	 *
	 * @var int
	 */
	public static $limit = 0;

	/**
	 * Number of chunks required to migrate
	 *
	 * @var int
	 */
	public static $chunks = 0;

	/**
	 * Check that legacy data exists before doing anything
	 *
	 * @return void
	 */
	public static function load() {
		self::$api_key   = get_option( 'wp_stream_site_api_key' );
		self::$site_uuid = get_option( 'wp_stream_site_uuid' );

		// Exit early if disconnected
		if ( ! self::is_connected() ) {
			return;
		}

		self::$record_count = self::get_record_count();

		// Disconnect and exit if no records exist
		if ( empty( self::$record_count ) ) {
			self::disconnect();

			return;
		}

		self::$limit  = apply_filters( 'wp_stream_migrate_chunk_size', 100 );
		self::$chunks = ( self::$record_count > self::$limit ) ? ceil( self::$record_count / self::$limit ) : 1;

		add_action( 'admin_notices', array( __CLASS__, 'migrate_notice' ), 9 );

		add_action( 'wp_ajax_wp_stream_migrate_action', array( __CLASS__, 'migrate_action_callback' ) );
	}

	/**
	 * Are we currently connected to WP Stream?
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return ( ! empty( self::$api_key ) && ! empty( self::$site_uuid ) );
	}

	/**
	 * Disconnect from WP Stream
	 *
	 * @return void
	 */
	public static function disconnect() {
		delete_option( 'wp_stream_site_api_key' );
		delete_option( 'wp_stream_site_uuid' );
		delete_option( 'wp_stream_migrate_chunk' );

		self::$api_key   = false;
		self::$site_uuid = false;
	}

	/**
	 * Get the current chunk number being migrated
	 *
	 * @return int
	 */
	private static function get_current_chunk() {
		return absint( get_option( 'wp_stream_migrate_chunk', 1 ) );
	}

	/**
	 * Search for records
	 *
	 * @param array $query
	 *
	 * @return array|bool Response body on success, or FALSE on failure
	 */
	private static function search( $query = array() ) {
		if ( ! self::is_connected() ) {
			return false;
		}

		$body['sites'] = array( self::$site_uuid );
		$body['query'] = (array) $query;

		$args = array(
			'headers'   => array(
				'Stream-Site-API-Key' => self::$api_key,
				'Content-Type'        => 'application/json',
			),
			'method'    => 'POST',
			'body'      => wp_stream_json_encode( $body ),
			'sslverify' => true,
		);

		$response = wp_safe_remote_request( 'https://api.wp-stream.com/search', $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Get the total number of records found
	 *
	 * @return int
	 */
	private static function get_record_count() {
		$response = self::search( array( 'size' => 0 ) );

		if ( empty( $response->meta->total ) ) {
			return 0;
		}

		return absint( $response->meta->total );
	}

	/**
	 * Get a chunk of records
	 *
	 * @param int $limit
	 * @param int $offset (optional)
	 *
	 * @return array|bool An array of record arrays, or FALSE if no records were found
	 */
	private static function get_records( $limit = null, $offset = 0 ) {
		$limit = is_int( $limit ) ? $limit : self::$limit;

		$query = array(
			'size' => absint( $limit ),
			'from' => absint( $offset ),
		);

		$response = self::search( $query );

		if ( empty( $response->records ) ) {
			return false;
		}

		return $response->records;
	}

	/**
	 * Determine where and when the migrate notice should be displayed
	 *
	 * @see WP_Stream_Admin::admin_enqueue_scripts()
	 *
	 * @return bool
	 */
	public static function show_migrate_notice() {
		if (
			! isset( $_GET['migrate_action'] )
			&&
			self::is_connected()
			&&
			WP_Stream_Admin::is_stream_screen()
			&&
			! empty( self::$record_count )
			&&
			false === get_transient( 'wp_stream_migrate_delayed' )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Give the user options for how to handle their records
	 *
	 * @action admin_notices
	 *
	 * @return void
	 */
	public static function migrate_notice() {
		if ( ! self::show_migrate_notice() ) {
			return;
		}

		$notice = sprintf(
			'<strong id="stream-migrate-title">%s</strong></p><p><a href="#" target="_blank">%s</a></p><p id="stream-migrate-message">%s</p><div id="stream-migrate-progress"><progress value="0" max="100"></progress> <strong>0&#37;</strong> <em></em> <button id="stream-migrate-actions-close" class="button button-secondary">%s</button><div class="clear"></div></div><p id="stream-migrate-actions"><button id="stream-start-migrate" class="button button-primary">%s</button> <button id="stream-migrate-reminder" class="button button-secondary">%s</button> <a href="#" id="stream-delete-records" class="delete">%s</a>',
			__( 'Our cloud storage services will be shutting down permanently on September 1, 2015', 'stream' ),
			__( 'Read the announcement post', 'stream' ),
			sprintf( esc_html__( 'We found %s activity records in the cloud that need to be migrated to your local database.', 'stream' ), number_format( self::$record_count ) ),
			__( 'Close', 'stream' ),
			__( 'Start Migration Now', 'stream' ),
			__( 'Remind Me Later', 'stream' ),
			__( 'No thanks, just delete my cloud records now', 'stream' )
		);

		WP_Stream::notice( $notice, true );
	}

	/**
	 * Ajax callback for processing migrate actions
	 *
	 * Break down the total number of records found into reasonably-sized chunks
	 * and save records from each of those chunks to the local DB.
	 *
	 * Disconnects from WP Stream once the migration is complete.
	 *
	 * @action wp_ajax_wp_stream_migrate_action
	 *
	 * @return void
	 */
	private static function migrate_action_callback() {
		$action = wp_stream_filter_input( INPUT_POST, 'migrate_action' );
		$nonce  = wp_stream_filter_input( INPUT_POST, 'nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_stream_migrate-' . absint( get_current_blog_id() ) . absint( get_current_user_id() ) ) ) {
			return;
		}

		set_time_limit( 0 ); // Just in case, this could take a while for some

		if ( 'migrate' === $action ) {
			self::migrate();
		}

		if ( 'delay' === $action ) {
			self::delay();
		}

		if ( 'delete' === $action ) {
			self::delete();
		}

		die();
	}

	/**
	 * Migrate a chunk of records
	 *
	 * @return void
	 */
	private static function migrate() {
		$chunk   = self::get_current_chunk();
		$offset  = ( $chunk - 1 ) * self::$limit;
		$records = self::get_records( self::$limit, $offset );

		// Disconnect when complete
		if ( empty( $records ) || $chunk > self::$chunks ) {
			self::disconnect();

			wp_send_json_success( esc_html__( 'Migration complete!', 'stream' ) );
		}

		$records_saved = self::save_records( $records );

		if ( true !== $records_saved ) {
			wp_send_json_error( esc_html__( 'An unknown error occurred during migration. Please try again later or contact support.', 'stream' ) );

			// @TODO: Provide better error messages during self::save_records()
		}

		// Records have been saved, move on to the next chunk
		update_option( 'wp_stream_migrate_chunk', absint( $chunk + 1 ) );

		wp_send_json_success( 'continue' );
	}

	/**
	 * Delay the migration of records
	 *
	 * @return void
	 */
	private static function delay() {
		set_transient( 'wp_stream_migrate_delayed', "Don't nag me, bro", HOUR_IN_SECONDS * 3 );

		wp_send_json_success( esc_html__( "OK, we'll remind you again in a few hours.", 'stream' ) );
	}

	/**
	 * Don't migrate any records
	 *
	 * @return void
	 */
	private static function delete() {
		wp_send_json_success( esc_html__( 'Your records will not be migrated. Thank you for using Stream!', 'stream' ) );
	}

	/**
	 * Save records to the database
	 *
	 * @param array $records
	 *
	 * @return bool
	 */
	private static function save_records( $records ) {
		return true;

		// @TODO: Save records to the local DB
	}

}
