<?php
/**
 * Interface for a Database Driver.
 *
 * @todo Review. Heavy refactor maybe needed.
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Interface - DB_Driver
 */
interface DB_Driver {
	/**
	 * Insert a record
	 *
	 * @param array $data Data to be insert into the database.
	 *
	 * @return int
	 */
	public function insert_record( $data );

	/**
	 * Retrieve records
	 *
	 * @param array $args Argument to filter the result by.
	 *
	 * @return array
	 */
	public function get_records( $args );

	/**
	 * Returns array of existing values for requested column.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * @param string $column Column to pull data from.
	 *
	 * @return array
	 */
	public function get_column_values( $column );

	/**
	 * Public getter to return the names of the tables this driver manages.
	 *
	 * @return array
	 */
	public function get_table_names();

	/**
	 * Init storage.
	 *
	 * @param \WP_Stream\Plugin $plugin Instance of the plugin.
	 */
	public function setup_storage( $plugin );

	/**
	 * Purge storage.
	 *
	 * @param \WP_Stream\Plugin $plugin Instance of the plugin.
	 */
	public function purge_storage( $plugin );
}
