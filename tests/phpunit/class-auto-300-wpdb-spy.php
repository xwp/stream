<?php
/**
 * Records queries from wp_stream_update_auto_300() without running them.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Stand-in for $wpdb that captures SQL and never touches the database.
 */
class Auto_300_Wpdb_Spy {
	/**
	 * Table prefix the migration interpolates into RENAME.
	 *
	 * @var string
	 */
	public $base_prefix;

	/**
	 * Queries the migration attempted.
	 *
	 * @var array
	 */
	public $queries = array();

	/**
	 * Store the table prefix the migration will read.
	 *
	 * @param string $base_prefix Table prefix.
	 */
	public function __construct( $base_prefix ) {
		$this->base_prefix = $base_prefix;
	}

	/**
	 * Record a query instead of running it.
	 *
	 * @param string $query SQL.
	 * @return false
	 */
	public function query( $query ) {
		$this->queries[] = $query;

		return false;
	}
}
