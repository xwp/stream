<?php

abstract class WP_Stream_DB_Base {

	abstract function install();

	abstract function query( $args );

	/**
	 * Store a record
	 *
	 * Inserts/Updates records in DB
	 *
	 * @param  array $data  Record data
	 * @return mixed       Record ID if successful, WP_Error if not
	 */
	function store( $data ) {
		// Take only what's ours!
		$valid_keys = get_class_vars( 'WP_Stream_Record' );
		$data       = array_intersect_key( $data, $valid_keys );
		$data       = array_filter( $data );

		/**
		 * Filter allows modification of record information
		 *
		 * @param  array  array of record information
		 * @return array  udpated array of record information
		 */
		$data = apply_filters( 'wp_stream_record_array', $data );

		// Allow extensions to handle the saving process
		if ( empty( $data ) ) {
			return;
		}

		// Fill in defaults
		$defaults = array(
			'type' => 'stream',
			'site_id' => 1,
			'blog_id' => 0,
			'object_id' => null,
			'author' => 0,
			'author_role' => '',
			'visibility' => 'publish',
			'parent' => 0,
		);
		$data = wp_parse_args( $data, $defaults );

		// TODO: Check/Validate *required* fields

		if ( isset( $data['ID'] ) ) {
			$result = $this->update( $data );

			// TODO: provide actions/filters on result
			return $result;
		} else {
			$result = $this->insert( $data );

			if ( is_wp_error( $result ) ) {
				/**
				 * Fires on errors during post insertion
				 *
				 * @param  string  DB Error encountered
				 */
				do_action( 'wp_stream_post_insert_error', $result->get_error_message() );

				return $result;
			} else {
				/**
				 * Fires when A Post is inserted
				 *
				 * @param  int    Inserted record ID
				 * @param  array  Array of information on this record
				 */
				do_action( 'wp_stream_post_inserted', $result, $data );

				return $result; // record_id
			}
		}
	}

	abstract protected function insert( $data );

	abstract protected function update( $data );

	abstract function delete( $args );

	abstract function reset();

	abstract function get_col( $column );

	abstract function get_found_rows();

}
