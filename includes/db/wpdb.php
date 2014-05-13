<?php

class WP_Stream_DB_WPDB extends WP_Stream_DB_Base {

	public static $table;

	public static $table_meta;

	public $found_rows;

	public function __construct() {
		global $wpdb;

		/**
		 * Allows devs to alter the tables prefix, default to base_prefix
		 *
		 * @param  string  database prefix
		 * @return string  udpated database prefix
		 */
		$prefix = apply_filters( 'wp_stream_db_tables_prefix', $wpdb->base_prefix );

		self::$table         = $prefix . 'stream';
		self::$table_meta    = $prefix . 'stream_meta';

		$wpdb->stream        = self::$table;
		$wpdb->streammeta    = self::$table_meta;

		// Hack for get_metadata
		$wpdb->recordmeta = self::$table_meta;
	}

	/**
	 * Public getter to return table names;
	 *
	 * @return array
	 */
	public function get_table_names() {
		return array(
			self::$table,
			self::$table_meta,
		);
	}

	public function install() {
		/**
		 * Filter will halt install() if set to true
		 *
		 * @param  bool
		 * @return bool
		 */
		if ( apply_filters( 'wp_stream_no_tables', false ) ) {
			return;
		}

		// Install plugin tables
		require_once WP_STREAM_INC_DIR . 'db/install/wpdb.php';
		$update = WP_Stream_Install_WPDB::get_instance();
	}

	protected function insert( $data ) {
		global $wpdb;

		$recordarr = $data;
		unset( $recordarr['meta'] );

		// Fallback from `contexts` to the new flat table structure
		if ( isset( $recordarr['contexts'] ) ) {
			$recordarr['action'] = reset( $recordarr['contexts'] );
			$recordarr['context'] = key( $recordarr['contexts'] );
			unset( $recordarr['contexts'] );
		}

		$result = $wpdb->insert(
			self::$table,
			$recordarr
		);

		if ( empty( $wpdb->last_error ) ) {
			$record_id = $wpdb->insert_id;
		} else {
			return new WP_Error( 'record-insert-error', $wpdb->last_error );
		}

		$this->prev_record = $record_id;

		foreach ( $data['meta'] as $key => $vals ) {
			if ( ! is_array( $vals ) ) {
				$vals = array( $vals );
			}
			if ( 0 !== key( $vals ) ) {
				$vals = array( $vals );
			}
			foreach ( $vals as $val ) {
				$this->add_meta( $record_id, $key, $val );
			}
		}

		return $record_id;
	}

	public function get_meta( $record_id, $key, $single = false ) {
		return get_metadata( 'record', $record_id, $key, $single = false );
	}

	public function add_meta( $record_id, $key, $val ) {
		return add_metadata( 'record', $record_id, $key, $val );
	}

	public function update_meta( $record_id, $key, $val, $prev = null ) {
		return update_metadata( 'record', $record_id, $key, $val, $prev );
	}

	public function delete_meta( $record_id, $key, $val = null, $delete_all = false ) {
		return delete_metadata( 'record', $record_id, $key, $val = null, $delete_all = false );
	}

	/**
	 * TODO Update a record information
	 *
	 * @param  array  $data Record data
	 * @return mixed        Record ID if successful, WP_Error if not
	 */
	protected function update( $data ) {
	}

	public function query( $query ) {
		global $wpdb;

		$join  = array();
		$where = array();

		/**
		 * PARSE CORE FILTERS
		 */
		foreach ( $query as $column => $rules ) {
			// Skip special query parameters
			if ( 0 === strpos( $column, '_' ) ) {
				continue;
			}
			$db_col = "$wpdb->stream.{$column}";
			// Handle date column properly
			if ( 'created' === $column ) {
				$db_col = "DATE($db_col)";
			}
			foreach ( $rules as $operator => $value ) {
				$where[] = $this->parse_rule( $db_col, $operator, $value );
			}
		}

		/**
		 * PARSE META FILTERS
		 */
		if ( isset( $query['_meta'] ) ) {
			$aliases = array(); // Table aliases for meta table joins
			foreach ( $query['_meta'] as $meta_key => $rules ) {
				// Handling meta-table aliasing
				$alias     = 'meta_' . $meta_key . '_' . $operator;
				$aliases[ $alias ] = $meta_key; // Use this to create a join statement later
				$db_col = "{$alias}.meta_value";
				foreach ( $rules as $operator => $value ) {
					$where[] = $this->parse_rule( $db_col, $operator, $value );
				}
			}

			// Create join statements from added aliases
			if ( $aliases ) {
				foreach ( $aliases as $alias => $meta_key ) {
					$joins[ $alias ] = $wpdb->prepare(
						"LEFT JOIN `$wpdb->stream_meta` `$alias`
						ON `$alias`.`record_id` = `$wpdb->stream`.`ID`
						AND `$alias`.`meta_key` = %s",
						$meta_key
					);
				}
			}
		}

		/**
		 * PARSE PAGINATION PARAMS
		 */
		$limit = '';
		if ( isset( $query['_perpage'] ) ) {
			$offset  = $query['_offset'];
			$perpage = $query['_perpage'];
			$limit   = "LIMIT $offset, $perpage";
		}

		/**
		 * PARSE ORDER PARAMS
		 */
		$orderby = sprintf( 'ORDER BY %s %s', key( $query['_order'] ), reset( $query['_order'] ) );


		/**
		 * PARSE SELECT PARAMETER
		 */
		if ( empty( $query['_select'] ) || '*' === $query['_select'][0] ) {
			$select = "`$wpdb->stream`.*";
		} else {
			$select = array();
			foreach ( $query['_select'] as $field ) {
				$select[] = "`$wpdb->stream`.`$field`";
			}
			$select = implode( ', ', $select );

			// TODO: Implement `distinct` parameter in WP_Stream_Query
			if ( 1 === count( $fields ) && $query['_distinct'] ) {
				$select = 'DISTINCT ' . $select;
			} elseif ( ! empty( $select ) ) {
				$select .= ", `$wpdb->stream`.`ID`";
			}
		}

		/**
		 * BUILD UP THE FINAL QUERY
		 */
		$where = 'WHERE ' . implode( ' AND ', $where );
		$join  = implode( "\n", $join );
		$sql   = "SELECT SQL_CALC_FOUND_ROWS $select
		FROM $wpdb->stream
		$join
		$where
		$orderby
		$limit";

		/**
		 * Allows developers to change final SQL of Stream Query
		 *
		 * @param  string $sql   SQL statement
		 * @param  array  $args  Arguments passed to query
		 * @return string
		 */
		$sql = apply_filters( 'wp_stream_query_sql', $sql, $query );

		$results          = $wpdb->get_results( $sql );
		$this->found_rows = $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		// Removed handling select[]=meta param, wp_stream_get_meta can now be used instead
		/**
		 * Allows developers to change the final result set of records
		 *
		 * @param  array  $results SQL result
		 * @return array  Filtered array of records
		 */
		return apply_filters( 'wp_stream_query_results', $results );
	}

	/**
	 * Parse a single query rule
	 *
	 * eg: %table.%column => %operator => array %values
	 *
	 * @param  string $db_col   Column name, in table/alias.column format
	 * @param  string $operator Comparison operator, from [like/in/not_in/gt(e)/lt(e)]
	 * @param  mixed  $value    Value to compare to, can be string/array
	 * @return string           Properly escaped SQL WHERE rule
	 */
	private function parse_rule( $db_col, $operator, $value ) {
		global $wpdb;

		// Handle `like` operator
		if ( 'like' === $operator ) {
			$value   = like_escape( trim( $args['search'], '%' ) );
			return $wpdb->prepare( "$db_col LIKE %s", "%{$value}%" );
		}

		// Handle `in`/`not_in` operators
		elseif ( in_array( $operator, array( 'in', 'not_in') ) ) {
			$values  = is_array( $value ) ? $value : array( $value );
			// TODO: Think about handling integer values, specially for $format param (%s vs %d)
			$_format = count( $values ) === count( array_filter( $values, 'is_int' ) ) ? '%d' : '%s';
			$format  = '(' . join( ',', array_fill( 0, count( $values ), $_format ) ) . ')';
			$db_op   = $operator === 'in' ? 'IN' : 'NOT IN';
			return $wpdb->prepare( "$db_col {$db_op} {$format}", $values );
		}

		// Handle numerical comparison operators
		elseif ( in_array( $operator, array( 'gt', 'lt', 'gte', 'lte' ) ) ) {
			// Resolve to proper DB comparison operator
			if ( 'gt' === $operator ) {
				$db_op = '>';
			} elseif ( 'gte' === $operator ) {
				$db_op = '>=';
			} elseif ( 'lt' === $operator ) {
				$db_op = '<';
			} elseif ( 'lte' === $operator ) {
				$db_op = '<=';
			}

			$format = is_int( $value ) ? '%d' : '%s';
			return $wpdb->prepare( "$db_col $db_op $format", $value );
		}
	}

	public function delete( $ids ) {
		global $wpdb;

		if ( is_array( $ids ) ) {
			$format  = '(' . join( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$where = 'WHERE ' . $wpdb->prepare( "ID IN $format", $ids );
		} elseif ( true === $ids ) {
			$where = '';
		} else {
			return new WP_Error( 'invalid-arguments', 'Invalid arguments' );
		}

		// Remove records, and all of their meta data
		$wpdb->query( "DELETE FROM $wpdb->stream $where" );
		$where = str_replace( 'ID', 'record_id', $where );
		$wpdb->query( "DELETE FROM $wpdb->streammeta $where" );
	}

	public function reset() {
		global $wpdb;
		// Delete all tables
		foreach ( $this->get_table_names() as $table ) {
			$wpdb->query( "DROP TABLE $table" );
		}
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
	 * @return array   Array of distinct values
	 */
	public function get_col( $column ) {
		global $wpdb;
		$values = $wpdb->get_col( "SELECT DISTINCT {$column} FROM {$wpdb->stream}" );
		return $values;
	}

	/**
	 * Get total count of the last query using query() method
	 *
	 * @return integer  Total item count
	 */
	public function get_found_rows() {
		return $this->found_rows;
	}

}

WP_Stream::$db = new WP_Stream_DB_WPDB();
