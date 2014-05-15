<?php

class WP_Stream_DB_Mongo extends WP_Stream_DB_Base {

	/**
	 * Mongo Connection
	 * @var MongoClient
	 */
	private static $conn;

	/**
	 * Mongo DB Object
	 * @var MongoDB
	 */
	private static $db;

	/**
	 * Mongo Collection Object
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
		 * @var string
		 */
		$db  = apply_filters( 'wp_stream_db_mongo_collection', 'stream' ); // TODO: Provide settings panel for this

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
			continue;
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
		self::$db->createCollection('stream');
		$fields = get_class_vars('WP_Stream_Record');
		self::$db->createIndex( $fields );
	}

	/**
	 * Remove DB tables/schema
	 */
	public function reset() {
		self::$coll->drop();
	}

	function parse( $args ) {
		$args = parent::parse( $args );

		// ignore_contexts is not really useful here since we're
		// using document-based DB, so we'll skip that

		$query = array();

		$whitelisted = array(
			'object_id',
			'type',
			'ip',
			'author_role',
			'visibility',
			'site_id',
			'blog_id',
		);

		foreach ( $whitelisted as $key ) {
			if ( $args[ $key ] ) {
				$query[ $key ] = $args[ $key ];
			}
		}

		if ( $args['author'] || 0 === $args['author'] ) {
			$query['author'] = intval( $args['author'] );
		}

		if ( $args['search'] ) {
			$search = sprintf( '/%s/i', trim( $args['search'], '%' ) );

			$query[ $args['search_field'] ]['$regex'] = $search;
		}

		// record_greater_than, curtsey of live-update
		if ( $args['record_greater_than'] ) {
			$query['_id']['$gt'] = new MongoId( $args['record_greater_than'] );
		}

		if ( $args['record__in'] ) {
			$query['_id']['$in'] = array_map(
				array( $this, 'create_mongo_id' ),
				(array) $args['record__in']
			);
		}

		if ( $args['record__not_in'] ) {
			$query['_id']['$nin'] = array_map(
				array( $this, 'create_mongo_id' ),
				(array) $args['record__not_in']
			);
		}

		if ( $args['record_parent'] ) {
			$query['parent'] = $args['record_parent'];
		}

		if ( $args['record_parent__in'] ) {
			$query['parent']['$in'] = array_map(
				array( $this, 'create_mongo_id' ),
				(array) $args['record_parent__in']
			);
		}

		if ( $args['record_parent__not_in'] ) {
			$query['parent']['$nin'] = array_map(
				array( $this, 'create_mongo_id' ),
				(array) $args['record_parent__not_in']
			);
		}

		if ( $args['author__in'] ) {
			$author__in = array_filter( (array) $args['author__in'], 'is_numeric' );
			if ( $author__in ) {
				$query['author']['$in'] = $author__in;
			}
		}

		if ( $args['author__not_in'] ) {
			$author__not_in = array_filter( (array) $args['author__not_in'], 'is_numeric' );
			if ( $author__not_in ) {
				$query['author']['$nin'] = $author__not_in;
			}
		}

		if ( $args['author_role__in'] ) {
			$query['author_role']['$in'] = (array) $args['author_role__in'];
		}

		if ( $args['author_role__not_in'] ) {
			$query['author_role']['$nin'] = (array) $args['author_role__not_in'];
		}

		if ( $args['ip__in'] ) {
			$query['ip']['$in'] = (array) $args['ip__in'];
		}

		if ( $args['ip__not_in'] ) {
			$query['ip']['$nin'] = (array) $args['ip__not_in'];
		}

		// TODO: Parsing Data filters {date/date_from/date_to}
		if ( $args['date'] ) {
			$date_from = strtotime( $args['date'] );
			$date_to   = $date_from;
		} else {
			if ( $args['date_from'] ) {
				$date_from = strtotime( $args['date_from'] );
			}
			if ( $args['date_to'] ) {
				$date_to = strtotime( $args['date_to'] );
			}
		}

		if ( isset( $date_from ) ) {
			$query['created']['$gt'] = $this->create_mongo_date( $date_from );
		}

		if ( isset( $date_to ) ) {
			$date_to += DAY_IN_SECONDS - 1; // till end of the day
			$query['created']['$lt'] = $this->create_mongo_date( $date_to );
		}

		// TODO: Meta query
		// TODO: Context query

		// Pagination params handled by query()
		// Order params handled by query()
		// Fields/Distinct params handled by query()

		return array( $args, $query );
	}

	function query( $args ) {
		list( $args, $query ) = $this->parse( $args );

		/**
		 * PARSE FIELDS PARAMETER
		 */
		$select = array();

		if ( $args['fields'] ) {
			$fields = array_filter( explode( ',', $args['fields'] ) );
			$fields = array_intersect( $fields, array_keys( get_class_vars( 'WP_Stream_Record' ) ) );
			$fields = array_diff( $fields, array( 'meta', 'contexts' ) );
			$select = array_fill_keys( $fields, 1 );
		}

		$distinct = ( 1 === count( $fields ) && $args['distinct'] );

		$cursor = $distinct ? self::$coll->distinct( key( $select ), $query ) : self::$coll->find( $query );

		/**
		 * PARSE SORTING/ORDER PARAMS
		 */
		if ( $args['order'] && $args['orderby'] ) {
			$order   = 'desc' === strtolower( $args['order'] ) ? -1 : 1;
			$orderby = ( empty( $args['orderby'] ) || 'ID' === $args['orderby'] ) ? '_id' : $args['orderby'];

			$cursor->sort( array( $orderby => $order ) );
		}

		/**
		 * PARSE PAGINATION AND LIMIT
		 */
		$perpage = intval( $args['records_per_page'] );

		if ( -1 === $perpage ) {
			$perpage = 0;
		}

		$cursor->limit( $perpage );

		// Pagination
		$paged = intval( $args['paged'] );

		if ( $perpage > 0 && $paged > 1 ) {
			$cursor->skip( ( $paged - 1 ) * $perpage );
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
				$object          = (object) $document;
				$object->ID      = (string) $document['_id'];
				$object->created = date( 'Y-m-d H:i:s', $document['created']->sec );
				$results[]       = $object;
			}
		}

		return $results;
	}

	/**
	 * Insert a new record
	 *
	 * @internal Used by store()
	 * @param  array   $data Record data
	 * @return integer       ID of the inserted record
	 */
	protected function insert( $data ) {
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
	 * @param  array    $data Record data to be updated, must include ID
	 * @return mixed          True if successful, WP_Error if not
	 */
	protected function update( $data ) {
		return (bool) self::$coll->update( array( '_id' => $data['_id'] ), $data );
	}

	// TODO
	function get_found_rows() {
		return 0;
	}

	/**
	 * Delete records with matching IDs
	 * @param  array|true $ids Array of IDs, or True to delete all records
	 * @return mixed           True if no errors, WP_Error if arguments fail
	 */
	function delete( $ids ) {
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
	 * @return array   Array of distinct values
	 */
	public function get_col( $column ) {
		return self::$coll->distinct( $column );
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
		if ( $record = self::$coll->findOne( array( '_id' => $this->create_mongo_id( $id ) ) ) ) {
			if ( $key ) {
				$meta = $record->meta->{$key};
				if ( empty( $meta ) ) {
					return false;
				}
				$meta = (array) $meta;
				if ( $single ) {
					return $meta[0];
				} else {
					return $meta;
				}
			} else {
				return $record->meta;
			}
		}
		else {
			return false;
		}
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
	 * @param  integer $record_id Record ID
	 * @param  string  $key       Meta key
	 * @param  mixed   $val       Meta value, will be serialized if non-scalar
	 * @param  mixed   $prev      Optional, Previous Meta value to replace, will be serialized if non-scalar
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
					$meta[ $key  ] = array( $val );
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
	 * @param  integer $record_id  Record ID, can be omitted if delete_all=true
	 * @param  string  $key        Meta key
	 * @param  mixed   $val        Optional, only delete entries with this value, will be serialized if non-scalar
	 * @param  boolean $delete_all Default: false, delete all matching entries from all objects
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
