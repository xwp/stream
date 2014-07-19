<?php

class WP_Stream_DB {

	public $found_rows;

	/**
	 * Store a record
	 *
	 * Inserts/Updates (based on ID existence) a single record in DB
	 *
	 * @param  array $data Record data
	 *
	 * @return mixed        Record ID if inserted successful, True if updated, false|WP_Error if not
	 */
	public function store( $data ) {
		// Take only what's ours!
		$valid_keys = get_class_vars( 'WP_Stream_Record' );
		$data       = array_intersect_key( $data, $valid_keys );
		$data       = array_filter( $data );

		/**
		 * Filter allows modification of record information
		 *
		 * @param  array $data Array of record information
		 *
		 * @return array  $data Updated array of record information
		 */
		$data = apply_filters( 'wp_stream_record_array', $data );

		// Allow extensions to handle the saving process
		if ( empty( $data ) ) {
			return false;
		}

		// Fill in defaults
		$defaults = array(
			'type'        => 'stream',
			'site_id'     => 1,
			'blog_id'     => 0,
			'object_id'   => null,
			'author'      => 0,
			'author_role' => '',
			'visibility'  => 'publish',
			'parent'      => 0,
		);

		$data = wp_parse_args( $data, $defaults );

		// TODO: Check/Validate *required* fields

		$result = $this->insert( $data );

		if ( is_wp_error( $result ) ) {
			/**
			 * Fires on errors during post insertion
			 *
			 * @param  string $errors DB Error encountered
			 */
			do_action( 'wp_stream_post_insert_error', $result->get_error_message() );

			return $result;
		} else {
			/**
			 * Fires when A Post is inserted
			 *
			 * @param  int   $result Inserted record ID
			 * @param  array $data   Array of information on this record
			 */
			do_action( 'wp_stream_post_inserted', $result, $data );

			return $result; // record_id
		}
	}

	/**
	 * Insert a new record
	 *
	 * @internal Used by store()
	 *
	 * @param array $data Record data
	 *
	 * @return int ID of the inserted record
	 */
	protected function insert( array $data ) {

		return true;
	}

	/**
	 * Query records
	 *
	 * @internal Used by WP_Stream_Query, and is not designed to be called explicitly
	 *
	 * @param  array $query  Query body.
	 * @param  array $fields Returns specified fields only.
	 *
	 * @return array List of records that match query
	 */
	public function query( $query, $fields ) {
		$response = WP_Stream::$api->search( $query, $fields );

		if ( empty( $response ) ) {
			return false;
		}

		$this->found_rows = $response->meta->total;

		$results = (array) $response->records;

		/**
		 * Allows developers to change the final result set of records
		 *
		 * @param array $results Query result
		 *
		 * @return array Filtered array of records
		 */
		return apply_filters( 'wp_stream_query_results', $results );
	}

	/**
	 * Get total count of the last query using query() method
	 *
	 * @return integer Total item count
	 */
	public function get_found_rows() {
		return $this->found_rows;
	}

	/**
	 * Returns array of existing values for requested column.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * @param string Requested Column (i.e., 'context')
	 *
	 * @return array Array of distinct values
	 */
	public function get_col( $column ) {
		$values = array();

		return $values;
	}

	/**
	 * Retrieve metadata of a single record
	 *
	 * @internal User by wp_stream_get_meta()
	 *
	 * @param  integer $record_id Record ID
	 * @param  string  $key       Optional, Meta key, if omitted, retrieve all meta data of this record.
	 * @param  boolean $single    Default: false, Return single meta value, or all meta values under specified key.
	 *
	 * @return string|array       Single/Array of meta data.
	 */
	public function get_meta( $record_id, $key = '', $single = false ) {
		$record = WP_Stream::$api->get_record( $record_id );

		if ( ! empty( $key ) ) {
			$meta = $record->stream_meta->$key;
		} else {
			$meta = $record->stream_meta;
		}

		if ( $single ) {
			return (array) $meta;
		} else {
			return array( $key => $meta );
		}
	}
}
