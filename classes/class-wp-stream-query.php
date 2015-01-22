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
	 * @param array Query args
	 *
	 * @return array Stream Records
	 */
	public function query( $args ) {
		global $wpdb;

		$defaults = array(
			// Search param
			'search'                => null,
			'search_field'          => 'summary',
			'record_after'          => null, // Deprecated, use date_after instead
			// Date-based filters
			'date'                  => null, // Ex: 2014-02-17
			'date_from'             => null, // Ex: 2014-02-17
			'date_to'               => null, // Ex: 2014-02-17
			'date_after'            => null, // Ex: 2014-02-17T15:19:21+00:00
			'date_before'           => null, // Ex: 2014-02-17T15:19:21+00:00
			// Record ID filters
			'record'                => null,
			'record__in'            => array(),
			'record__not_in'        => array(),
			// Pagination params
			'records_per_page'      => get_option( 'posts_per_page', 20 ),
			'paged'                 => 1,
			// Order
			'order'                 => 'desc',
			'orderby'               => 'date',
			// Meta/Taxonomy sub queries
			'meta'                  => array(),
			// Data aggregations
			'aggregations'          => array(),
			// Fields selection
			'fields'                => null,
		);

		// Additional property fields
		$properties = array(
			'type'          => 'stream',
			'author'        => null,
			'author_role'   => null,
			'ip'            => null,
			'object_id'     => null,
			'site_id'       => null,
			'blog_id'       => null,
			'visibility'    => 'publish',
			'connector'     => null,
			'context'       => null,
			'action'        => null,
		);

		/**
		 * Filter allows additional query properties to be added
		 *
		 * @since 2.0.0
		 *
		 * @return array  Array of query properties
		 */
		$properties = apply_filters( 'wp_stream_query_properties', $properties );

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
		 * @since 1.4.0
		 *
		 * @return array  Array of query arguments
		 */
		$args = apply_filters( 'wp_stream_query_args', $args );

		$query   = array();
		$filters = array();
		$fields  = array();

		// PARSE SEARCH
		if ( $args['search'] ) {
			if ( $args['search_field'] ) {
				$search_field = $args['search_field'];
				$query['query']['match'][ $search_field ] = $args['search'];
			} else {
				$query['query']['match']['summary'] = $args['search'];
			}
		}

		// PARSE FIELDS
		if ( $args['fields'] ) {
			$fields = is_array( $args['fields'] ) ? $args['fields'] : explode( ',', $args['fields'] );
		}

		// PARSE DATE
		if ( $args['date_from'] ) {
			$filters[]['range']['created']['gte'] = wp_stream_get_iso_8601_extended_date( strtotime( $args['date_from']  . ' 00:00:00' ), get_option( 'gmt_offset' ) );
		}

		if ( $args['date_to'] ) {
			$filters[]['range']['created']['lte'] = wp_stream_get_iso_8601_extended_date( strtotime( $args['date_to']  . ' 23:59:59' ), get_option( 'gmt_offset' ) );
		}

		// Support deprecated argument replaced by date_after
		if ( $args['record_after'] && ! $args['date_after'] ) {
			$args['date_after'] = $args['record_after'];
		}

		if ( $args['date_after'] ) {
			$filters[]['range']['created']['gt'] = wp_stream_get_iso_8601_extended_date( strtotime( $args['date_after'] ) );
		}

		if ( $args['date_before'] ) {
			$filters[]['range']['created']['lt'] = wp_stream_get_iso_8601_extended_date( strtotime( $args['date_before'] ) );
		}

		if ( $args['date'] ) {
			$filters[]['range']['created'] = array(
				'gte' => wp_stream_get_iso_8601_extended_date( strtotime( $args['date'] . ' 00:00:00' ), get_option( 'gmt_offset' ) ),
				'lte' => wp_stream_get_iso_8601_extended_date( strtotime( $args['date'] . ' 23:59:59' ), get_option( 'gmt_offset' ) ),
			);
		}

		// PARSE RECORD
		if ( $args['record'] ) {
			$filters[]['ids']['values'] = array( $args['record'] );
		}

		if ( $args['record__in'] && is_array( $args['record__in'] ) ) {
			$values = is_array( $args['record__in'] ) ? $args['record__in'] : array_map( 'trim', explode( ',', $args['record__in'] ) );

			$filters[]['ids']['values'] = $values;
		}

		if ( $args['record__not_in'] && is_array( $args['record__not_in'] ) ) {
			$values = is_array( $args['record__not_in'] ) ? $args['record__not_in'] : array_map( 'trim', explode( ',', $args['record__not_in'] ) );

			$filters[]['not']['ids']['values'] = $values;
		}

		// PARSE PROPERTIES
		foreach ( $properties as $property => $default ) {
			if ( $args[ $property ] ) {
				$filters[]['term'][ $property ] = $args[ $property ];
			}

			if ( $args[ "{$property}__in" ] ) {
				$values      = is_array( $args[ "{$property}__in" ] ) ? $args[ "{$property}__in" ] : array_map( 'trim', explode( ',', $args[ "{$property}__in" ] ) );
				$property_in = array();
				foreach ( $values as $value ) {
					$property_in[]['term'][ $property ] = $value;
				}
				$filters[]['or'] = $property_in;
			}

			if ( $args[ "{$property}__not_in" ] ) {
				$values          = is_array( $args[ "{$property}__not_in" ] ) ? $args[ "{$property}__not_in" ] : array_map( 'trim', explode( ',', $args[ "{$property}__not_in" ] ) );
				$property_not_in = array();
				foreach ( $values as $value ) {
					$property_not_in[]['not']['term'][ $property ] = $value;
				}
				$filters[]['or'] = $property_not_in;
			}
		}

		// PARSE PAGINATION
		if ( $args['records_per_page'] ) {
			if ( $args['records_per_page'] >= 0 ) {
				$query['size'] = (int) $args['records_per_page'];
			} else {
				$query['size'] = 999999; // Actual limit placed on "unlimited" results
			}
		} else {
			$query['size'] = get_option( 'posts_per_page', 20 );
		}

		if ( $args['paged'] ) {
			$query['from'] = ( (int) $args['paged'] - 1 ) * $query['size'];
		}

		// PARSE ORDER
		$query['sort'] = array();

		$orderby = ! empty( $args['orderby'] ) ? $args['orderby'] : 'created';
		$order   = ! empty( $args['order'] ) ? $args['order'] : 'desc';

		if ( 'date' === $orderby ) {
			$orderby = 'created';
		}

		$query['sort'][][ $orderby ]['order'] = $order;

		// PARSE META
		if ( $args['meta'] ) {
			$meta = (array) $args['meta'];
			foreach ( $meta as $key => $values ) {
				if ( ! is_array( $values ) ) {
					$values = (array) $values;
				}
				$filters[]['nested'] = array(
					'path'   => 'stream_meta',
					'filter' => array(
						'terms' => array(
							$key => $values,
						),
					),
				);
			}
		}

		// PARSE AGGREGATIONS
		if ( ! empty( $args['aggregations'] ) ) {
			foreach ( $args['aggregations'] as $aggregation_term ) {
				$query['aggregations'][ $aggregation_term ]['terms']['field'] = $aggregation_term;
			}
		}

		// Add filters to query
		if ( ! empty( $filters ) ) {
			if ( count( $filters ) > 1 ) {
				$query['filter']['and'] = $filters;
			} else {
				$query['filter'] = current( $filters );
			}
		}

		/**
		 * Filter allows the final query args to be modified
		 *
		 * @since 2.0.0
		 *
		 * @return array  Array of query arguments
		 */
		$query = apply_filters( 'wp_stream_db_query', $query );

		/**
		 * Filter allows the final query fields to be modified
		 *
		 * @since 2.0.0
		 *
		 * @return array  Array of query fields
		 */
		$fields = apply_filters( 'wp_stream_db_fields', $fields );

		/**
		 * Query results
		 *
		 * @var array
		 */
		return WP_Stream::$db->query( $query, $fields );
	}
}
