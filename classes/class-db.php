<?php
namespace WP_Stream;

class DB {
	/**
	 * Hold Driver class
	 * @var DB_Driver
	 */
	protected $driver;

	/**
	 * Numbers of records in last request
	 * @var Int
	 */
	protected $found_records_count = 0;

	/**
	 * Class constructor.
	 */
	public function __construct( $driver ) {
		$this->driver = $driver;
	}

	/**
	 * Insert a record
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
			return false;
		}

		$fields = array( 'object_id', 'site_id', 'blog_id', 'user_id', 'user_role', 'created', 'summary', 'ip', 'connector', 'context', 'action' );
		$data   = array_intersect_key( $recordarr, array_flip( $fields ) );

		$meta = array();
		foreach ( (array) $recordarr['meta'] as $key => $vals ) {
			// If associative array, serialize it, otherwise loop on its members
			$vals = (is_array( $vals ) && 0 !== key( $vals )) ? array( $vals ) : $vals;

			foreach ( (array) $vals as $num => $val ) {
				$vals[ $num ] = maybe_serialize( $val );
			}
			$meta[ $key ] = $vals;
		}

		$data['meta'] = $meta;

		$record_id = $this->driver->insert_record( $data );

		if ( ! $record_id ) {
			/**
			 * Fires on a record insertion error
			 *
			 * @param array $recordarr
			 * @param bool false Backwards compatibility
			 */
			do_action( 'wp_stream_record_insert_error', $recordarr, false );

			return false;
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
	public function existing_records( $column ) {
		// Sanitize column
		$allowed_columns = array( 'ID', 'site_id', 'blog_id', 'object_id', 'user_id', 'user_role', 'created', 'summary', 'connector', 'context', 'action', 'ip' );
		if ( ! in_array( $column, $allowed_columns ) ) {
			return array();
		}

		$rows = $this->driver->get_column_values( $column );

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

		$term_labels = wp_stream_get_instance()->connectors->term_labels;
		return isset( $term_labels[ $column ] ) ? $term_labels[ $column ] : array();
	}

	/**
	 * Get stream records
	 *
	 * @param array Query args
	 *
	 * @return array Stream Records
	 */
	public function get_records( $args ) {
		$defaults = array(
			// Search param
			'search'           => null,
			'search_field'     => 'summary',
			'record_after'     => null, // Deprecated, use date_after instead
			// Date-based filters
			'date'             => null, // Ex: 2015-07-01
			'date_from'        => null, // Ex: 2015-07-01
			'date_to'          => null, // Ex: 2015-07-01
			'date_after'       => null, // Ex: 2015-07-01T15:19:21+00:00
			'date_before'      => null, // Ex: 2015-07-01T15:19:21+00:00
			// Record ID filters
			'record'           => null,
			'record__in'       => array(),
			'record__not_in'   => array(),
			// Pagination params
			'records_per_page' => get_option( 'posts_per_page', 20 ),
			'paged'            => 1,
			// Order
			'order'            => 'desc',
			'orderby'          => 'date',
			// Fields selection
			'fields'           => array(),
		);

		// Additional property fields
		$properties = array(
			'user_id'   => null,
			'user_role' => null,
			'ip'        => null,
			'object_id' => null,
			'site_id'   => null,
			'blog_id'   => null,
			'connector' => null,
			'context'   => null,
			'action'    => null,
		);

		/**
		 * Filter allows additional query properties to be added
		 *
		 * @return array  Array of query properties
		 */
		$properties = apply_filters( 'wp_stream_query_properties', $properties );

		// Add property fields to defaults, including their __in/__not_in variations
		foreach ( $properties as $property => $default ) {
			if ( ! isset( $defaults[ $property ] ) ) {
				$defaults[ $property ] = $default;
			}

			$defaults[ "{$property}__in" ]     = array();
			$defaults[ "{$property}__not_in" ] = array();
		}

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter allows additional arguments to query $args
		 *
		 * @return array  Array of query arguments
		 */
		$args = apply_filters( 'wp_stream_query_args', $args );

		$result = (array) $this->driver->get_records( $args );
		$this->found_records_count = isset( $result['count'] ) ? $result['count'] : 0;

		return empty( $result['items'] ) ? array() : $result['items'];
	}

	/**
	 * Helper function, backwards compatibility
	 *
	 * @param array Query args
	 *
	 * @return array Stream Records
	 */
	public function query( $args ) {
		$this->get_records( $args );
	}

	/**
	 * Return the number of records found in last request
	 *
	 * return int
	 */
	public function get_found_records_count() {
		return $this->found_records_count;
	}
}
