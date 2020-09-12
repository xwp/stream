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
	/**
	 * Hold the number of records found
	 *
	 * @var int
	 */
	public $found_records = 0;

	/**
	 * Instance of the WPDB object.
	 *
	 * @var \WP_DB
	 */
	protected $db;

	/**
	 * List of IN and NOT IN query field names.
	 *
	 * @var array
	 */
	protected $lookup_fields;

	/**
	 * Setup a query.
	 */
	public function __construct() {
		global $wpdb;

		// TODO: Switch all instances of $wpdb to $this->db;
		$this->db = $wpdb;

		$this->lookup_fields = array(
			'ID',
			'site_id',
			'blog_id',
			'object_id',
			'user_id',
			'user_role',
			'created',
			'summary',
			'connector',
			'context',
			'action',
			'ip',
		);
	}

	/**
	 * Query records
	 *
	 * @param array $args Arguments to filter the records by.
	 *
	 * @return array Stream Records
	 */
	public function query( $args ) {
		global $wpdb;

		$join  = '';
		$where = '';

		foreach ( $args as $query_arg => $query_value ) {
			if ( empty( $query_value ) ) {
				continue;
			}

			switch ( $query_arg ) {
				// Process core params.
				case 'site_id':
				case 'blog_id':
				case 'object_id':
				case 'user_id':
				case 'user_role':
				case 'connector':
				case 'context':
				case 'action':
				case 'ip':
					$where .= $this->and_where( $query_arg, $query_value );
					break;

				// Process "search*" params.
				case 'search':
					$field = ! empty( $args['search_field'] ) ? $args['search_field'] : 'summary';
					$field = $this->lookup_field_validated( $field );

					if ( ! empty( $field ) ) {
						$where .= $this->and_where( $field, "%{$query_value}%", 'LIKE' );
					}
					break;

				// Process "date*" params.
				case 'date':
					$args['date_from'] = $args['date'];
					$args['date_to']   = $args['date'];
					break;
				case 'date_from':
				case 'date_to':
				case 'date_after':
				case 'date_before':
					if ( 'date_from' === $query_arg ) {
						$time = '00:00:00';
					} elseif ( 'date_to' === $query_arg ) {
						$time = '23:59:59';
					}

					$compare = $this->get_date_compare( $query_arg );

					$date   = isset( $time ) ? strtotime( "{$query_value} {$time}" ) : strtotime( $query_value );
					$date   = get_gmt_from_date( gmdate( 'Y-m-d H:i:s', $date ) );
					$where .= $this->and_where( 'created', $date, $compare, true );
					break;

				// Process all other valid params except "fields", "order" and "pagination" params.
				default:
					$field = $this->lookup_field_validated( $query_arg );

					if ( ! empty( $field ) && ! empty( $query_value ) ) {
						$values_prepared = implode( ', ', $this->db_prepare_list( $query_value ) );

						if ( $this->key_is_in_lookup( $query_arg ) ) {
							$where .= sprintf( " AND $wpdb->stream.%s IN (%s)", $field, $values_prepared );
						} elseif ( $this->key_is_not_in_lookup( $query_arg ) ) {
							$where .= sprintf( " AND $wpdb->stream.%s NOT IN (%s)", $field, $values_prepared );
						}
					}
					break;
			}
		}

		// Process pagination params.
		$limits   = '';
		$page     = absint( $args['paged'] );
		$per_page = absint( $args['records_per_page'] );

		if ( $per_page >= 0 ) {
			$offset = absint( ( $page - 1 ) * $per_page );
			$limits = "LIMIT {$offset}, {$per_page}";
		}

		// Process order params.
		$order     = esc_sql( $args['order'] );
		$orderby   = esc_sql( $args['orderby'] );
		$orderable = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'summary', 'created', 'connector', 'context', 'action' );

		if ( in_array( $orderby, $orderable, true ) ) {
			$orderby = sprintf( '%s.%s', $wpdb->stream, $orderby );
		} elseif ( 'meta_value_num' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "CAST($wpdb->streammeta.meta_value AS SIGNED)";
		} elseif ( 'meta_value' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "$wpdb->streammeta.meta_value";
		} else {
			$orderby = "$wpdb->stream.ID";
		}

		$orderby = "ORDER BY {$orderby} {$order}";

		// Process "fields" parameters.
		$fields  = (array) $args['fields'];
		$selects = array();

		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field ) {
				// We'll query the meta table later.
				if ( 'meta' === $field ) {
					continue;
				}

				$selects[] = sprintf( "$wpdb->stream.%s", $field );
			}
		} else {
			$selects[] = "$wpdb->stream.*";
		}

		$select = implode( ', ', $selects );

		// Build the final query.
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

		$result = array();

		// Execute query and return results.
		$result['items'] = $wpdb->get_results( $query ); // @codingStandardsIgnoreLine $query already prepared
		$result['count'] = $result['items'] ? absint( $wpdb->get_var( 'SELECT FOUND_ROWS()' ) ) : 0;

		return $result;
	}

	/**
	 * Escape and prepare list values for select IN and NOT IN statements.
	 *
	 * @param array|string $list Values to prepare.
	 *
	 * @return array
	 */
	protected function db_prepare_list( $list ) {
		// Ensure we're always working with the same data type.
		if ( ! is_array( $list ) ) {
			$list = array( $list );
		}

		return array_map(
			function( $value ) {
				if ( is_numeric( $value ) ) {
					return $this->db->prepare( '%d', $value );
				}

				return $this->db->prepare( '%s', (string) $value );
			},
			$list
		);
	}

	/**
	 * Is key for a IN query.
	 *
	 * @param string $key Query key.
	 *
	 * @return boolean
	 */
	protected function key_is_in_lookup( $key ) {
		return ( '__in' === substr( $key, -4 ) );
	}

	/**
	 * Is key for a NOT IN query.
	 *
	 * @param string $key Query key.
	 *
	 * @return boolean
	 */
	protected function key_is_not_in_lookup( $key ) {
		return ( '__not_in' === substr( $key, -8 ) );
	}

	/**
	 * Map IN and NOT query keys to known database field names.
	 *
	 * @param string $key Query key name.
	 *
	 * @return string|null
	 */
	protected function key_to_field( $key ) {
		if ( $this->key_is_in_lookup( $key ) || $this->key_is_not_in_lookup( $key ) ) {
			$field = str_replace( array( 'record_', '__in', '__not_in' ), '', $key );

			return $field;
		}

		return null;
	}

	/**
	 * Check if a known lookup field is requested.
	 *
	 * @param string $field Field name.
	 *
	 * @return string|null
	 */
	protected function lookup_field_validated( $field ) {
		$field = $this->key_to_field( $field );
		if ( ! empty( $field ) && in_array( $field, $this->lookup_fields, true ) ) {
			return $field;
		}

		return null;
	}

	/**
	 * Return partial of prepare WHERE statement.
	 *
	 * @param string         $field    Field being evaluated.
	 * @param string|integer $value    Value being compared.
	 * @param string         $compare  String representation of how value should be compare (Eg. =, <=, ...).
	 * @param bool           $as_date  A type for the value to be cast to.
	 *
	 * @return string
	 */
	protected function and_where( $field, $value, $compare = '=', $as_date = false ) {
		if ( empty( $value ) ) {
			return '';
		}

		$field = "{$this->db->stream}.{$field}";
		if ( $as_date ) {
			$field = "DATE({$field})";
		}

		if ( is_numeric( $value ) ) {
			$placeholder = '%d';
		} else {
			$placeholder = '%s';
		}

		return $this->db->prepare( " AND {$field} {$compare} {$placeholder}", $value );
	}

	/**
	 * Return the proper compare operator for the date comparing type provided.
	 *
	 * @param string $date_type  Date type.
	 *
	 * @return string|null
	 */
	protected function get_date_compare( $date_type ) {
		switch ( $date_type ) {
			case 'date_from':
				return '>=';
			case 'date_to':
				return '<=';
			case 'date_after':
				return '>';
			case 'date_before':
				return '<';
			default:
				return null;
		}
	}
}
