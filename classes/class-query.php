<?php
/**
 * Queries the database for stream records.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Query
 */
class Query {
	const ALLOWED_FIELDS = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'created', 'summary', 'connector', 'context', 'action', 'ip' );

	/**
	 * Columns that may appear in ORDER BY.
	 *
	 * ALLOWED_FIELDS minus ip. `orderby=date` (the DB::get_records() default)
	 * is not in this list, so those queries sort by ID. Mapping date to
	 * created would change order for backdated or imported records.
	 *
	 * @var string[]
	 */
	const ORDERABLE_FIELDS = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'summary', 'created', 'connector', 'context', 'action' );

	/**
	 * Hold the number of records found
	 */
	public int $found_records = 0;

	/**
	 * Database handle. Set from the global $wpdb in the constructor.
	 *
	 * @var object
	 */
	public $wpdb;

	/**
	 * Class constructor.
	 *
	 * @param mixed $unused Existing callers pass the DB driver; ignored.
	 */
	public function __construct( $unused = null ) {
		unset( $unused );
		global $wpdb;
		$this->wpdb = isset( $wpdb ) ? $wpdb : null;
	}

	/**
	 * Query records
	 *
	 * @param array $args Arguments to filter the records by.
	 *
	 * @return array Stream Records
	 */
	public function query( $args ) {
		$wpdb = $this->wpdb;

		// where_dates() expands date onto a copy for SQL. Filter $args stay as the
		// caller sent them — we do not write date_from/date_to here (that used to leak into the filters).

		$join    = $this->join( $args );
		$where   = $this->where_columns( $args );
		$where  .= $this->where_dates( $args );
		$where  .= $this->where_in( $args );
		$limits  = $this->limits( $args );
		$orderby = $this->orderby( $args );
		$select  = $this->select( $args );
		$groupby = $join ? "GROUP BY $wpdb->stream.ID" : '';

		/**
		 * Filters query WHERE statement as an alternative to filtering
		 * the $query using the hook below.
		 *
		 * @param string $where  WHERE statement.
		 *
		 * @return string
		 */
		$where = apply_filters( 'wp_stream_db_query_where', $where );

		/**
		 * BUILD THE FINAL QUERY
		 */
		$query = "SELECT {$select}
		FROM $wpdb->stream
		{$join}
		WHERE 1=1 {$where}
		{$groupby}
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

		// Build result count query.
		$count_query = "SELECT COUNT( DISTINCT $wpdb->stream.ID ) as found
		FROM $wpdb->stream
		{$join}
		WHERE 1=1 {$where}";

		/**
		 * Filter allows the result count query to be modified before execution.
		 *
		 * @param string $query
		 * @param array  $args
		 *
		 * @return string
		 */
		$count_query = apply_filters( 'wp_stream_db_count_query', $count_query, $args );

		/**
		 * QUERY THE DATABASE FOR RESULTS
		 */
		$result = array(
			'items' => $wpdb->get_results( $query ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'count' => absint( $wpdb->get_var( $count_query ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return $result;
	}

	/**
	 * Equality, search, and IP filters.
	 *
	 * @param array $args Arguments to filter the records by.
	 * @return string WHERE fragments (each starts with " AND ").
	 */
	public function where_columns( $args ) {
		$wpdb  = $this->wpdb;
		$where = '';

		foreach ( array( 'site_id', 'blog_id', 'object_id', 'user_id' ) as $column ) {
			if ( is_numeric( $args[ $column ] ) ) {
				$where .= $wpdb->prepare( " AND $wpdb->stream.{$column} = %d", $args[ $column ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		if ( ! empty( $args['user_role'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.user_role = %s", $args['user_role'] );
		}

		// Between user_role and connector — original query() order (not after action).
		if ( ! empty( $args['search'] ) ) {
			$field = ! empty( $args['search_field'] ) ? $args['search_field'] : 'summary';

			if ( in_array( $field, self::ALLOWED_FIELDS, true ) ) {
				$where .= $wpdb->prepare( " AND $wpdb->stream.{$field} LIKE %s", "%{$args['search']}%" ); // @codingStandardsIgnoreLine can't prepare column name
			}
		}

		foreach ( array( 'connector', 'context', 'action' ) as $column ) {
			if ( ! empty( $args[ $column ] ) ) {
				$where .= $wpdb->prepare( " AND $wpdb->stream.{$column} = %s", $args[ $column ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		if ( ! empty( $args['ip'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.ip = %s", wp_stream_filter_var( $args['ip'], FILTER_VALIDATE_IP ) );
		}

		return $where;
	}

	/**
	 * Date family filters. `date` expands to date_from and date_to.
	 *
	 * @param array $args Arguments to filter the records by.
	 * @return string WHERE fragments (each starts with " AND ").
	 */
	public function where_dates( $args ) {
		$wpdb  = $this->wpdb;
		$where = '';

		if ( ! empty( $args['date'] ) ) {
			$args['date_from'] = $args['date'];
			$args['date_to']   = $args['date'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$date   = get_gmt_from_date( gmdate( 'Y-m-d H:i:s', strtotime( $args['date_from'] . ' 00:00:00' ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) >= %s", $date );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$date   = get_gmt_from_date( gmdate( 'Y-m-d H:i:s', strtotime( $args['date_to'] . ' 23:59:59' ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) <= %s", $date );
		}

		if ( ! empty( $args['date_after'] ) ) {
			$date   = get_gmt_from_date( gmdate( 'Y-m-d H:i:s', strtotime( $args['date_after'] ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) > %s", $date );
		}

		if ( ! empty( $args['date_before'] ) ) {
			$date   = get_gmt_from_date( gmdate( 'Y-m-d H:i:s', strtotime( $args['date_before'] ) ) );
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) < %s", $date );
		}

		return $where;
	}

	/**
	 * __in and __not_in filters.
	 *
	 * Strip the suffix, map `record` → `ID`, allowlist against ALLOWED_FIELDS,
	 * then prepare with one placeholder per value (no array_shift, no array arg).
	 *
	 * @param array $args Arguments to filter the records by.
	 * @return string WHERE fragments (each starts with " AND ").
	 */
	public function where_in( $args ) {
		$wpdb     = $this->wpdb;
		$where    = '';
		$families = array(
			'__in'     => 'IN',
			'__not_in' => 'NOT IN',
		);

		foreach ( $families as $suffix => $sql_op ) {
			$suffix_len = strlen( $suffix );

			foreach ( $args as $arg => $value ) {
				if ( substr( $arg, -$suffix_len ) !== $suffix ) {
					continue;
				}

				if ( empty( $value ) || ! is_array( $value ) ) {
					continue;
				}

				$field = substr( $arg, 0, -$suffix_len );
				if ( 'record' === $field ) {
					$field = 'ID';
				}

				if ( ! in_array( $field, self::ALLOWED_FIELDS, true ) ) {
					continue;
				}

				$values = array_values( $value );
				$type   = is_numeric( $values[0] ) ? '%d' : '%s';
				$format = '(' . join( ',', array_fill( 0, count( $values ), $type ) ) . ')';

				$where .= $wpdb->prepare(
					" AND $wpdb->stream.{$field} {$sql_op} {$format}", // @codingStandardsIgnoreLine column name allowlisted
					...$values
				);
			}
		}

		return $where;
	}

	/**
	 * SELECT list. Column names cannot go through prepare(), so they are
	 * restricted to ALLOWED_FIELDS.
	 *
	 * @param array $args Arguments to filter the records by.
	 * @return string
	 */
	public function select( $args ) {
		$wpdb    = $this->wpdb;
		$fields  = (array) $args['fields'];
		$selects = array();

		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( 'meta' === $field ) {
					continue;
				}

				if ( ! in_array( $field, self::ALLOWED_FIELDS, true ) ) {
					continue;
				}

				$selects[] = sprintf( "$wpdb->stream.%s", $field );
			}
		}

		if ( empty( $selects ) ) {
			$selects[] = "$wpdb->stream.*";
		}

		return implode( ', ', $selects );
	}

	/**
	 * ORDER BY clause.
	 *
	 * @param array $args Arguments to filter the records by.
	 * @return string
	 */
	public function orderby( $args ) {
		$wpdb = $this->wpdb;

		// Default to sorting by record ID. See ORDERABLE_FIELDS.
		$orderby = "$wpdb->stream.ID";

		if ( in_array( $args['orderby'], self::ORDERABLE_FIELDS, true ) ) {
			$orderby = sprintf( '%s.%s', $wpdb->stream, $args['orderby'] );
		} elseif ( 'meta_value_num' === $args['orderby'] && ! empty( $args['meta_key'] ) ) {
			$orderby = "CAST($wpdb->streammeta.meta_value AS SIGNED)";
		} elseif ( 'meta_value' === $args['orderby'] && ! empty( $args['meta_key'] ) ) {
			$orderby = "$wpdb->streammeta.meta_value";
		}

		$order = 'DESC';
		if ( 'ASC' === strtoupper( $args['order'] ) ) {
			$order = 'ASC';
		}

		return sprintf( 'ORDER BY %s %s', $orderby, $order );
	}

	/**
	 * JOIN fragment for meta orderby.
	 *
	 * When orderby is meta_value / meta_value_num and meta_key is set,
	 * JOIN streammeta so ORDER BY can reference meta_value.
	 *
	 * @param array $args Arguments to filter the records by.
	 * @return string JOIN clause, or empty string.
	 */
	public function join( $args ) {
		$wpdb = $this->wpdb;

		$is_meta_orderby = in_array( $args['orderby'], array( 'meta_value', 'meta_value_num' ), true );
		if ( ! $is_meta_orderby || empty( $args['meta_key'] ) ) {
			return '';
		}

		return $wpdb->prepare(
			"LEFT JOIN $wpdb->streammeta ON $wpdb->streammeta.record_id = $wpdb->stream.ID AND $wpdb->streammeta.meta_key = %s", // @codingStandardsIgnoreLine table names from wpdb properties
			$args['meta_key']
		);
	}

	/**
	 * LIMIT clause.
	 *
	 * `$per_page >= 0` is kept for zero drift. absint() cannot return a
	 * negative, so the LIMIT is always emitted. paged below 1 is treated
	 * as page 1 so paged=0 does not become LIMIT per_page, per_page.
	 *
	 * @param array $args Arguments to filter the records by.
	 * @return string
	 */
	public function limits( $args ) {
		$limits   = '';
		$page     = absint( $args['paged'] );
		$per_page = absint( $args['records_per_page'] );

		if ( $page < 1 ) {
			$page = 1;
		}

		if ( $per_page >= 0 ) {
			$offset = absint( ( $page - 1 ) * $per_page );
			$limits = "LIMIT {$offset}, {$per_page}";
		}

		return $limits;
	}
}
