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
			'record_greater_than'   => null,
			'distinct'              => null,
			// Date-based filters
			'date'                  => null,
			'date_from'             => null,
			'date_to'               => null,
			// __in params
			'record_greater_than'   => null,
			'record__in'            => array(),
			'record__not_in'        => array(),
			'record_parent'         => '',
			'record_parent__in'     => array(),
			'record_parent__not_in' => array(),
			'author__in'            => array(),
			'author__not_in'        => array(),
			'author_role__in'       => array(),
			'author_role__not_in'   => array(),
			'ip__in'                => array(),
			'ip__not_in'            => array(),
			// Pagination params
			'records_per_page'      => get_option( 'posts_per_page' ),
			'paged'                 => 1,
			// Order
			'order'                 => 'desc',
			'orderby'               => 'ID',
			// Meta/Taxonomy sub queries
			'meta'                  => array(),
			// Fields selection
			'fields'                => '*',
		);

		// Stream core fields filtering
		$columns = array(
			'type'          => array(
				'default' => 'stream',
			),
			'object_id'     => array(
				'is_int'  => true,
			),
			'ip'            => array(),
			'site_id'       => array(
				'default' => is_multisite() ? get_current_site()->id : 1,
				'is_int'  => true,
			),
			'blog_id'       => array(
				'default' => is_network_admin() ? null : get_current_blog_id(),
				'is_int'  => true,
			),
			'author'        => array(
				'is_int' => true,
			),
			'author_role'   => array(
				'is_int' => true,
			),
			'visibility'    => array(
				'default' => 'publish',
			),
			'record'        => array(
				'column' => 'ID',
			),
			'record_parent' => array(
				'column' => 'parent',
			),
			'connector'     => array(),
			'context'       => array(),
			'action'        => array(),
		);

		// Add core fields to defaults, including their __in/__not_in variations
		foreach ( $columns as $_field => $_def ) {
			if ( ! isset( $defaults[ $_field ] ) ) {
				$defaults[ $_field ] = isset( $_def['default'] ) ? $_def['default'] : null;
			}
			$defaults[ "{$_field}__in" ]     = null; // Null makes `isset` return false
			$defaults[ "{$_field}__not_in" ] = null;
		}

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter allows additional arguments to query $args
		 *
		 * @param  array  Array of query arguments
		 * @return array  Updated array of query arguments
		 */
		$args = apply_filters( 'wp_stream_query_args', $args );

		$query = array();

		/**
		 * PARSE CORE FILTERS, PLUS __IN / __NOT_IN VARIATIONS
		 */
		foreach ( $columns as $field => $def ) {
			$is_int = isset( $def['is_int'] );
			$column = isset( $def['column'] ) ? $def['column'] : $field;

			// Parse basic filter, eg: author=1
			// Allow 0 if is meant to be an integer
			if ( $args[ $field ] || ( $is_int && 0 === $args[ $field ] ) ) {
				// Sanitize if is meant to be an integer
				$value = $is_int ? (int) $args[ $field ] : $args[ $field ];
				// MySQL optimiser will transform the IN to the = when IN is just one element
				$query[ $column ]['in'] = array( $value );

			} else { // If field=value exists, do not parse __in/__not_in variations

				// Parse __in/__not_in variations
				foreach ( array( 'in', 'not_in' ) as $operator ) {
					if ( isset( $args[ "{$field}__{$operator}" ] ) ) {
						$values = $args[ "{$field}__{$operator}" ];
						// Values are supposed to be arrays
						if ( ! is_array( $values ) ) {
							$values = explode( ',', $values );
						}
						// Sanitize integer values
						if ( $is_int ) {
							$values = array_filter( $values, 'is_numeric' );
						}
						// Bail if no values passed validation
						if ( ! empty( $values ) ) {
							$query[ $column ][ $operator ] = $values;
						}
					}
				}
			}
		}

		/**
		 * PARSE CUSTOM CORE FILTERS
		 */
		if ( $args['search'] && $args['search_field'] ) {
			$search_field = $args['search_field'];
			$query[ $search_field ]['like'] = $args['search'];
		}

		if ( $args['record_greater_than'] ) {
			$query['ID']['gt'] = $args['record_greater_than'];
		}

		/**
		 * PARSE DATE FILTERS
		 */
		if ( $args['date'] ) {
			$query['created']['in'] = $args['date'];
		} else {
			if ( $args['date_from'] ) {
				$query['created']['gte'] = $args['date_from'];
			}
			if ( $args['date_to'] ) {
				$query['created']['lte'] = $args['date_from'];
			}
		}

		/**
		 * PARSE META QUERY PARAMS
		 * Meta should be defined as:
		 * #1 args['meta'] => array( %key => %value )
		 * #2 args['meta'] => array( %key => array %values )
		 * #3 args['meta'] => array( %key => array( 'in' => array %values )
		 * Where 'in' can be one of in/not_in/like/gt/lt/gte/lte (just like core filters)
		 */
		if ( $args['meta'] ) {
			$meta = (array) $args['meta'];
			foreach ( $meta as $key => $values ) {
				// #1
				if ( ! is_array( $values ) ) {
					$values = (array) $values;
				}
				// #2
				if ( 0 === key( $values ) ) {
					$query['_meta'][ $key ]['in'] = $values;
				}
				// #3
				else {
					foreach ( $values as $operator => $_value ) {
						if ( in_array( $operator, array( 'in', 'not_in', 'like', 'gt', 'gte', 'lt', 'lte' ) ) ) {
							$query['_meta'][ $key ][ $operator ] = $_value;
						}
					}
				}
			}
		}

		/**
		 * PARSE PAGINATION PARAMS
		 */
		$page    = intval( $args['paged'] );
		$perpage = intval( $args['records_per_page'] );

		if ( $perpage >= 0 ) {
			$query['_offset']  = ( $page - 1 ) * $perpage;
			$query['_perpage'] = $perpage;
		}

		/**
		 * PARSE ORDER PARAMS
		 */
		$order   = esc_sql( $args['order'] );
		$orderby = $args['orderby'] ? esc_sql( $args['orderby'] ) : 'ID';
		if ( 'date' === $orderby ) {
			$orderby = 'created';
		}
		$orderable = array( 'ID', 'site_id', 'blog_id', 'object_id', 'author', 'author_role', 'summary', 'visibility', 'parent', 'type', 'created' );
		// TODO: Order by meta value, currently not possible without knowing the alias
		if ( in_array( $orderby, $orderable ) /*|| false !== strpos( $orderby, 'meta.' )*/ ) {
			if ( in_array( strtolower( $order ), array( 'asc', 'desc' ) ) ) {
				$query['_order'] = array( $orderby => $order );
			}
		}

		/**
		 * PARSE FIELDS PARAMETER
		 */
		$fields = $args['fields'];
		$query['_select'] = is_array( $fields ) ? $fields : explode( ',', $fields );

		/**
		 * PARSE DISTINCT PARAMETER
		 */
		if ( $args['distinct'] ) {
			$query['_distinct'] = true;
		}

		/**
		 * Allows developers to change final query
		 *
		 * @param  array  $query Query vars
		 * @param  array  $args  Arguments passed to query
		 * @return array
		 */
		$query = apply_filters( 'wp_stream_query', $query, $args );

		/**
		 * Query results
		 * @var  array
		 */
		$results = WP_Stream::$db->query( $query );

		/**
		 * Allow developers/extensions to modify results array
		 * @param   array  $results  Query Results
		 * @return  array
		 */
		return apply_filters( 'wp_stream_results', $results );
	}
}
