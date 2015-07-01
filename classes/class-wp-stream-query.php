<?php

class WP_Stream_Query {

	/**
	 * Hold class instance
	 *
	 * @access public
	 * @static
	 *
	 * @var WP_Stream_Query
	 */
	public static $instance;

	/**
	 * Return an active instance of this class, and create one if it doesn't exist
	 *
	 * @return WP_Stream_Query
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Query records
	 *
	 * @access public
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
			// Exclude
			'ignore_context'        => null,
			'hide_excluded'         => ! empty( WP_Stream_Settings::$options['exclude_hide_previous_records'] ),
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

		if ( true === $args['hide_excluded'] ) {
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
		 * PARSE CORE PARAMS
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

		if ( is_numeric( $args['site_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.site_id = %d", $args['site_id'] );
		}

		if ( is_numeric( $args['blog_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.blog_id = %d", $args['blog_id'] );
		}

		if ( $args['search'] ) {
			$field = ! empty( $args['search'] ) ? $args['search'] : 'summary';
			$where .= $wpdb->prepare( " AND $wpdb->stream.%s LIKE %s", $field, "%{$args['search']}%" );
		}

		if ( $args['author'] || '0' === $args['author'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.author = %d", (int) $args['author'] );
		}

		if ( $args['author_role'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.author_role = %s", $args['author_role'] );
		}

		if ( $args['visibility'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.visibility = %s", $args['visibility'] );
		}

		/**
		 * PARSE DATE PARAM FAMILY
		 */
		if ( $args['date_from'] ) {
			$date   = date( 'Y-m-d H:i:s', strtotime( $args['date_from'] ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) >= %s", $date );
		}

		if ( $args['date_to'] ) {
			$date   = date( 'Y-m-d H:i:s', strtotime( $args['date_to'] ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) <= %s", $date );
		}

		if ( $args['date_after'] ) {
			$date   = date( 'Y-m-d H:i:s', strtotime( $args['date_after'] ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) > %s", $date );
		}

		if ( $args['date_before'] ) {
			$date   = date( 'Y-m-d H:i:s', strtotime( $args['date_before'] ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) < %s", $date );
		}

		if ( $args['date'] ) {
			$date   = date( 'Y-m-d H:i:s', strtotime( $args['date'] ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) = %s", $date );
		}

		/**
		 * PARSE __IN PARAM FAMILY
		 */
		$ins = array();

		foreach ( $args as $arg => $value ) {
			if ( '__in' === substr( $arg, -4 ) ) {
				$ins[ $arg ] = $value;
			}
		}

		if ( ! empty( $ins ) ) {
			foreach ( $ins as $key => $value ) {
				if ( empty( $value ) || ! is_array( $value ) ) {
					continue;
				}

				$field = str_replace( array( 'record_', '__in' ), '', $key );
				$field = empty( $field ) ? 'ID' : $field;
				$type  = is_numeric( array_shift( $value ) ) ? '%d' : '%s';

				if ( ! empty( $value ) ) {
					$format = '(' . join( ',', array_fill( 0, count( $value ), $type ) ) . ')';
					$where .= $wpdb->prepare( " AND $wpdb->stream.%s IN {$format}", $field, $value );
				}
			}
		}

		/**
		 * PARSE __NOT_IN PARAM FAMILY
		 */
		$not_ins = array();

		foreach ( $args as $arg => $value ) {
			if ( '__not_in' === substr( $arg, -8 ) ) {
				$not_ins[ $arg ] = $value;
			}
		}

		if ( ! empty( $not_ins ) ) {
			foreach ( $not_ins as $key => $value ) {
				if ( empty( $value ) || ! is_array( $value ) ) {
					continue;
				}

				$field = str_replace( array( 'record_', '__not_in' ), '', $key );
				$field = empty( $field ) ? 'ID' : $field;
				$type  = is_numeric( array_shift( $value ) ) ? '%d' : '%s';

				if ( ! empty( $value ) ) {
					$format = '(' . join( ',', array_fill( 0, count( $value ), $type ) ) . ')';
					$where .= $wpdb->prepare( " AND $wpdb->stream.%s NOT IN {$format}", $field, $value );
				}
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
		$limits   = '';
		$page     = absint( $args['paged'] );
		$per_page = absint( $args['records_per_page'] );

		if ( $per_page >= 0 ) {
			$offset = absint( ( $page - 1 ) * $per_page );
			$limits = "LIMIT {$offset}, {$per_page}";
		}

		/**
		 * PARSE ORDER PARAMS
		 */
		$order     = esc_sql( $args['order'] );
		$orderby   = esc_sql( $args['orderby'] );
		$orderable = array( 'ID', 'site_id', 'blog_id', 'object_id', 'author', 'author_role', 'summary', 'visibility', 'parent', 'type', 'created' );

		if ( in_array( $orderby, $orderable ) ) {
			$orderby = sprintf( '%s.%s', $wpdb->stream, $orderby );
		} elseif ( in_array( $orderby, array( 'connector', 'context', 'action' ) ) ) {
			$orderby = sprintf( '%s.%s', $wpdb->streamcontext, $orderby );
		} elseif ( 'meta_value_num' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "CAST($wpdb->streammeta.meta_value AS SIGNED)";
		} elseif ( 'meta_value' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "$wpdb->streammeta.meta_value";
		} else {
			$orderby = "$wpdb->stream.ID";
		}

		$orderby = "ORDER BY {$orderby} {$order}";

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
		} elseif ( 'summary' === $fields ) {
			$select = "$wpdb->stream.summary, $wpdb->stream.ID";
		}

		/**
		 * BUILD THE FINAL QUERY
		 */
		$query = "SELECT SQL_CALC_FOUND_ROWS {$select}
		FROM $wpdb->stream
			{$join}
		WHERE 1=1 {$where}
		{$orderby}
		{$limits}";

		/**
		 * Filter allows the final query to be modified before execution
		 *
		 * @param string $query
		 * @param array  $args
		 *
		 * @return string
		 */
		$query = apply_filters( 'wp_stream_db_query', $query, $args );

		/**
		 * QUERY THE DATABASE FOR RESULTS
		 */
		$results = $wpdb->get_results( $query );

		//print_r( $results ); die();

		if ( 'with-meta' === $fields && is_array( $results ) && ! empty( $results ) ) {
			$ids = array_map( 'absint', wp_list_pluck( $results, 'ID' ) );

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

		return (array) $results;
	}

}
