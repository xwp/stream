<?php

class WP_Stream_DB {

	public static $instance;

	public static $table;

	public static $table_meta;

	public static $table_context;

	public function __construct() {
		global $wpdb;

		/**
		 * Allows devs to alter the tables prefix, default to base_prefix
		 *
		 * @param  string  database prefix
		 * @return string  udpated database prefix
		 */
		$prefix = apply_filters( 'wp_stream_db_tables_prefix', $wpdb->prefix );

		self::$table         = $prefix . 'stream';
		self::$table_meta    = $prefix . 'stream_meta';
		self::$table_context = $prefix . 'stream_context';

		$wpdb->stream        = self::$table;
		$wpdb->streammeta    = self::$table_meta;
		$wpdb->streamcontext = self::$table_context;

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
			self::$table_context,
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

		$fields = array( 'object_id', 'author', 'created', 'summary', 'parent', 'visibility', 'ip' );
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
			do_action( 'wp_stream_post_insert_error', $record_id );
			return $record_id;
		}

		self::$instance->prev_record = $record_id;

		$connector = $recordarr['connector'];

		foreach ( (array) $recordarr['contexts'] as $context => $action ) {
			$this->insert_context( $record_id, $connector, $context, $action );
		}

		foreach ( $recordarr['meta'] as $key => $vals ) {
			foreach ( (array) $vals as $val ) {
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

	public function insert_context( $record_id, $connector, $context, $action ) {
		global $wpdb;

		$result = $wpdb->insert(
			self::$table_context,
			array(
				'record_id' => $record_id,
				'connector' => $connector,
				'context'   => $context,
				'action'    => $action,
			)
		);

		return $result;
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
