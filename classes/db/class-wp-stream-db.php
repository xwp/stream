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
		$query_dsl = array();
		$fields    = array();

		// PARSE SEARCH
		if ( ! empty( $args['search'] ) ) {
			if ( ! empty( $args['search_field'] ) ) {
				$search_field = $args['search_field'];
				$query_dsl['query']['match'][ $search_field ] = $args['search'];
			} else {
				$query_dsl['query']['match']['summary'] = $args['search'];
			}
		}

		// PARSE FIELDS
		if ( ! empty( $args['fields'] ) ) {
			$fields = is_array( $args['fields'] ) ? $args['fields'] : explode( ',', $args['fields'] );
		}

		// PARSE DISTINCT
		if ( true === $args['distinct'] && ! empty( $args['search_field'] ) ) {
			$search_field = $args['search_field'];
			$query_dsl['aggs'][ $search_field ]['terms']['field'] = $search_field;
		}

		// PARSE DATE
		if ( ! empty( $args['date_from'] ) ) {
			$query_dsl['filter']['range']['created']['from'] = date( 'c', strtotime( $args['date_from'] . ' 00:00:00' ) );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$query_dsl['filter']['range']['created']['to'] = date( 'c', strtotime( $args['date_to'] . ' 23:59:59' ) );
		}

		if ( ! empty( $args['date'] ) ) {
			$query_dsl['filter']['range']['created']['from'] = date( 'c', strtotime( $args['date'] . ' 00:00:00' ) );
			$query_dsl['filter']['range']['created']['to']   = date( 'c', strtotime( $args['date'] . ' 23:59:59' ) );
		}

		// PARSE RECORD
		if ( ! empty( $args['record__in'] ) ) {
			$query_dsl['filter']['ids']['values'] = (array) $args['record__in'];
		}

		if ( ! empty( $args['record__in'] ) ) {
			$query_dsl['filter']['ids']['values'] = (array) $args['record__in'];
		}

		if ( ! empty( $args['record__not_in'] ) ) {
			$query_dsl['filter']['not']['ids']['values'] = (array) $args['record__not_in'];
		}

		$properties = array(
			'author',
			'author_role',
			'ip',
			'type',
			'record_parent',
			'object_id',
			'site_id',
			'blog_id',
			'visibility',
			'connector',
			'context',
			'action',
		);

		foreach ( $properties as $property ) {
			if ( ! empty( $args[ $property ] ) ) {
				$query_dsl['filter']['term'][ $property ] = $args[ $property ];
			}

			if ( ! empty( $args["{$property}__in"] ) ) {
				$query_dsl['filter']['term'][ $property ] = $args["{$property}__in"];
			}

			if ( ! empty( $args["{$property}__not_in"] ) ) {
				$query_dsl['filter']['not']['term'][ $property ] = $args["{$property}__in"];
			}
		}

		// PARSE PAGINATION
		if ( ! empty( $args['records_per_page'] ) ) {
			$query_dsl['size'] = (int) $args['records_per_page'];
		} else {
			$query_dsl['size'] = get_option( 'posts_per_page', 20 );
		}

		if ( ! empty( $args['paged'] ) ) {
			$query_dsl['from'] = ( (int) $args['paged'] - 1 ) * $query_dsl['size'];
		}

		// PARSE ORDER
		$query_dsl['sort'] = array();

		$orderby = ! empty( $args['orderby'] ) ? $args['orderby'] : 'created';
		$order   = ! empty( $args['order'] ) ? $args['order'] : 'desc';

		if ( 'date' === $orderby ) {
			$orderby = 'created';
		}

		$query_dsl['sort'][][ $orderby ]['order'] = strtolower( $order );

		// PARSE META

		if ( ! isset( $query_dsl['query'] ) || empty( $query_dsl['query'] ) ) {
			$query_dsl['query']['match_all'] = new stdClass();
		}

		$query_dsl = apply_filters( 'wp_stream_db_query_dsl', $query_dsl );
		$fields    = apply_filters( 'wp_stream_db_fields', $fields );

		$response = WP_Stream::$api->search( $query_dsl, $fields );

		$this->found_rows = $response->meta->total;

		$results = array();

		if ( true === $args['distinct'] && isset( $args['search_field'] ) ) {
			$search_field = $args['search_field'];
			foreach ( $search->aggregations->$search_field->buckets as $bucket ) {
				$results[] = $bucket->key;
			}
		} else {
			$results = $response->records;
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
