<?php

class WP_Stream_DB {

	/**
	 * Hold class instance
	 *
	 * @access public
	 * @static
	 *
	 * @var WP_Stream_DB
	 */
	public static $instance;

	/**
	 * Hold records table name
	 *
	 * @access public
	 * @static
	 *
	 * @var string
	 */
	public static $table;

	/**
	 * Hold meta table name
	 *
	 * @access public
	 * @static
	 *
	 * @var string
	 */
	public static $table_meta;

	/**
	 * Class constructor
	 *
	 * @access public
	 */
	public function __construct() {
		global $wpdb;

		/**
		 * Allows devs to alter the tables prefix, default to base_prefix
		 *
		 * @param string $prefix
		 *
		 * @return string
		 */
		$prefix = apply_filters( 'wp_stream_db_tables_prefix', $wpdb->base_prefix );

		self::$table      = $prefix . 'stream';
		self::$table_meta = $prefix . 'stream_meta';

		$wpdb->stream     = self::$table;
		$wpdb->streammeta = self::$table_meta;
		$wpdb->recordmeta = self::$table_meta;
	}

	/**
	 * Return an active instance of this class, and create one if it doesn't exist
	 *
	 * @access public
	 * @static
	 *
	 * @return WP_Stream_DB
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Public getter to return table names
	 *
	 * @access public
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
	 * Insert a record
	 *
	 * @access public
	 *
	 * @param array $recordarr
	 *
	 * @return int
	 */
	public function insert( $recordarr ) {
		/**
		 * Filter allows modification of record information
		 *
		 * @param array $recordarr
		 *
		 * @return array
		 */
		$recordarr = apply_filters( 'wp_stream_record_array', $recordarr );

		if ( empty( $recordarr ) ) {
			return;
		}

		global $wpdb;

		$fields = array( 'object_id', 'site_id', 'blog_id', 'user_id', 'user_role', 'created', 'summary', 'ip', 'connector', 'context', 'action' );
		$data   = array_intersect_key( $recordarr, array_flip( $fields ) );
		$data   = array_filter( $data );
		$result = $wpdb->insert( self::$table, $data );

		if ( 1 !== $result ) {
			/**
			 * Fires on a record insertion error
			 *
			 * @param array $recordarr
			 * @param mixed $result
			 */
			do_action( 'wp_stream_record_insert_error', $recordarr, $result );

			return $result;
		}

		$record_id = $wpdb->insert_id;

		// Insert record meta
		foreach ( (array) $recordarr['meta'] as $key => $vals ) {
			// If associative array, serialize it, otherwise loop on its members
			$vals = ( is_array( $vals ) && 0 !== key( $vals ) ) ? array( $vals ) : $vals;

			foreach ( (array) $vals as $val ) {
				$val = maybe_serialize( $val );

				$this->insert_meta( $record_id, $key, $val );
			}
		}

		/**
		 * Fires after a record has been inserted
		 *
		 * @param int   $record_id
		 * @param array $recordarr
		 */
		do_action( 'wp_stream_record_inserted', $record_id, $recordarr );

		return absint( $record_id );
	}

	/**
	 * Insert record meta
	 *
	 * @access public
	 *
	 * @param int    $record_id
	 * @param string $key
	 * @param string $val
	 *
	 * @return array
	 */
	public function insert_meta( $record_id, $key, $val ) {
		global $wpdb;

		$result = $wpdb->insert(
			self::$table_meta,
			array(
				'record_id'  => $record_id,
				'meta_key'   => $key,
				'meta_value' => $val,
			)
		);

		return $result;
	}

}
