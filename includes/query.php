<?php

class WP_Stream_Query {

	public static $instance;

	public static function get_instance() {
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
			// Pagination params
			'records_per_page'      => 10,
			'paged'                 => 1,
			// Search params
			'search'                => null,
			// Stream core fields filtering
			'type'                  => 'stream',
			'object_id'             => null,
			'ip'                    => null,
			// Author param
			'author'                => null,
			// Date-based filters
			'date'                  => null,
			'date_from'             => null,
			'date_to'               => null,
			// Visibility filters
			'visibility'            => null,
			// __in params
			'record_greater_than'   => null,
			'record__in'            => array(),
			'record__not_in'        => array(),
			'record_parent'         => '',
			'record_parent__in'     => array(),
			'record_parent__not_in' => array(),
			'author__in'            => array(),
			'author__not_in'        => array(),
			'ip__in'                => array(),
			'ip__not_in'            => array(),
			// Order
			'order'                 => 'desc',
			'orderby'               => 'ID',
			// Meta/Taxonomy sub queries
			'meta_query'            => array(),
			'context_query'         => array(),
			// Fields selection
			'fields'                => '',
			'ignore_context'        => null,
			// Hide records that match the exclude rules
			'hide_excluded'         => ! empty( WP_Stream_Settings::$options[ 'exclude_hide_previous_records' ] ),
		);

		$args = wp_parse_args( $args, $defaults );
		/**
		 * Filter allows additional arguments to query $args
		 *
		 * @param  array  Array of query arguments
		 * @return array  Updated array of query arguments
		 */
		$args = apply_filters( 'stream_query_args', $args );

		if ( true === $args[ 'hide_excluded' ] ) {
			$args = self::add_excluded_record_args( $args );
		}

		$join  = '';
		$where = '';

		// Only join with context table for correct types of records
		if ( ! $args['ignore_context'] ) {
			$join = sprintf(
				' INNER JOIN %1$s ON ( %1$s.record_id = %2$s.ID )',
				$wpdb->streamcontext,
				$wpdb->stream
			);
		}

		/**
		 * PARSE CORE FILTERS
		 */
		if ( $args['object_id'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.object_id = %d", $args['object_id'] );
		}

		if ( $args['type'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.type = %s", $args['type'] );
		}

		if ( $args['ip'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.ip = %s", wp_stream_filter_var( $args['ip'], FILTER_VALIDATE_IP ) );
		}

		if ( $args['search'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.summary LIKE %s", "%{$args['search']}%" );
		}

		if ( $args['author'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.author LIKE %d", (int) $args['author'] );
		}

		if ( $args['visibility'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.visibility = %s", $args['visibility'] );
		}

		/**
		 * PARSE DATE FILTERS
		 */
		if ( $args['date'] ) {
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) = %s", $args['date'] );
		}
		else {
			if ( $args['date_from'] ) {
				$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) >= %s", $args['date_from'] );
			}
			if ( $args['date_to'] ) {
				$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) <= %s", $args['date_to'] );
			}
		}

		/**
		 * PARSE __IN PARAM FAMILY
		 */
		if ( $args[ 'record_greater_than' ] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.ID > %d", (int) $args[ 'record_greater_than' ] );
		}

		if ( $args[ 'record__in' ] ) {
			$record__in = array_filter( (array) $args[ 'record__in' ], 'is_numeric' );
			if ( ! empty( $record__in ) ) {
				$record__in_format = '(' . join( ',', array_fill( 0, count( $record__in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.ID IN {$record__in_format}", $record__in );
			}
		}

		if ( $args[ 'record__not_in' ] ) {
			$record__not_in = array_filter( (array) $args[ 'record__not_in' ], 'is_numeric' );
			if ( ! empty( $record__not_in ) ) {
				$record__not_in_format = '(' . join( ',', array_fill( 0, count( $record__not_in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.ID NOT IN {$record__not_in_format}", $record__not_in );
			}
		}

		if ( $args[ 'record_parent' ] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.parent = %d", (int) $args[ 'record_parent' ] );
		}

		if ( $args[ 'record_parent__in' ] ) {
			$record_parent__in = array_filter( (array) $args[ 'record_parent__in' ], 'is_numeric' );
			if ( ! empty( $record_parent__in ) ) {
				$record_parent__in_format = '(' . join( ',', array_fill( 0, count( $record_parent__in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.parent IN {$record_parent__in_format}", $record_parent__in );
			}
		}

		if ( $args[ 'record_parent__not_in' ] ) {
			$record_parent__not_in = array_filter( (array) $args[ 'record_parent__not_in' ], 'is_numeric' );
			if ( ! empty( $record_parent__not_in ) ) {
				$record_parent__not_in_format = '(' . join( ',', array_fill( 0, count( $record_parent__not_in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.parent NOT IN {$record_parent__not_in_format}", $record_parent__not_in );
			}
		}

		if ( $args[ 'author__in' ] ) {
			$author__in = array_filter( (array) $args[ 'author__in' ], 'is_numeric' );
			if ( ! empty( $author__in ) ) {
				$author__in_format = '(' . join( ',', array_fill( 0, count( $author__in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.author IN {$author__in_format}", $author__in );
			}
		}

		if ( $args[ 'author__not_in' ] ) {
			$author__not_in = array_filter( (array) $args[ 'author__not_in' ], 'is_numeric' );
			if ( ! empty( $author__not_in ) ) {
				$author__not_in_format = '(' . join( ',', array_fill( 0, count( $author__not_in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.author NOT IN {$author__not_in_format}", $author__not_in );
			}
		}
		if ( $args[ 'ip__in' ] ) {
			if ( ! empty( $args[ 'ip__in' ] ) ) {
				$ip__in = '(' . join( ',', array_fill( 0, count( $args[ 'ip__in' ] ), '%s' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.ip IN {$ip__in}", $args[ 'ip__in' ] );
			}
		}

		if ( $args[ 'ip__not_in' ] ) {
			if ( ! empty( $args[ 'ip__not_in' ] ) ) {
				$ip__not_in = '(' . join( ',', array_fill( 0, count( $args[ 'ip__not_in' ] ), '%s' ) ) . ')';
				$where     .= $wpdb->prepare( " AND $wpdb->stream.ip NOT IN {$ip__not_in}", $args[ 'ip__not_in' ] );
			}
		}

		/**
		 * PARSE META QUERY PARAMS
		 */
		$meta_query = new WP_Meta_Query;
		$meta_query->parse_query_vars( $args );
		if ( ! empty( $meta_query->queries ) ) {
			$mclauses = $meta_query->get_sql( 'stream', $wpdb->stream, 'ID' );
			$join    .= str_replace( 'stream_id', 'record_id', $mclauses['join'] );
			$where   .= str_replace( 'stream_id', 'record_id', $mclauses['where'] );
		}

		/**
		 * PARSE CONTEXT PARAMS
		 */
		if ( ! $args['ignore_context'] ) {
			$context_query = new WP_Stream_Context_Query( $args );
			$cclauses      = $context_query->get_sql();
			$join         .= $cclauses['join'];
			$where        .= $cclauses['where'];
		}

		/**
		 * PARSE PAGINATION PARAMS
		 */
		$page    = intval( $args['paged'] );
		$perpage = intval( $args['records_per_page'] );

		if ( $perpage >= 0 ) {
			$offset = ($page - 1) * $perpage;
			$limits = "LIMIT $offset, {$perpage}";
		} else {
			$limits = '';
		}

		/**
		 * PARSE ORDER PARAMS
		 */
		$order     = esc_sql( $args['order'] );
		$orderby   = esc_sql( $args['orderby'] );
		$orderable = array( 'ID', 'site_id', 'object_id', 'author', 'summary', 'visibility', 'parent', 'type', 'created' );

		if ( in_array( $orderby, $orderable ) ) {
			$orderby = $wpdb->stream . '.' . $orderby;
		}
		elseif ( in_array( $orderby, array( 'connector', 'context', 'action' ) ) ) {
			$orderby = $wpdb->streamcontext . '.' . $orderby;
		}
		elseif ( 'meta_value_num' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "CAST($wpdb->streammeta.meta_value AS SIGNED)";
		}
		elseif ( 'meta_value' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "$wpdb->streammeta.meta_value";
		}
		else {
			$orderby = "$wpdb->stream.ID";
		}
		$orderby = 'ORDER BY ' . $orderby . ' ' . $order;

		/**
		 * PARSE FIELDS PARAMETER
		 */
		$fields = $args['fields'];
		$select = "$wpdb->stream.*";

		if ( ! $args['ignore_context'] ) {
			$select .= ", $wpdb->streamcontext.context, $wpdb->streamcontext.action, $wpdb->streamcontext.connector";
		}

		if ( 'ID' === $fields ) {
			$select = "$wpdb->stream.ID";
		}
		elseif ( 'summary' === $fields ) {
			$select = "$wpdb->stream.summary, $wpdb->stream.ID";
		}

		/**
		 * BUILD UP THE FINAL QUERY
		 */
		$sql = "SELECT SQL_CALC_FOUND_ROWS $select
		FROM $wpdb->stream
		$join
		WHERE 1=1 $where
		$orderby
		$limits";

		/**
		 * Allows developers to change final SQL of Stream Query
		 *
		 * @param  string $sql   SQL statement
		 * @param  array  $args  Arguments passed to query
		 * @return string
		 */
		$sql = apply_filters( 'wp_stream_query', $sql, $args );

		$results = $wpdb->get_results( $sql );

		if ( 'with-meta' === $fields && is_array( $results ) ) {
			$ids      = array_map( 'absint', wp_list_pluck( $results, 'ID' ) );
			$sql_meta = sprintf(
				"SELECT * FROM $wpdb->streammeta WHERE record_id IN ( %s )",
				implode( ',', $ids )
			);

			$meta  = $wpdb->get_results( $sql_meta );
			$ids_f = array_flip( $ids );
			foreach ( $meta as $meta_record ) {
				$results[ $ids_f[ $meta_record->record_id ] ]->meta[ $meta_record->meta_key ][] = $meta_record->meta_value;
			}
		}

		return $results;
	}

	/**
	 * Function will add excluded settings args into stream query
	 *
	 * @param $args array query args passed to stream_query
	 *
	 * @return array
	 */
	public static function add_excluded_record_args( $args ) {
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

		return $args;
	}

}

function stream_query( $args = array() ) {
	return WP_Stream_Query::get_instance()->query( $args );
}

function get_stream_meta( $record_id, $key = '', $single = false ) {
	return get_metadata( 'record', $record_id, $key, $single );
}

function update_stream_meta( $record_id, $meta_key, $meta_value, $prev_value = '' ) {
	return update_metadata( 'record', $record_id, $meta_key, $meta_value, $prev_value );
}

/**
 * Returns array of existing values for requested column.
 * Used to fill search filters with only used items, instead of all items.
 *
 * GROUP BY allows query to find just the first occurance of each value in the column,
 * increasing the efficiency of the query.
 *
 * @todo   increase security against injections
 *
 * @see    assemble_records
 * @since  1.0.4
 * @param  string  Requested Column (i.e., 'context')
 * @param  string  Requested Table
 * @return array   Array of items to be output to select dropdowns
 */
function existing_records( $column, $table = '' ) {
	global $wpdb;

	switch ( $table ) {
		case 'stream' :
			$rows = $wpdb->get_results( "SELECT {$column} FROM {$wpdb->stream} GROUP BY {$column}", 'ARRAY_A' );
			break;
		case 'meta' :
			$rows = $wpdb->get_results( "SELECT {$column} FROM {$wpdb->streammeta} GROUP BY {$column}", 'ARRAY_A' );
			break;
		default :
			$rows = $wpdb->get_results( "SELECT {$column} FROM {$wpdb->streamcontext} GROUP BY {$column}", 'ARRAY_A' );
	}

	if ( is_array( $rows ) && ! empty( $rows ) ) {
		foreach ( $rows as $row ) {
			foreach ( $row as $cell => $value ) {
				$output_array[ $value ] = $value;
			}
		}
		return (array) $output_array;
	} else {
		$column = sprintf( 'stream_%s', $column );
		return isset( WP_Stream_Connectors::$term_labels[ $column ] ) ? WP_Stream_Connectors::$term_labels[ $column ] : array();
	}
}
