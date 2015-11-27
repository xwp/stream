<?php
namespace WP_Stream;

class DB {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Hold Query class
	 * @var Query
	 */
	public $query;

	/**
	 * Hold records table name
	 *
	 * @var string
	 */
	public $table;

	/**
	 * Hold meta table name
	 *
	 * @var string
	 */
	public $table_meta;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->query  = new Query( $this );

		global $wpdb;

		/**
		 * Allows devs to alter the tables prefix, default to base_prefix
		 *
		 * @param string $prefix
		 *
		 * @return string
		 */
		$prefix = apply_filters( 'wp_stream_db_tables_prefix', $wpdb->base_prefix );

		$this->table         = $prefix . 'stream';
		$this->table_meta    = $prefix . 'stream_meta';

		$wpdb->stream        = $this->table;
		$wpdb->streammeta    = $this->table_meta;

		// Hack for get_metadata
		$wpdb->recordmeta    = $this->table_meta;
	}

	/**
	 * Public getter to return table names
	 *
	 * @return array
	 */
	public function get_table_names() {
		return array(
			$this->table,
			$this->table_meta,
		);
	}

	/**
	 * Insert a record
	 *
	 * @param array $recordarr
	 *
	 * @return int
	 */
	public function insert( $recordarr ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return false;
		}

		/**
		 * Filter allows modification of record information
		 *
		 * @param array $recordarr
		 *
		 * @return array
		 */
		$recordarr = apply_filters( 'wp_stream_record_array', $recordarr );

		if ( empty( $recordarr ) ) {
			return false;
		}

		global $wpdb;

		$fields = array( 'object_id', 'site_id', 'blog_id', 'user_id', 'user_role', 'created', 'summary', 'ip', 'connector', 'context', 'action' );
		$data   = array_intersect_key( $recordarr, array_flip( $fields ) );

		$result = $wpdb->insert( $this->table, $data );

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
	 * @param int    $record_id
	 * @param string $key
	 * @param string $val
	 *
	 * @return array
	 */
	public function insert_meta( $record_id, $key, $val ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table_meta,
			array(
				'record_id'  => $record_id,
				'meta_key'   => $key,
				'meta_value' => $val,
			)
		);

		return $result;
	}

	/**
	 * Returns array of existing values for requested column.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * GROUP BY allows query to find just the first occurance of each value in the column,
	 * increasing the efficiency of the query.
	 *
	 * @see assemble_records
	 * @since 1.0.4
	 *
	 * @param string $column
	 *
	 * @return array
	 */
	function existing_records( $column ) {
		global $wpdb;

		// Sanitize column
		$allowed_columns = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'created', 'summary', 'connector', 'context', 'action', 'ip' );
		if ( ! in_array( $column, $allowed_columns ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			"SELECT DISTINCT $column FROM $wpdb->stream", // @codingStandardsIgnoreLine can't prepare column name
			'ARRAY_A'
		);

		if ( is_array( $rows ) && ! empty( $rows ) ) {
			$output_array = array();

			foreach ( $rows as $row ) {
				foreach ( $row as $cell => $value ) {
					$output_array[ $value ] = $value;
				}
			}

			return (array) $output_array;
		}

		$column = sprintf( 'stream_%s', $column );

		return isset( $this->plugin->connectors->term_labels[ $column ] ) ? $this->plugin->connectors->term_labels[ $column ] : array();
	}

	/**
	 * Helper function for calling $this->query->query()
	 *
	 * @see Query->query()
	 *
	 * @param array Query args
	 *
	 * @return array Stream Records
	 */
	function query( $args ) {
		return $this->query->query( $args );
	}
}
