<?php

class WP_Stream_Legacy_Update {

	/**
	 * @var int Total number of legacy records found in the DB
	 */
	public static $record_count = 0;

	/**
	 * @var int Limit payload chunks to a certain number of records
	 */
	public static $limit = 100;

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

		self::send_chunks();
	}

	public static function send_chunks() {
		$output = array();
		$max    = ceil( self::$record_count / self::$limit );

		// @TODO: Create AJAX callback that returns the success of each chunk to update the progress bar

		for ( $i = 0; $i < $max; $i++ ) {
			$chunk  = self::get_chunk( absint( self::$limit * $i ) );

			// @TODO: Send chunk to the API via bulk ingestion endpoint

			echo json_encode( $chunk, JSON_PRETTY_PRINT ); // @TODO Remove this, for testing only
		}
	}

	public static function get_chunk( $offset = 0 ) {
		global $wpdb;

		$records = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}stream WHERE type = 'stream' ORDER BY created ASC LIMIT %d, %d", $offset, self::$limit ), ARRAY_A );

		foreach ( $records as $record => $data ) {
			$stream_contexts    = $wpdb->get_row( $wpdb->prepare( "SELECT connector, context, action FROM {$wpdb->prefix}stream_context WHERE record_id = %d", $records[ $record ]['ID'] ), ARRAY_A );
			$stream_meta        = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->prefix}stream_meta WHERE record_id = %d", $records[ $record ]['ID'] ), ARRAY_A );
			$stream_meta_output = array();

			foreach ( $stream_meta as $meta => $value ) {
				$stream_meta_output[ $value['meta_key'] ] = maybe_unserialize( $value['meta_value'] );
			}

			$records[ $record ]['connector']   = $stream_contexts['connector'];
			$records[ $record ]['context']     = $stream_contexts['context'];
			$records[ $record ]['action']      = $stream_contexts['action'];
			$records[ $record ]['stream_meta'] = $stream_meta_output;
			$records[ $record ]['created']     = wp_stream_get_iso_8601_extended_date( strtotime( $records[ $record ]['created'] ) );

			unset( $records[ $record ]['ID'] );
		}

		return $records;
	}

}
