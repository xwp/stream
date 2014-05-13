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

		$join  = '';
		$where = '';

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

		if ( is_numeric( $args['site_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.site_id = %d", $args['site_id'] );
		}

		if ( is_numeric( $args['blog_id'] ) ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.blog_id = %d", $args['blog_id'] );
		}

		if ( $args['search'] ) {
			$search_field = $args['search_field'];
			$search = sprintf( '%%%s%%', like_escape( trim( $args['search'], '%' ) ) );
			$where .= $wpdb->prepare(
				" AND $wpdb->stream.{$search_field} LIKE %s",
				$search
			);
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
		 * PARSE DATE FILTERS
		 */
		if ( $args['date'] ) {
			$where .= $wpdb->prepare( " AND DATE($wpdb->stream.created) = %s", $args['date'] );
		} else {
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
			$record__in = array_filter( (array) $args['record__in'], 'is_numeric' );
			if ( ! empty( $record__in ) ) {
				$record__in_format = '(' . join( ',', array_fill( 0, count( $record__in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.ID IN {$record__in_format}", $record__in );
			}
		}

		if ( $args['record__not_in'] ) {
			$record__not_in = array_filter( (array) $args['record__not_in'], 'is_numeric' );
			if ( ! empty( $record__not_in ) ) {
				$record__not_in_format = '(' . join( ',', array_fill( 0, count( $record__not_in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.ID NOT IN {$record__not_in_format}", $record__not_in );
			}
		}

		if ( $args['record_parent'] ) {
			$where .= $wpdb->prepare( " AND $wpdb->stream.parent = %d", (int) $args['record_parent'] );
		}

		if ( $args['record_parent__in'] ) {
			$record_parent__in = array_filter( (array) $args['record_parent__in'], 'is_numeric' );
			if ( ! empty( $record_parent__in ) ) {
				$record_parent__in_format = '(' . join( ',', array_fill( 0, count( $record_parent__in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.parent IN {$record_parent__in_format}", $record_parent__in );
			}
		}

		if ( $args['record_parent__not_in'] ) {
			$record_parent__not_in = array_filter( (array) $args['record_parent__not_in'], 'is_numeric' );
			if ( ! empty( $record_parent__not_in ) ) {
				$record_parent__not_in_format = '(' . join( ',', array_fill( 0, count( $record_parent__not_in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.parent NOT IN {$record_parent__not_in_format}", $record_parent__not_in );
			}
		}

		if ( $args['author__in'] ) {
			$author__in = array_filter( (array) $args['author__in'], 'is_numeric' );
			if ( ! empty( $author__in ) ) {
				$author__in_format = '(' . join( ',', array_fill( 0, count( $author__in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.author IN {$author__in_format}", $author__in );
			}
		}

		if ( $args['author__not_in'] ) {
			$author__not_in = array_filter( (array) $args['author__not_in'], 'is_numeric' );
			if ( ! empty( $author__not_in ) ) {
				$author__not_in_format = '(' . join( ',', array_fill( 0, count( $author__not_in ), '%d' ) ) . ')';
				$where .= $wpdb->prepare( " AND $wpdb->stream.author NOT IN {$author__not_in_format}", $author__not_in );
			}
		}

		if ( $args['author_role__in'] ) {
			$author_role__in = '(' . join( ',', array_fill( 0, count( $args['author_role__in'] ), '%s' ) ) . ')';
			$where          .= $wpdb->prepare( " AND $wpdb->stream.author_role IN {$author_role__in}", $args['author_role__in'] );
		}

		if ( $args['author_role__not_in'] ) {
			$author_role__not_in = '(' . join( ',', array_fill( 0, count( $args['author_role__not_in'] ), '%s' ) ) . ')';
			$where              .= $wpdb->prepare( " AND $wpdb->stream.author_role NOT IN {$author_role__not_in}", $args['author_role__not_in'] );
		}

		if ( $args['ip__in'] ) {
			$ip__in = '(' . join( ',', array_fill( 0, count( $args['ip__in'] ), '%s' ) ) . ')';
			$where .= $wpdb->prepare( " AND $wpdb->stream.ip IN {$ip__in}", $args['ip__in'] );
		}

		if ( $args['ip__not_in'] ) {
			$ip__not_in = '(' . join( ',', array_fill( 0, count( $args['ip__not_in'] ), '%s' ) ) . ')';
			$where     .= $wpdb->prepare( " AND $wpdb->stream.ip NOT IN {$ip__not_in}", $args['ip__not_in'] );
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
		$orderable = array( 'ID', 'site_id', 'blog_id', 'object_id', 'author', 'author_role', 'summary', 'visibility', 'parent', 'type', 'created' );

		if ( in_array( $orderby, $orderable ) ) {
			$orderby = $wpdb->stream . '.' . $orderby;
		} elseif ( in_array( $orderby, array( 'connector', 'context', 'action' ) ) ) {
			$orderby = $wpdb->streamcontext . '.' . $orderby;
		} elseif ( 'meta_value_num' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "CAST($wpdb->streammeta.meta_value AS SIGNED)";
		} elseif ( 'meta_value' === $orderby && ! empty( $args['meta_key'] ) ) {
			$orderby = "$wpdb->streammeta.meta_value";
		} else {
			$orderby = "$wpdb->stream.ID";
		}
		$orderby = 'ORDER BY ' . $orderby . ' ' . $order;

		/**
		 * PARSE FIELDS PARAMETER
		 */
		$fields = array_filter( explode( ',', $args['fields'] ) );
		$fields = array_intersect( $fields, array_keys( get_class_vars( 'WP_Stream_Record' ) ) );
		$select = "$wpdb->stream.*";

		if ( ! $args['ignore_context'] ) {
			$select .= ", $wpdb->streamcontext.context, $wpdb->streamcontext.action, $wpdb->streamcontext.connector";
		}

		if ( ! empty( $fields ) ) {
			$select = array();
			foreach ( $fields as $field ) {
				// Escape 'meta' and 'contexts' fields
				if ( in_array( $field, array( 'meta', 'contexts' ) ) ) {
					continue;
				}
				$select[] = "{$wpdb->stream}.{$field}";
			}
			$select = implode( ', ', $select );
		}

		if ( 1 === count( $fields ) && $args['distinct'] ) {
			$select = 'DISTINCT ' . $select;
		} elseif ( ! empty( $fields ) ) {
			$select .= ", $wpdb->stream.ID";
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

		$results          = $wpdb->get_results( $sql );
		$this->found_rows = $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		if ( in_array( 'meta', $fields ) && is_array( $results ) && $results ) {
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

	public function delete( $args ) {
		global $wpdb;

		if ( $args ) {
			// Only get IDs
			$args = array_merge(
				$args,
				array(
					'fields'           => 'ID',
					'records_per_page' => -1,
				)
			);
			$records = $this->query( $args );
			$ids     = wp_list_pluck( $records, 'ID' );

			if ( ! $ids ) {
				return false;
			}
			$where = sprintf( 'ID IN ( %s )', implode( ',', $ids ) );
		} else {
			$where = '1=1';
		}

		// Remove records, and all of their meta/context data
		$wpdb->query( "DELETE FROM $wpdb->stream WHERE $where" );
		$where = str_replace( 'ID', 'record_id', $where );
		$wpdb->query( "DELETE FROM $wpdb->streammeta WHERE $where" );
		$wpdb->query( "DELETE FROM $wpdb->streamcontext WHERE $where" );
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
