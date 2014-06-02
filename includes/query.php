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
			'orderby'               => 'ID',
			// Meta/Taxonomy sub queries
			'meta'                  => array(),
			// Fields selection
			'fields'                => '*',
			// Hide records that match the exclude rules
			'hide_excluded'         => ! empty( WP_Stream_Settings::$options['exclude_hide_previous_records'] ),
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
			$defaults[ "{$_field}__in" ] = null; // Null makes `isset` return false
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

		if ( true === $args['hide_excluded'] ) {
			// Remove record of excluded connector
			$args['connector__not_in'] = WP_Stream_Settings::get_excluded_by_key( 'connectors' );

			// Remove record of excluded context
			$args['context__not_in'] = WP_Stream_Settings::get_excluded_by_key( 'contexts' );

			// Remove record of excluded actions
			$args['action__not_in'] = WP_Stream_Settings::get_excluded_by_key( 'actions' );

			// Remove record of excluded author
			$args['author__not_in'] = WP_Stream_Settings::get_excluded_by_key( 'authors_and_roles' );

			// Remove record of excluded ip
			$args['ip__not_in'] = WP_Stream_Settings::get_excluded_by_key( 'ip_addresses' );
		}

		$query  = array();

		/**
		 * PARSE CORE FILTERS, PLUS __IN / __NOT_IN VARIATIONS
		 */
		foreach ( $columns as $field => $def ) {
			$is_int  = isset( $def['is_int'] );
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
		if ( $args['search'] ) {
			$query['summary']['like'] = $args['search'];
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
		 * This is breaking change, meta should now be defined as:
		 * #1 args[ 'meta' ] => array( %key => %value )
		 * #2 args[ 'meta' ] => array( %key => array %values )
		 * #3 args[ 'meta' ] => array( %key => array( 'in' => array %values )
		 *   While 'in' can be one of in/not_in/like/gt/lt/gte/lte ( just like core filters )
		 */
		if ( $args['meta'] ) {
			$meta = (array) $args['meta'];
			foreach ( $meta as $key => $values ) {
				// #1
				if ( ! is_array( $values ) ) {
					$values   = (array) $values;
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
			$query['_offset'] = ( $page - 1 ) * $perpage;
			$query['_perpage'] = $perpage;
		}

		/**
		 * PARSE ORDER PARAMS
		 */
		$order     = esc_sql( $args['order'] );
		$orderby   = $args['orderby'] ? esc_sql( $args['orderby'] ) : 'ID';
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

function wp_stream_query( $args = array() ) {
	return WP_Stream_Query::instance()->query( $args );
}

function wp_stream_get_meta( $record_id, $meta_key = '', $single = false ) {
	return WP_Stream::$db->get_meta( $record_id, $meta_key, $single );
}

function wp_stream_add_meta( $record_id, $meta_key, $meta_value ) {
	return WP_Stream::$db->add_meta( $record_id, $meta_key, $meta_value );
}

function wp_stream_update_meta( $record_id, $meta_key, $meta_value, $prev_value = '' ) {
	return WP_Stream::$db->update_meta( $record_id, $meta_key, $meta_value, $prev_value );
}

function wp_stream_delete_meta( $record_id, $meta_key, $meta_value = null, $delete_all = false ) {
	return WP_Stream::$db->delete_meta( $record_id, $meta_key, $meta_value, $delete_all );
}

function wp_stream_delete_records( $args = array() ) {
	if ( $args ) {
		$args['fields']           = 'ID';
		$args['records_per_page'] = -1;
		$records                  = wp_stream_query( $args );
		$params                   = wp_list_pluck( $records, 'ID' );
		if ( empty( $ids ) ) {
			return 0;
		}
	} else {
		$params = true; // Delete them ALL!
	}

	return WP_Stream::$db->delete( $params );
}

/**
 * Returns array of existing values for requested column.
 * Used to fill search filters with only used items, instead of all items.
 *
 * @see    assemble_records
 * @since  1.0.4
 * @param  string  Requested Column (i.e., 'context')
 * @return array   Array of items to be output to select dropdowns
 */
function wp_stream_existing_records( $column ) {
	// Short circuit for now, till Facets is available
	return array();
	$values = WP_Stream::$db->get_col( $column );
	if ( is_array( $values ) && ! empty( $values ) ) {
		return array_combine( $values, $values );
	} else {
		$column = sprintf( 'stream_%s', $column );
		return isset( WP_Stream_Connectors::$term_labels[ $column ] ) ? WP_Stream_Connectors::$term_labels[ $column ] : array();
	}
}
