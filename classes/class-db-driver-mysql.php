<?php
namespace WP_Stream;

class DB_Driver_Mysql implements DB_Driver_Interface {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Hold Query class
	 * @var Query
	 */
	protected $query;

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
		$prefix = apply_filters( 'wp_stream_db_tables_prefix', $wpdb->base_prefix );

		$this->table         = $prefix . 'stream';
		$this->table_meta    = $prefix . 'stream_meta';

		$wpdb->stream        = $this->table;
		$wpdb->streammeta    = $this->table_meta;

		// Hack for get_metadata
		$wpdb->recordmeta    = $this->table_meta;
	}

	/**
	 * Insert a record
	 *
	 * @param array $data
	 *
	 * @return int
	 */
	public function insert_record( $data ) {
		global $wpdb;

		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return false;
		}

		$meta = $data['meta'];
		unset( $data['meta'] );

		$result = $wpdb->insert( $this->table, $data );
		if ( ! $result ) {
			return false;
		}

		$record_id = $wpdb->insert_id;

		// Insert record meta
		foreach ( (array) $meta as $key => $vals ) {
			foreach ( (array) $vals as $val ) {
				$this->insert_meta( $record_id, $key, $val );
			}
		}

		return $record_id;
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
	 * Retrieve records
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function get_records( $args ) {
		return $this->query->query( $args );
	}

	/**
	 * Returns array of existing values for requested column.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * GROUP BY allows query to find just the first occurance of each value in the column,
	 * increasing the efficiency of the query.
	 *
	 * @param string $column
	 *
	 * @return array
	 */
	public function get_column_values( $column ) {
		global $wpdb;
		return (array) $wpdb->get_results(
			"SELECT DISTINCT $column FROM $wpdb->stream", // @codingStandardsIgnoreLine can't prepare column name
			'ARRAY_A'
		);
	}

	/**
	 * Purge storage
	 */
	public function purge_storage() {
		// TODO: Implement method and rework class-uninstall to use this method
	}

	/**
	 * Init storage
	 */
	public function setup_storage() {
		// TODO: Implement method and rework class-install to use this method
	}
}
