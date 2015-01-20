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
	 * @param  array $records
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
			'object_id'   => 0,
			'author'      => 0,
			'author_role' => '',
			'visibility'  => 'publish',
			'ip'          => '',
		);

		foreach ( $records as $key => $record ) {
			$records[ $key ] = array_intersect_key( $record, $valid_keys );
			$records[ $key ] = array_filter( $record );
			$records[ $key ] = wp_parse_args( $record, $defaults );
		}

		/**
		 * Allows modification of record information just before logging occurs.
		 *
		 * @since 0.2.0
		 *
		 * @param  array $records An array of record data.
		 */
		$records = apply_filters( 'wp_stream_record_array', $records );

		// Allow extensions to handle the saving process
		if ( empty( $records ) ) {
			return false;
		}

		// TODO: Check/Validate *required* fields

		$result = $this->insert( $records );

		if ( $result && ! is_wp_error( $result ) ) {

			/**
			 * Fires when A Post is inserted
			 *
			 * @since 2.0.0
			 *
			 * @param  int    $record_id  Inserted record ID
			 * @param  array  $recordarr  Array of information on this record
			 */
			do_action( 'wp_stream_records_inserted', $records );
		}

		return true;
	}

	/**
	 * Insert a new record
	 *
	 * @internal Used by store()
	 *
	 * @param array   $records
	 *
	 * @return object $response The inserted records
	 */
	private function insert( array $records ) {
		return WP_Stream::$api->new_records( $records );
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

		if ( empty( $response ) || ! isset( $response->meta ) || ! isset( $response->records ) ) {
			return false;
		}

		$this->query_meta = $response->meta;

		$results = (array) $response->records;

		/**
		 * Allows developers to change the final result set of records
		 *
		 * @since 2.0.0
		 *
		 * @param array $results
		 * @param array $query
		 * @param array $fields
		 *
		 * @return array Filtered array of record results
		 */
		return apply_filters( 'wp_stream_query_results', $results, $query, $fields );
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
