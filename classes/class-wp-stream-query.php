<?php

class WP_Stream_Query {

	public static $instance;

	/**
	 * @return WP_Stream_Query
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

	/**
	 * Query Stream records
	 *
	 * @param  array|string $args Query args
	 * @return array              Stream Records
	 */
	public function query( $args ) {
		global $wpdb;

		$defaults = array(
			// Search param
			'search'                => null,
			'search_field'          => 'summary',
			'distinct'              => null,
			'record_greater_than'   => null,
			// Date-based filters
			'date'                  => null,
			'date_from'             => null,
			'date_to'               => null,
			// Pagination params
			'records_per_page'      => get_option( 'posts_per_page' ),
			'paged'                 => 1,
			// Order
			'order'                 => 'desc',
			'orderby'               => 'date',
			// Meta/Taxonomy sub queries
			'meta'                  => array(),
			// Fields selection
			'fields'                => null,
		);

		// Additional property fields
		$properties = array(
			'record'        => null,
			'type'          => 'stream',
			'record_parent' => null,
			'author'        => null,
			'author_role'   => null,
			'ip'            => null,
			'object_id'     => null,
			'site_id'       => null,
			'blog_id'       => null,
			'visibility'    => null,
			'connector'     => null,
			'context'       => null,
			'action'        => null,
		);

		// Add property fields to defaults, including their __in/__not_in variations
		foreach ( $properties as $property => $default ) {
			if ( ! isset( $defaults[ $property ] ) ) {
				$defaults[ $property ] = $default;
			}
			$defaults[ "{$property}__in" ]     = array();
			$defaults[ "{$property}__not_in" ] = array();
		}

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter allows additional arguments to query $args
		 *
		 * @param  array  Array of query arguments
		 * @return array  Updated array of query arguments
		 */
		$args = apply_filters( 'wp_stream_query_args', $args );

		$query  = array();
		$fields = array();

		// PARSE SEARCH
		if ( ! empty( $args['search'] ) ) {
			if ( ! empty( $args['search_field'] ) ) {
				$search_field = $args['search_field'];
				$query['query']['match'][ $search_field ] = $args['search'];
			} else {
				$query['query']['match']['summary'] = $args['search'];
			}
		}

		// PARSE FIELDS
		if ( ! empty( $args['fields'] ) ) {
			$fields = is_array( $args['fields'] ) ? $args['fields'] : explode( ',', $args['fields'] );
		}
		$fields[] = 'created';
		$fields[] = 'summary';

		// PARSE DISTINCT
		if ( true === $args['distinct'] && ! empty( $args['search_field'] ) ) {
			$search_field = $args['search_field'];
			$query['aggs'][ $search_field ]['terms']['field'] = $search_field;
		}

		// PARSE DATE
		if ( ! empty( $args['date_from'] ) ) {
			$query['query']['filter']['range']['created']['from'] = date( 'c', strtotime( $args['date_from'] . ' 00:00:00' ) );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$query['query']['filter']['range']['created']['to'] = date( 'c', strtotime( $args['date_to'] . ' 23:59:59' ) );
		}

		if ( ! empty( $args['date'] ) ) {
			$query['query']['filter']['range']['created']['from'] = date( 'c', strtotime( $args['date'] . ' 00:00:00' ) );
			$query['query']['filter']['range']['created']['to']   = date( 'c', strtotime( $args['date'] . ' 23:59:59' ) );
		}

		// PARSE RECORD
		if ( ! empty( $args['record__in'] ) ) {
			$query['query']['filter']['ids']['values'] = (array) $args['record__in'];
		}

		if ( ! empty( $args['record__in'] ) ) {
			$query['query']['filter']['ids']['values'] = (array) $args['record__in'];
		}

		if ( ! empty( $args['record__not_in'] ) ) {
			$query['query']['filter']['not']['ids']['values'] = (array) $args['record__not_in'];
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
				$query['query']['filter']['term'][ $property ] = $args[ $property ];
			}

			if ( ! empty( $args["{$property}__in"] ) ) {
				$query['query']['filter']['term'][ $property ] = $args["{$property}__in"];
			}

			if ( ! empty( $args["{$property}__not_in"] ) ) {
				$query['query']['filter']['not']['term'][ $property ] = $args["{$property}__in"];
			}
		}

		// PARSE PAGINATION
		if ( ! empty( $args['records_per_page'] ) ) {
			$query['size'] = (int) $args['records_per_page'];
		} else {
			$query['size'] = get_option( 'posts_per_page', 20 );
		}

		if ( ! empty( $args['paged'] ) ) {
			$query['from'] = ( (int) $args['paged'] - 1 ) * $query['size'];
		}

		// PARSE ORDER
		$query['sort'] = array();

		$orderby = ! empty( $args['orderby'] ) ? $args['orderby'] : 'created';
		$order   = ! empty( $args['order'] ) ? $args['order'] : 'desc';

		$query['sort'] = $orderby . '.' . strtolower( $order );

		// PARSE META


		if ( ! isset( $query['query'] ) || empty( $query['query'] ) ) {
			$query['query']['match_all'] = new stdClass();
		}

		$query  = apply_filters( 'wp_stream_db_query', $query );
		$fields = apply_filters( 'wp_stream_db_fields', $fields );

		/**
		 * Query results
		 * @var  array
		 */
		return WP_Stream::$db->query( $query, $fields );
	}
}
