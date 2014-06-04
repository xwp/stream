<?php

class WP_Stream_DB_Mongo extends WP_Stream_DB_Base {

	/**
	 * Mongo Connection
	 *
	 * @var MongoClient
	 */
	private static $conn;

	/**
	 * Mongo DB Object
	 *
	 * @var MongoDB
	 */
	private static $db;

	/**
	 * Mongo Collection Object
	 *
	 * @var MongoCollection
	 */
	private static $coll;

	public function __construct() {
		// Check requirements
		if ( ! class_exists( 'MongoClient' ) ) {
			wp_die( esc_html__( 'Mongo PHP extension is not loaded, therefore you cannot use Mongo as your DB adapter', 'stream' ), esc_html__( 'Stream DB Error', 'stream' ) );
		}

		/**
		 * Filter the DSN connection path for Mongo
		 * eg: mongodb://localhost:27017
		 *
		 * @var string
		 */
		$dsn = apply_filters( 'wp_stream_db_mongo_dsn', '' );

		/**
		 * Filter DB name for Stream
		 *
		 * @var string
		 */
		$db = apply_filters( 'wp_stream_db_mongo_collection', 'stream' ); // TODO: Provide settings panel for this

		self::$conn = new MongoClient( $dsn );
		self::$db   = self::$conn->{$db};
		self::$coll = self::$db->stream;
	}

	/**
	 * Runs on each page load
	 *
	 * @action init
	 */
	public function check_db() {
		// Check if collection exists
		$collections = self::$db->getCollectionNames();
		if ( in_array( 'stream', $collections ) ) {
			// No update routines here
			return;
		} else {
			// Create collection and proper indexes
			$this->install();
		}
	}

	/**
	 * Install or update DB schema
	 *
	 * @internal Used by check_db()
	 */
	public function install() {
		self::$db->createCollection( 'stream' );
		$fields = get_class_vars( 'WP_Stream_Record' );
		// @see http://docs.mongodb.org/manual/core/index-types/
		$indexes = array_fill_keys( array_keys( $fields ), 1 );

		/**
		 * Allow users to enable text indexes, which is disabled by default,
		 * because it is disabled by default in mongodb installation, and,
		 * if enabled, text indexes tend to be large in size
		 *
		 * @param  bool Enable text indexes
		 *
		 * @return bool
		 */
		if ( apply_filters( 'wp_stream_mongodb_enable_text_index', false ) ) {
			$indexes['summary'] = 'text';
		}
		foreach ( $indexes as $index => $type ) {
			$fn = method_exists( self::$coll, 'createIndex' )
				? array( self::$coll, 'createIndex' )
				: array( self::$coll, 'ensureIndex' );
			call_user_func( $fn, array( $index => $type ) );
		}

		/**
		 * Allow devs to hook into the process of installing MongoDB database
		 */
		do_action( 'wp_stream_mongodb_install' );
	}

	/**
	 * Remove DB tables/schema
	 */
	public function reset() {
		self::$coll->drop();
	}

	/**
	 * Insert a new record
	 *
	 * @internal Used by store()
	 *
	 * @param  array $data Record data
	 *
	 * @return integer       ID of the inserted record
	 */
	protected function insert( array $data ) {
		// Fallback from `contexts` to the new flat table structure
		if ( isset( $data['contexts'] ) ) {
			$data['action']  = reset( $data['contexts'] );
			$data['context'] = key( $data['contexts'] );
			unset( $data['contexts'] );
		}

		$data['created'] = $this->create_mongo_date( $data['created'] );

		// TODO: Return the last inserted ID
		self::$coll->insert( $data );
	}

	/**
	 * Update a single record
	 *
	 * @internal Used by store()
	 *
	 * @param  array $data Record data to be updated, must include ID
	 *
	 * @return mixed          True if successful, WP_Error if not
	 */
	protected function update( array $data ) {
		return (bool) self::$coll->update( array( '_id' => $data['_id'] ), $data );
	}

	/**
	 * Query records
	 *
	 * @internal Used by WP_Stream_Query, and is not designed to be called explicitly
	 *
	 * @param  array $query Query arguments
	 *
	 * @return array         List of records that match query
	 */
	public function query( $query ) {
		$_query = array();

		/**
		 * PARSE CORE FILTERS
		 */
		foreach ( $query as $column => $rules ) {
			// Skip special query parameters
			if ( 0 === strpos( $column, '_' ) ) {
				continue;
			}
			foreach ( $rules as $operator => $value ) {
				$_query = $this->parse_rule( $column, $operator, $value, $_query );
			}
		}

		/**
		 * PARSE META FILTERS
		 */
		if ( isset( $query['_meta'] ) ) {
			foreach ( $query['_meta'] as $meta_key => $rules ) {
				$meta_key = 'meta.' . $meta_key;
				foreach ( $rules as $operator => $value ) {
					$_query = $this->parse_rule( $meta_key, $operator, $value, $_query );
				}
			}
		}

		/**
		 * PARSE SELECT PARAMETER
		 */
		if ( empty( $query['_select'] ) || '*' === $query['_select'][0] ) {
			$select = array();
		} else {
			$select = $query['_select'];
		}

		/**
		 * Allows developers to change final Query
		 *
		 * @param  array $_query MongoDB Query statement
		 * @param  array $args   Arguments passed to query()
		 *
		 * @return array
		 */
		$_query = apply_filters( 'wp_stream_query_mongodb', $_query, $query );

		$distinct = ( 1 === count( $select ) && ! empty( $query['_distinct'] ) );
		$cursor   = $distinct
			? self::$coll->distinct( key( $select ), $_query )
			: self::$coll->find( $_query, $select );

		$this->found_rows = self::$coll->count( $_query );

		/**
		 * PARSE SORTING/ORDER PARAMS
		 */
		if ( isset( $query['_order'] ) ) {
			$order   = intval( 'desc' === strtolower( current( $query['_order'] ) ) ? '-1' : '1' );
			$orderby = key( $query['_order'] );
			if ( 'id' === strtolower( $orderby ) ) {
				$orderby = '_id';
			}
			$cursor->sort( array( $orderby => $order ) );
		}

		/**
		 * PARSE PAGINATION AND LIMIT
		 */
		if ( isset( $query['_perpage'] ) ) {
			$offset  = $query['_offset'];
			$perpage = $query['_perpage'];
			$cursor->skip( $offset )->limit( $perpage );
		}

		/**
		 * FORMAT RESULTS
		 */
		$results = array();

		if ( $distinct ) {
			foreach ( $cursor as $value ) {
				// Return an object as well
				$results[] = (object) array( key( $search ) => $value );
			}
		} else {
			foreach ( $cursor as $document ) {
				$document['ID']      = (string) $document['_id'];
				$document['created'] = date( 'Y-m-d H:i:s', $document['created']->sec );
				$object              = WP_Stream_Record::instance( $document );
				$results[]           = $object;
			}
		}

		/**
		 * Allows developers to change the final result set of records
		 *
		 * @param  array $results SQL result
		 *
		 * @return array  Filtered array of records
		 */

		return apply_filters( 'wp_stream_query_results', $results );
	}

	/**
	 * Parse a single query rule
	 *
	 * eg: %column => %operator => array %values
	 *
	 * @param  string $col      Column name
	 * @param  string $operator Comparison operator, from [like/in/not_in/gt(e)/lt(e)]
	 * @param  mixed  $value    Value to compare to, can be string/array
	 * @param  array  $query    Query array to append to
	 *
	 * @return array            A single MongoDB criteria rule
	 */
	private function parse_rule( $col, $operator, $value, array $query = array() ) {

		// Handle `like` operator
		if ( 'like' === $operator ) {
			$value                     = sprintf( '/%s/i', trim( $value, '%' ) );
			$query[ $col ]['$regex'] = $value;

			return $query;
		} // Handle `in`/`not_in` operators
		elseif ( in_array( $operator, array( 'in', 'not_in' ) ) ) {
			$values = is_array( $value ) ? $value : array( $value );
			$op     = $operator === 'in' ? '$in' : '$nin';
			// Handling _id column
			if ( strtolower( $col ) === 'id' ) {
				$col    = '_id';
				$values = array_map( array( $this, 'create_mongo_id' ), $values );
			}
			$query[ $col ][ $op ] = $values;

			return $query;
		} // Handle numerical comparison operators
		elseif ( in_array( $operator, array( 'gt', 'lt', 'gte', 'lte' ) ) ) {
			$op = '$' . $operator;
			// Handling dates
			if ( $col === 'created' ) {
				$value = $this->create_mongo_date( $value );
			}
			$query[ $col ][ $op ] = $value;

			return $query;
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
	 *
	 * @param  array|true $ids Array of IDs, or True to delete all records
	 *
	 * @return mixed           True if no errors, WP_Error if arguments fail
	 */
	public function delete( $ids ) {
		if ( is_array( $ids ) ) {
			$ids = array_map( array( $this, 'create_mongo_id' ), $ids );

			return self::$coll->remove( array( '_id' => array( '$in' => $ids ) ) );
		} elseif ( true === $ids ) {
			return self::$coll->remove();
		} else {
			return new WP_Error( 'invalid-arguments', 'Invalid arguments' );
		}
	}

	/**
	 * Returns array of existing values for requested column.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * @param  string  Requested Column (i.e., 'context')
	 *
	 * @return array   Array of distinct values
	 */
	public function get_col( $column ) {
		return self::$coll->distinct( $column );
	}

	/**
	 * Retrieve metadata of a single record
	 *
	 * @internal User by wp_stream_get_meta()
	 *
	 * @param  integer $record_id Record ID
	 * @param  string  $key       Optional, Meta key, if omitted, retrieve all meta data of this record.
	 * @param  boolean $single    Default: false, Return single meta value, or all meta values under specified key.
	 *
	 * @return string|array       Single/Array of meta data.
	 */
	public function get_meta( $record_id, $key, $single = false ) {
		if ( $record = self::$coll->findOne( array( '_id' => $this->create_mongo_id( $record_id ) ) ) ) {
			if ( $key ) {
				if ( ! isset( $record['meta'][ $key ] ) ) {
					return false;
				}
				$meta = $record['meta'][ $key ];
				if ( empty( $meta ) ) {
					return false;
				}
				$meta = array( $meta );
				if ( $single ) {
					return $meta[0];
				} else {
					return $meta;
				}
			} else {
				return $record['meta'];
			}
		} else {
			return false;
		}
	}

	/**
	 * Add metadata for a single record
	 *
	 * @internal User by wp_stream_add_meta()
	 *
	 * @param  integer $record_id Record ID
	 * @param  string  $key       Meta key
	 * @param  mixed   $val       Meta value, will be serialized if non-scalar
	 *
	 * @return bool               True on success, false on failure
	 */
	public function add_meta( $record_id, $key, $val ) {
		$record = self::$coll->findOne( array( '_id' => $this->create_mongo_id( $record_id ) ) );
		if ( $record ) {
			$meta = $record['meta'];
			if ( isset( $meta[ $key ] ) ) {
				$meta[ $key ] = array_merge( (array) $meta[ $key ], array( $val ) );
			} else {
				$meta[ $key ] = array( $val );
			}
			$record['meta'] = $meta;
			self::$coll->save( $record );

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Update metadata for a single record
	 *
	 * @internal User by wp_stream_update_meta()
	 *
	 * @param  integer $record_id Record ID
	 * @param  string  $key       Meta key
	 * @param  mixed   $val       Meta value, will be serialized if non-scalar
	 * @param  mixed   $prev      Optional, Previous Meta value to replace, will be serialized if non-scalar
	 *
	 * @return int|bool           Meta ID if meta-key didn't exist, true on successful update, false on failure
	 */
	public function update_meta( $record_id, $key, $val, $prev = null ) {
		$record = self::$coll->findOne( array( '_id' => $this->create_mongo_id( $record_id ) ) );
		if ( $record ) {
			$meta = $record['meta'];
			if ( isset( $prev ) && isset( $meta[ $key ] ) ) {
				$_key = array_search( $prev, (array) $meta[ $key ] );
				if ( $_key && is_array( $meta[ $key ] ) ) {
					unset( $meta[ $key ] [ $_key ] );
					$meta[ $key ][] = $val;
				} elseif ( ! is_array( $meta[ $key ] ) && $meta[ $key ] === $prev ) {
					$meta[ $key ] = array( $val );
				}
			} else {
				$meta[ $key ] = array( $val );
			}
			$record['meta'] = $meta;

			return self::$coll->save( $record );
		} else {
			return false;
		}
	}

	/**
	 * Delete metadata for specified record(s)
	 *
	 * @internal Used by wp_stream_delete_meta()
	 *
	 * @param  integer $record_id  Record ID, can be omitted if delete_all=true
	 * @param  string  $key        Meta key
	 * @param  mixed   $val        Optional, only delete entries with this value, will be serialized if non-scalar
	 * @param  boolean $delete_all Default: false, delete all matching entries from all objects
	 *
	 * @return boolean             True on success, false on failure
	 */
	public function delete_meta( $record_id, $key, $val = null, $delete_all = false ) {
		if ( ! $record_id && ! $delete_all ) {
			return false;
		}
		if ( $record_id ) {
			$record = self::$coll->findOne( array( '_id' => $this->create_mongo_id( $record_id ) ) );
			$meta   = $record['meta'];
			if ( isset( $meta[ $key ] ) ) {
				if ( isset( $val ) ) {
					if ( $_key = array_search( $val, $meta ) ) {
						unset( $meta[ $key ][ $_key ] );
					}
				} else {
					unset( $meta[ $key ] );
				}

				$record['meta'] = $meta;

				return self::$coll->save( $record );
			} else {
				return false;
			}
		} elseif ( $delete_all ) {
			// TODO: Handle batch update of records
		}
	}

	private function create_mongo_id( $string ) {
		return is_object( $string ) ? $string : new MongoId( $string );
	}

	private function create_mongo_date( $time ) {
		if ( ! is_numeric( $time ) ) {
			$time = strtotime( $time );
		}

		return new MongoDate( $time );
	}

}

WP_Stream::$db = new WP_Stream_DB_Mongo();
