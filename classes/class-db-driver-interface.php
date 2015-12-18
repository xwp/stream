<?php
namespace WP_Stream;

interface DB_Driver_Interface {
	/**
	 * Insert a record
	 *
	 * @param array $data
	 *
	 * @return int
	 */
	public function insert_record( $data );

	/**
	 * Retrieve records
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function get_records( $args );

	/**
	 * Returns array of existing values for requested column.
	 * Used to fill search filters with only used items, instead of all items.
	 *
	 * @param string $column
	 *
	 * @return array
	 */
	public function get_column_values( $column );

	/**
	 * Init storage
	 */
	public function setup_storage();

	/**
	 * Purge storage
	 */
	public function purge_storage();
}
