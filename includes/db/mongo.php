<?php

class WP_Stream_DB_Mongo extends WP_Stream_DB_Base {

	private static $conn;
	private static $db;
	private static $coll;

	public function __construct() {
		// Check requirements
		if ( ! class_exists( 'MongoClient' ) ) {
			wp_die( esc_html__( 'Mongo PHP extension is not loaded, therefore you cannot use Mongo as your DB adapter', 'stream' ), esc_html__( 'Stream DB Error', 'stream' ) );
		}

		$dsn = ''; // TODO: Provide settings panel for this
		$db  = 'stream'; // TODO: Provide settings panel for this

		self::$conn = new MongoClient( $dsn );
		self::$db   = self::$conn->{$db};
		self::$coll = self::$db->stream;

		// TODO: ->ENSUREINDEX()
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

	protected function insert( $data ) {
		// Resolve the context=>action complex
		// Pending decision to eliminate the multiple contexts requirement
		$data['action']  = reset( $data['contexts'] );
		$data['context'] = key( $data['contexts'] );
		$data['created'] = $this->create_mongo_date( $data['created'] );

		unset( $data['contexts'] );

		self::$coll->insert( $data );
	}

	protected function update( $data ) {

	}

	function delete( $args ) {
		$query = $this->parse( $args );

		self::$coll->remove( $query );
	}

	function reset() {
		self::$db->drop();
	}

	function get_existing_records( $column, $table = '' ) {
		return array();
	}

	function get_found_rows() {
		return 0;
	}

	private function create_mongo_id( $string ) {
		return new MongoId( $string );
	}

	private function create_mongo_date( $time ) {
		if ( ! is_numeric( $time ) ) {
			$time = strtotime( $time );
		}

		return new MongoDate( $time );
	}

}

WP_Stream::$db = new WP_Stream_DB_Mongo();
