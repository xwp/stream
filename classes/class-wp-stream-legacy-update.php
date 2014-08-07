<?php

class WP_Stream_Legacy_Update {

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
	 * Check that legacy data exists before doing anything
	 *
	 * @return void
	 */
	public static function load() {

		// @TODO: Make this whole process multisite compat

		if ( false === get_option( 'wp_stream_db' ) ) {
			return;
		}

		global $wpdb;

		if ( null === $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}stream'" ) ) {
			// If there are no legacy records, then clear the legacy options again
			self::drop_legacy_data();
			return;
		}

		self::$record_count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}stream` WHERE type = 'stream'" );

		if ( 0 === self::$record_count ) {
			return;
		}

		// @TODO: Create admin notice that a database migration is needed

		self::create_chunks();
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
			$records  = self::get_records( absint( self::$limit * $i ) );

			//echo json_encode( $records, JSON_PRETTY_PRINT ); // @TODO Remove this, for testing only

			// self::send_chunk( $records );
		}

		// self::drop_legacy_data();
	}

	/**
	 * Get a chunk of records formatted for Stream API ingestion
	 *
	 * @param  int    The number of rows to skip
	 *
	 * @return array  An array of record arrays
	 */
	public static function get_records( $offset = 0 ) {
		global $wpdb;

		$records = $wpdb->get_results(
			$wpdb->prepare( "
				SELECT s.*, sc.connector, sc.context, sc.action
				FROM {$wpdb->prefix}stream AS s, {$wpdb->prefix}stream_context AS sc
				WHERE s.type = 'stream'
					AND sc.record_id = s.ID
				ORDER BY s.created ASC
				LIMIT %d, %d
				",
				$offset,
				self::$limit
			),
			ARRAY_A
		);

		foreach ( $records as $record => $data ) {
			// @TODO Figure out a way to eliminate this additional query using a JOIN above, if possible
			$stream_meta        = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->prefix}stream_meta WHERE record_id = %d", $records[ $record ]['ID'] ), ARRAY_A );
			$stream_meta_output = array();

			foreach ( $stream_meta as $meta => $value ) {
				$stream_meta_output[ $value['meta_key'] ] = maybe_unserialize( $value['meta_value'] );
			}

			$records[ $record ]['stream_meta'] = $stream_meta_output;
			$records[ $record ]['created']     = wp_stream_get_iso_8601_extended_date( strtotime( $records[ $record ]['created'] ) );

			unset( $records[ $record ]['ID'] );
		}

		return $records;
	}

	/**
	 * Send a JSON chunk of records to the Stream API
	 *
	 * @return void
	 */
	public static function send_chunk( $records ) {
		// @TODO: Send each chunk to the API via bulk ingestion endpoint
		// @TODO: Create AJAX callback that returns the success of each chunk to update the progress bar
	}

	/**
	 * Drop the legacy Stream tables and options from the database
	 *
	 * @return void
	 */
	public static function drop_legacy_data() {
		global $wpdb;

		// Drop legacy tables
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}stream, {$wpdb->prefix}stream_context, {$wpdb->prefix}stream_meta" );

		// Delete legacy multisite options
		if ( is_multisite() ) {
			$blogs = wp_get_sites();

			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				delete_option( plugin_basename( WP_STREAM_DIR ) . '_db' ); // Deprecated option key
				delete_option( 'wp_stream_db' );
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

}
