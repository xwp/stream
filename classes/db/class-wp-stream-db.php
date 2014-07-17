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
	 * @param  array $args Query arguments
	 *
	 * @return array List of records that match query
	 */
	public function query( $args ) {
		$query  = array();
		$aggs   = array();
		$fields = array();

		// PARSE SEARCH
		if ( isset( $args['search'] ) ) {
			if ( isset( $args['search_field'] ) ) {
				$search_field = $args['search_field'];
				$query['filtered']['query']['match'][ $search_field ] = $args['search'];
			} else {
				$query['filtered']['query']['match']['_all'] = $args['search'];
			}
		}

		// PARSE FIELDS
		if ( isset( $args['fields'] ) ) {
			$fields = is_array( $args['fields'] ) ? $args['fields'] : explode( ',', $args['fields'] );
		}

		// PARSE DISTINCT
		if ( true === $args['distinct'] && isset( $args['search_field'] ) ) {
			$search_field = $args['search_field'];
			$aggs[ $search_field ] = array(
				'terms' => array(
					'field' => $search_field,
				),
			);
		}

		// PARSE DATE
		if ( isset( $args['date_from'] ) ) {
			$query['filtered']['filter']['range']['created']['from']['gte'] = date( 'Y-m-d H:i:s', strtotime( $args['date_from'] . ' 00:00:00' ) );
		}

		if ( isset( $args['date_to'] ) ) {
			$query['filtered']['filter']['range']['created']['from']['lte'] = date( 'Y-m-d H:i:s', strtotime( $args['date_to'] . ' 23:59:59' ) );
		}

		if ( isset( $args['date'] ) ) {
			$query['filtered']['filter']['range']['created']['from']['gte'] = date( 'Y-m-d H:i:s', strtotime( $args['date'] . ' 00:00:00' ) );
			$query['filtered']['filter']['range']['created']['from']['lte'] = date( 'Y-m-d H:i:s', strtotime( $args['date'] . ' 23:59:59' ) );
		}

		// PARSE RECORD
		if ( isset( $args['record__in'] ) ) {
			$query['filtered']['filter']['ids']['values'] = (array) $args['record__in'];
		}

		if ( isset( $args['record__not_in'] ) ) {
			$query['filtered']['filter']['not']['ids']['values'] = (array) $args['record__not_in'];
		}

		if ( isset( $args['record_parent'] ) ) {
			$query['filtered']['filter']['term']['parent'] = $args['record_parent'];
		}

		if ( isset( $args['record_parent__in'] ) ) {
			$query['filtered']['filter']['term']['parent'] = (array) $args['record_parent__in'];
		}

		if ( isset( $args['record_parent__not_in'] ) ) {
			$query['filtered']['filter']['not']['term']['parent'] = (array) $args['record_parent__not_in'];
		}

		// PARSE AUTHOR
		if ( isset( $args['author__in'] ) ) {
			$query['filtered']['filter']['term']['author'] = (array) $args['author__in'];
		}

		if ( isset( $args['author__not_in'] ) ) {
			$query['filtered']['filter']['not']['term']['author'] = (array) $args['author__not_in'];
		}

		if ( isset( $args['author_role__in'] ) ) {
			$query['filtered']['filter']['term']['author_role'] = (array) $args['author_role__in'];
		}

		if ( isset( $args['author_role__not_in'] ) ) {
			$query['filtered']['filter']['not']['term']['author_role'] = (array) $args['author_role__not_in'];
		}

		// PARSE IP
		if ( isset( $args['ip__in'] ) ) {
			$query['filtered']['filter']['term']['ip'] = (array) $args['ip__in'];
		}

		if ( isset( $args['ip__not_in'] ) ) {
			$query['filtered']['filter']['not']['term']['ip'] = (array) $args['ip__not_in'];
		}

		// PARSE PAGINATION

		// PARSE ORDER

		// PARSE META

		$query = apply_filters( 'wp_stream_db_query', $query );
		$aggs  = apply_filters( 'wp_stream_db_aggs', $aggs );

		$response = WP_Stream::$api->search( $query, $aggs, $fields );

		$this->found_rows = $response->meta->total;

		$results = array();

		if ( true === $args['distinct'] && isset( $args['search_field'] ) ) {
			$search_field = $args['search_field'];
			foreach ( $search->aggregations->$search_field->buckets as $bucket ) {
				$results[] = $bucket->key;
			}
		} else {
			$results = $response->hits;
		}

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
