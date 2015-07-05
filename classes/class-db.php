<?php
namespace WP_Stream;

class DB {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * @var Query
	 */
	public $query;

	/**
	 * Meta information returned in the last query
	 *
	 * @var mixed
	 */
	public $query_meta = false;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->query  = new Query( $this );
	}

	/**
	 * Store records
	 *
	 * @param array $records
	 *
	 * @return mixed True if updated, otherwise false|WP_Error
	 */
	public function store( $records ) {
		// Take only what's ours!
		$valid_keys = get_class_vars( 'Record' );

		// Fill in defaults
		$defaults = array(
			'type'        => 'stream',
			'site_id'     => 1,
			'blog_id'     => 0,
			'object_id'   => 0,
			'author'      => 0,
			'author_role' => '',
			'visibility'  => 'publish',
			'ip'          => '',
		);

		foreach ( $records as $key => $record ) {
			$records[ $key ] = array_intersect_key( $record, $valid_keys );
			$records[ $key ] = array_filter( $record );
			$records[ $key ] = wp_parse_args( $record, $defaults );
		}

		/**
		 * Allows modification of record information just before logging occurs.
		 *
		 * @param  array $records An array of record data.
		 */
		$records = apply_filters( 'wp_stream_record_array', $records );

		// Allow extensions to handle the saving process
		if ( empty( $records ) ) {
			return false;
		}

		// TODO: Check/Validate *required* fields

		$result = $this->insert( $records );

		if ( $result && ! is_wp_error( $result ) ) {
			/**
			 * Fires when A Post is inserted
			 *
			 * @param array $records
			 */
			do_action( 'wp_stream_records_inserted', $records );

			return true;
		}

		return is_wp_error( $result ) ? $result : false;
	}

	/**
	 * Insert a new record
	 *
	 * @internal Used by store()
	 *
	 * @param array $records
	 *
	 * @return object The inserted records
	 */
	private function insert( array $records ) {
		return $this->plugin->api->new_records( $records );
	}

	/**
	 * Query records
	 *
	 * @param array $query  Query body.
	 * @param array $fields Returns specified fields only.
	 *
	 * @return array List of records that match query
	 */
	public function query( $query, $fields ) {
		$response = $this->plugin->api->search( $query, $fields );

		if ( empty( $response ) || ! isset( $response->meta ) || ! isset( $response->records ) ) {
			return false;
		}

		$this->query_meta = $response->meta;

		$results = (array) $response->records;

		/**
		 * Allows developers to change the final result set of records
		 *
		 * @param array $results
		 * @param array $query
		 * @param array $fields
		 *
		 * @return array Filtered array of record results
		 */
		return apply_filters( 'wp_stream_query_results', $results, $query, $fields );
	}

	/**
	 * Get total count of the last query using query() method
	 *
	 * @return integer Total item count
	 */
	public function get_found_rows() {
		if ( ! isset( $this->query_meta->total ) ) {
			return 0;
		}
		return $this->query_meta->total;
	}

	/**
	 * Get meta data for last query using query() method
	 *
	 * @return array Meta data for query
	 */
	public function get_query_meta() {
		return $this->query_meta;
	}

	/**
	 * Returns array of existing values for requested field.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * @param string $field Requested field (i.e., 'context')
	 *
	 * @return array Array of distinct values
	 */
	public function get_distinct_field_values( $field ) {
		$query['aggregations']['fields']['terms']['field'] = $field;

		$values   = array();
		$response = $this->plugin->api->search( $query, array( $field ) );

		if ( isset( $response->meta->aggregations->fields->buckets ) ) {
			foreach ( $response->meta->aggregations->fields->buckets as $field ) {
				$values[] = $field->key;
			}
		}

		return $values;
	}
}
