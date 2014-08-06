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
	 * Check that
	 *
	 * @return void
	 */
	public static function load() {
		global $wpdb;

		if ( null === $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}stream'" ) ) {
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
	 * Break down the total number of records into reasonably-sized chunks
	 *
	 * @return void
	 */
	public static function create_chunks() {
		$output = array();
		$max    = ceil( self::$record_count / self::$limit );

		// @TODO: Create AJAX callback that returns the success of each chunk to update the progress bar

		for ( $i = 0; $i < $max; $i++ ) {
			$records  = self::get_records( absint( self::$limit * $i ) );

			echo json_encode( $records, JSON_PRETTY_PRINT ); // @TODO Remove this, for testing only

			// self::send_chunk( $records );
		}

		// self::drop_legacy_tables()
	}

	/**
	 * Send a chunk of records to the Stream API
	 *
	 * @return void
	 */
	public static function send_chunk( $records ) {
		// @TODO: Send each chunk to the API via bulk ingestion endpoint
		// @TODO: Create AJAX callback that returns the success of each chunk to update the progress bar
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
	 * Drop the legacy Stream MySQL tables
	 *
	 * @return void
	 */
	public static function drop_legacy_tables() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}stream, {$wpdb->prefix}stream_context, {$wpdb->prefix}stream_meta" );
	}

}
