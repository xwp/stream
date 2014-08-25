<?php

class WP_Stream_DB {

	/**
	 * Meta information returned in the last query
	 *
	 * @var mixed
	 */
	public $query_meta = false;

	/**
	 * Store records
	 *
	 * @param  array Records
	 *
	 * @return mixed True if updated, false|WP_Error if not
	 */
	public function store( $records ) {
		// Take only what's ours!
		$valid_keys = get_class_vars( 'WP_Stream_Record' );

		// Fill in defaults
		$defaults = array(
			'type'        => 'stream',
			'site_id'     => 1,
			'blog_id'     => 0,
			'object_id'   => null,
			'author'      => 0,
			'author_role' => '',
			'visibility'  => 'publish',
		);

		foreach ( $records as $key => $record ) {
			$records[ $key ] = array_intersect_key( $record, $valid_keys );
			$records[ $key ] = array_filter( $record );
			$records[ $key ] = wp_parse_args( $record, $defaults );
		}

		/**
		 * Filter allows modification of record information
		 *
		 * @param  array $records Array of record information
		 *
		 * @return array $records Updated array of record information
		 */
		$records = apply_filters( 'wp_stream_record_array', $records );

		// Allow extensions to handle the saving process
		if ( empty( $records ) ) {
			return false;
		}

		// TODO: Check/Validate *required* fields

		$this->insert( $records );

		return true;
	}

	/**
	 * Insert a new record
	 *
	 * @internal Used by store()
	 *
	 * @param array   $records  Record data
	 *
	 * @return object $response The inserted records
	 */
	private function insert( array $records ) {
		WP_Stream::$api->new_records( $records );
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

		$this->query_meta = $response->meta;

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
		if ( ! isset( $this->query_meta->total ) ) {
			return 0;
		}
		return $this->query_meta->total;
	}

	/**
	 * Get meta data for last query using query() method
	 *
	 * @return array Meta data for query
	 */
	public function get_query_meta() {
		return $this->query_meta;
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
	public function get_record_meta( $record_id, $key = '', $single = false ) {
		$record = WP_Stream::$api->get_record( $record_id );

		if ( ! isset( $record->stream_meta ) ) {
			return array();
		}

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

	/**
	 * Returns array of existing values for requested field.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * @param string Requested field (i.e., 'context')
	 *
	 * @return array Array of distinct values
	 */
	public function get_distinct_field_values( $field ) {
		$query['aggregations']['fields']['terms']['field'] = $field;

		$values   = array();
		$response = WP_Stream::$api->search( $query, array( $field ) );

		if ( isset( $response->meta->aggregations->fields->buckets ) ) {
			foreach ( $response->meta->aggregations->fields->buckets as $field ) {
				$values[] = $field->key;
			}
		}

		return $values;
	}
}
