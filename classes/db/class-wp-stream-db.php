<?php

class WP_Stream_DB extends WP_Stream_DB_Base {

	public $found_rows;

	public function __construct() {
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
			return $meta;
		} else {
			return array( $key => $meta );
		}
	}
}

WP_Stream::$db = new WP_Stream_DB();
