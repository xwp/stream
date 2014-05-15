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

	/**
	 * Verify that all needed databases are present and add an error message if not.
	 */
	public function check_db() {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		/**
		 * Filter will halt DB check if set to true
		 *
		 * @param  bool
		 * @return bool
		 */
		if ( apply_filters( 'wp_stream_no_tables', false ) ) {
			return;
		}

		global $wpdb;

		$database_message  = '';
		$uninstall_message = '';

		// Check if all needed DB is present
		foreach ( $this->get_table_names() as $table_name ) {
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
				$database_message .= sprintf( '%s %s', __( 'The following table is not present in the WordPress database:', 'stream' ), $table_name );
			}
		}

		if ( is_plugin_active_for_network( WP_STREAM_PLUGIN ) && current_user_can( 'manage_network_plugins' ) ) {
			$uninstall_message = sprintf( __( 'Please <a href="%s">uninstall</a> the Stream plugin and activate it again.', 'stream' ), network_admin_url( 'plugins.php#stream' ) );
		} elseif ( current_user_can( 'activate_plugins' ) ) {
			$uninstall_message = sprintf( __( 'Please <a href="%s">uninstall</a> the Stream plugin and activate it again.', 'stream' ), admin_url( 'plugins.php#stream' ) );
		}

		// Check upgrade routine
		$this->install();

		if ( ! empty( $database_message ) ) {
			WP_Stream::notice( $database_message );
			if ( ! empty( $uninstall_message ) ) {
				WP_Stream::notice( $uninstall_message );
			}
		}
	}

	/**
	 * Install or update DB schema
	 *
	 * @internal Used by check_db()
	 */
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

	/**
	 * Remove DB tables/schema
	 */
	public function reset() {
		global $wpdb;
		// Delete all tables
		foreach ( $this->get_table_names() as $table ) {
			$wpdb->query( "DROP TABLE $table" );
		}
	}

	/**
	 * Insert a new record
	 *
	 * @internal Used by store()
	 * @param  array   $data Record data
	 * @return integer       ID of the inserted record
	 */
	protected function insert( array $data ) {
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

	/**
	 * Update a single record
	 * TODO: Implement
	 *
	 * @internal Used by store()
	 * @param  array    $data Record data to be updated, must include ID
	 * @return mixed          True if successful, WP_Error if not
	 */
	protected function update( array $data ) {
	}

	/**
	 * Query records
	 *
	 * @internal Used by WP_Stream_Query, and is not designed to be called explicitly
	 * @param  array  $query Query arguments
	 * @return array         List of records that match query
	 */
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

	/**
	 * Get total count of the last query using query() method
	 *
	 * @return integer  Total item count
	 */
	public function get_found_rows() {
		return $this->found_rows;
	}

	/**
	 * Delete records with matching IDs
	 * @param  array|true $ids Array of IDs, or True to delete all records
	 * @return mixed           True if no errors, WP_Error if arguments fail
	 */
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
		return true;
	}

	/**
	 * Returns array of existing values for requested column.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * @param  string  Requested Column (i.e., 'context')
	 * @return array   Array of distinct values
	 */
	public function get_col( $column ) {
		global $wpdb;
		$values = $wpdb->get_col( "SELECT DISTINCT {$column} FROM {$wpdb->stream}" );
		return $values;
	}

	/**
	 * Retrieve metadata of a single record
	 *
	 * @internal User by wp_stream_get_meta()
	 * @param  integer $record_id Record ID
	 * @param  string  $key       Optional, Meta key, if omitted, retrieve all meta data of this record.
	 * @param  boolean $single    Default: false, Return single meta value, or all meta values under specified key.
	 * @return string|array       Single/Array of meta data.
	 */
	public function get_meta( $record_id, $key, $single = false ) {
		return get_metadata( 'record', $record_id, $key, $single );
	}

	/**
	 * Add metadata for a single record
	 *
	 * @internal User by wp_stream_add_meta()
	 * @param  integer $record_id Record ID
	 * @param  string  $key       Meta key
	 * @param  mixed   $val       Meta value, will be serialized if non-scalar
	 * @return bool               True on success, false on failure
	 */
	public function add_meta( $record_id, $key, $val ) {
		return (bool) add_metadata( 'record', $record_id, $key, $val );
	}

	/**
	 * Update metadata for a single record
	 *
	 * @internal User by wp_stream_update_meta()
	 * @param  integer $record_id Record ID
	 * @param  string  $key       Meta key
	 * @param  mixed   $val       Meta value, will be serialized if non-scalar
	 * @param  mixed   $prev      Optional, Previous Meta value to replace, will be serialized if non-scalar
	 * @return bool               True on successful update, false on failure
	 */
	public function update_meta( $record_id, $key, $val, $prev = null ) {
		return (bool) update_metadata( 'record', $record_id, $key, $val, $prev );
	}

	/**
	 * Delete metadata for specified record(s)
	 *
	 * @internal Used by wp_stream_delete_meta()
	 * @param  integer $record_id  Record ID, can be omitted if delete_all=true
	 * @param  string  $key        Meta key
	 * @param  mixed   $val        Optional, only delete entries with this value, will be serialized if non-scalar
	 * @param  boolean $delete_all Default: false, delete all matching entries from all objects
	 * @return boolean             True on success, false on failure
	 */
	public function delete_meta( $record_id, $key, $val = null, $delete_all = false ) {
		return delete_metadata( 'record', $record_id, $key, $val, $delete_all );
	}
}

WP_Stream::$db = new WP_Stream_DB_WPDB();
