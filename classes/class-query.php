<?php
namespace WP_Stream;

class Query {
	/**
	 * @var DB
	 */
	public $db;

	/**
	 * Hold the number of records found
	 *
	 * @var int
	 */
	public $found_records = 0;

	/**
	 * Class constructor.
	 *
	 * @param DB $db The parent database class.
	 */
	public function __construct( $db ) {
		$this->db = $db;
	}

	/**
	 * Query records
	 *
	 * @param array Query args
	 *
	 * @return array Stream Records
	 */
	public function query( $args ) {
		global $wpdb;

		$defaults = array(
			// Search param
			'search'           => null,
			'search_field'     => 'summary',
			'record_after'     => null, // Deprecated, use date_after instead
			// Date-based filters
			'date'             => null, // Ex: 2015-07-01
			'date_from'        => null, // Ex: 2015-07-01
			'date_to'          => null, // Ex: 2015-07-01
			'date_after'       => null, // Ex: 2015-07-01T15:19:21+00:00
			'date_before'      => null, // Ex: 2015-07-01T15:19:21+00:00
			// Record ID filters
			'record'           => null,
			'record__in'       => array(),
			'record__not_in'   => array(),
			// Pagination params
			'records_per_page' => get_option( 'posts_per_page', 20 ),
			'paged'            => 1,
			// Order
			'order'            => 'desc',
			'orderby'          => 'date',
			// Fields selection
			'fields'           => array(),
		);

		// Additional property fields
		$properties = array(
			'user_id'   => null,
			'user_role' => null,
			'ip'        => null,
			'object_id' => null,
			'site_id'   => null,
			'blog_id'   => null,
			'connector' => null,
			'context'   => null,
			'action'    => null,
		);

		/**
		 * Filter allows additional query properties to be added
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
		 * @return array  Array of query arguments
		 */
		$args = apply_filters( 'wp_stream_query_args', $args );

		$join  = '';
		$where = '';

		/**
		 * PARSE CORE PARAMS
		 */
		if ( is_numeric( $args['site_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.site_id = %d", $args['site_id'] );
		}

		if ( is_numeric( $args['blog_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.blog_id = %d", $args['blog_id'] );
		}

		if ( is_numeric( $args['object_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.object_id = %d", $args['object_id'] );
		}

		if ( is_numeric( $args['user_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.user_id = %d", $args['user_id'] );
		}

		if ( ! empty( $args['user_role'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.user_role = %s", $args['user_role'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$field  = ! empty( $args['search_field'] ) ? $args['search_field'] : 'summary';

			// Sanitize field
			$allowed_fields = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'created', 'summary', 'connector', 'context', 'action', 'ip' );
			if ( in_array( $field, $allowed_fields ) ) {
				$where .= $wpdb->prepare( " AND $wpdb->stream.{$field} LIKE %s", "%{$args['search']}%" ); // @codingStandardsIgnoreLine can't prepare column name
			}
		}

		if ( ! empty( $args['connector'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.connector = %s", $args['connector'] );
		}

		if ( ! empty( $args['context'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.context = %s", $args['context'] );
		}

		if ( ! empty( $args['action'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.action = %s", $args['action'] );
		}

		if ( ! empty( $args['ip'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.ip = %s", wp_stream_filter_var( $args['ip'], FILTER_VALIDATE_IP ) );
		}

		/**
		 * PARSE DATE PARAM FAMILY
		 */
		if ( ! empty( $args['date_from'] ) ) {
			$date   = get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( $args['date_from'] . ' 00:00:00' ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) >= %s", $date );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$date   = get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( $args['date_to'] . ' 23:59:59' ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) <= %s", $date );
		}

		if ( ! empty( $args['date_after'] ) ) {
			$date   = get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( $args['date_after'] ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) > %s", $date );
		}

		if ( ! empty( $args['date_before'] ) ) {
			$date   = get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( $args['date_before'] ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) < %s", $date );
		}

		if ( ! empty( $args['date'] ) ) {
			$args['date_from'] = date( 'Y-m-d', strtotime( $args['date'] ) ) . ' 00:00:00';
			$args['date_to']   = date( 'Y-m-d', strtotime( $args['date'] ) ) . ' 23:59:59';
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
					$where .= $wpdb->prepare( " AND $wpdb->stream.%s IN {$format}", $field, $value ); // @codingStandardsIgnoreLine prepare okay
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
					$where .= $wpdb->prepare( " AND $wpdb->stream.%s NOT IN {$format}", $field, $value ); // @codingStandardsIgnoreLine prepare okay
				}
			}
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
		$orderable = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'summary', 'created', 'connector', 'context', 'action' );

		if ( in_array( $orderby, $orderable ) ) {
			$orderby = sprintf( '%s.%s', $wpdb->stream, $orderby );
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
		$fields  = (array) $args['fields'];
		$selects = array();

		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field ) {
				// We'll query the meta table later
				if ( 'meta' === $field ) {
					continue;
				}

				$selects[] = sprintf( "$wpdb->stream.%s", $field );
			}
		} else {
			$selects[] = "$wpdb->stream.*";
		}

		$select = implode( ', ', $selects );

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
		$results = $wpdb->get_results( $query ); // @codingStandardsIgnoreLine $query already prepared

		// Hold the number of records found
		$this->found_records = absint( $wpdb->get_var( 'SELECT FOUND_ROWS()' ) );

		// Add meta to the records, when applicable
		if ( empty( $fields ) || in_array( 'meta', $fields ) ) {
			$results = $this->add_record_meta( $results );
		}

		return (array) $results;
	}

	/**
	 * Add meta to a set of records
	 *
	 * @param array $records
	 *
	 * @return array
	 */
	public function add_record_meta( $records ) {
		global $wpdb;

		$record_ids = array_map( 'absint', wp_list_pluck( $records, 'ID' ) );

		if ( empty( $record_ids ) ) {
			return (array) $records;
		}

		$sql_meta = sprintf(
			"SELECT * FROM $wpdb->streammeta WHERE record_id IN ( %s )",
			implode( ',', $record_ids )
		);

		$meta  = $wpdb->get_results( $sql_meta ); // @codingStandardsIgnoreLine prepare okay
		$ids_f = array_flip( $record_ids );

		foreach ( $meta as $meta_record ) {
			$records[ $ids_f[ $meta_record->record_id ] ]->meta[ $meta_record->meta_key ] = maybe_unserialize( $meta_record->meta_value );
		}

		return (array) $records;
	}
}
