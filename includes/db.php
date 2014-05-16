<?php

class WP_Stream_DB {

	public static $instance;

	public static $table;

	public static $table_meta;

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

	public static function get_instance() {
		if ( ! self::$instance ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
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

	public function insert( $recordarr ) {
		global $wpdb;

		/**
		 * Filter allows modification of record information
		 *
		 * @param  array  array of record information
		 * @return array  udpated array of record information
		 */
		$recordarr = apply_filters( 'wp_stream_record_array', $recordarr );

		// Allow extensions to handle the saving process
		if ( empty( $recordarr ) ) {
			return;
		}

		$fields = array( 'object_id', 'site_id', 'blog_id', 'author', 'author_role', 'connector', 'context', 'action', 'created', 'summary', 'parent', 'visibility', 'ip' );
		$data   = array_intersect_key( $recordarr, array_flip( $fields ) );
		$data   = array_filter( $data );

		// TODO: Check/Validate *required* fields

		$result = $wpdb->insert(
			self::$table,
			$data
		);

		if ( 1 === $result ) {
			$record_id = $wpdb->insert_id;
		} else {
			/**
			 * Action Hook that fires on an error during post insertion
			 *
			 * @param  int  $record_id  Record being inserted
			 */
			do_action( 'wp_stream_post_insert_error', $result );
			return $result;
		}

		self::$instance->prev_record = $record_id;

		foreach ( $recordarr['meta'] as $key => $vals ) {
			// If associative array, serialize it, otherwise loop on its members
			if ( is_array( $vals ) && 0 !== key( $vals ) ) {
				$vals = array( $vals );
			}
			foreach ( (array) $vals as $val ) {
				$val = maybe_serialize( $val );
				$this->insert_meta( $record_id, $key, $val );
			}
		}

		/**
		 * Fires when A Post is inserted
		 *
		 * @param  int    $record_id  Inserted record ID
		 * @param  array  $recordarr  Array of information on this record
		 */
		do_action( 'wp_stream_post_inserted', $record_id, $recordarr );

		return $record_id;
	}

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
