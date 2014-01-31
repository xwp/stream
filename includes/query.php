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
			// Order
			'order'                 => 'desc',
			'orderby'               => 'ID',
			// Meta/Taxonomy sub queries
			'meta_query'            => array(),
			'context_query'         => array(),
			// Fields selection
			'fields'                => '',
			'ignore_context'        => null,
			);

		$args = wp_parse_args( $args, $defaults );

		$args = apply_filters( 'stream_query_args', $args );

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
			$where .= $wpdb->prepare( " AND $wpdb->stream.ip = %s", filter_var( $args['ip'], FILTER_VALIDATE_IP ) );
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
		if ( $args['record_greater_than'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.ID > %d", (int) $args['record_greater_than'] );
		}

		if ( $args['record__in'] ) {
			$record__in = implode( ',', array_filter( (array) $args['record__in'], 'is_numeric' ) );
			if ( $record__in ) {
				$where .= $wpdb->prepare( " AND $wpdb->stream.ID IN ($record__in)", '' );
			}
		}

		if ( $args['record__not_in'] ) {
			$record__not_in = implode( ',', array_filter( (array) $args['record__not_in'], 'is_numeric' ) );
			if ( strlen( $record__not_in ) ) {
				$where .= $wpdb->prepare( " AND $wpdb->stream.ID NOT IN ($record__not_in)", '' );
			}
		}

		if ( $args['record_parent'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.parent = %d", (int) $args['record_parent'] );
		}

		if ( $args['record_parent__in'] ) {
			$record_parent__in = implode( ',', array_filter( (array) $args['record_parent__in'], 'is_numeric' ) );
			if ( strlen( $record_parent__in ) ) {
				$where .= $wpdb->prepare( " AND $wpdb->stream.parent IN ($record_parent__in)", '' );
			}
		}

		if ( $args['record_parent__not_in'] ) {
			$record_parent__not_in = implode( ',', array_filter( (array) $args['record_parent__not_in'], 'is_numeric' ) );
			if ( strlen( $record_parent__not_in ) ) {
				$where .= $wpdb->prepare( " AND $wpdb->stream.parent NOT IN ($record_parent__not_in)", '' );
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
		$order   = esc_sql( $args['order'] );
		$orderby = esc_sql( $args['orderby'] );

		if ( in_array(
			$orderby,
			array( 'ID', 'site_id', 'object_id', 'author', 'summary', 'visibility', 'parent', 'type', 'created' )
			) ) {
			$orderby = $wpdb->stream . '.' . $orderby;
		}
		elseif ( in_array( $orderby, array( 'connector', 'context', 'action' ) ) ) {
			$orderby = $wpdb->streamcontext . '.' . $orderby;
		}
		elseif ( $orderby == 'meta_value_num' && ! empty( $args['meta_key'] ) ) {
			$orderby = "CAST($wpdb->streammeta.meta_value AS SIGNED)";
		}
		elseif ( $orderby == 'meta_value' && ! empty( $args['meta_key'] ) ) {
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
		if ( $fields == 'ID' ) {
			$select = "$wpdb->stream.ID";
		}
		elseif ( $fields == 'summary' ) {
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

		$results = $wpdb->get_results( $sql );

		return $results;
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
				$output_array[$value] = $value;
			}
		}
		return (array) $output_array;
	} else {
		return WP_Stream_Connectors::$term_labels['stream_' . $column];
	}
}
