<?php

abstract class WP_Stream_DB_Base {

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
		$data     = wp_parse_args( $data, $defaults );

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
	 * @return int|WP_Error ID of the inserted record
	 */
	abstract protected function insert( array $data );

	/**
	 * Query records
	 *
	 * @internal Used by WP_Stream_Query, and is not designed to be called explicitly
	 *
	 * @param array $query Query arguments
	 *
	 * @return array List of records that match query
	 */
	abstract public function query( $query );

	/**
	 * Get count of total found rows ( with no limit/paging ) for the last run query
	 *
	 * @return mixed NULL if no queries are done yet, or count of total records of the last query
	 */
	abstract public function get_found_rows();

	/**
	 * Get unique values of a specific column/field
	 *
	 * @param string $column Column name
	 *
	 * @return array Array of distinct existing values for specified column
	 */
	abstract public function get_col( $column );

	/**
	 * Retrieve metadata of a single record
	 *
	 * @internal User by wp_stream_get_meta()
	 *
	 * @param integer $record_id Record ID
	 * @param string  $key       Optional, Meta key, if omitted, retrieve all meta data of this record.
	 * @param boolean $single    Default: false, Return single meta value, or all meta values under specified key.
	 *
	 * @return string|array Single/Array of meta data.
	 */
	abstract public function get_meta( $record_id, $key, $single = false );
}
