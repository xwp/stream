<?php

function wp_stream_query( $args = array() ) {
	return WP_Stream::$db->query( $args );
}

function wp_stream_get_meta( $record_id, $key = '', $single = false ) {
	return maybe_unserialize( get_metadata( 'record', $record_id, $key, $single ) );
}

function wp_stream_update_meta( $record_id, $meta_key, $meta_value, $prev_value = '' ) {
	return update_metadata( 'record', $record_id, $meta_key, $meta_value, $prev_value );
}

/**
 * Returns array of existing values for requested column.
 * Used to fill search filters with only used items, instead of all items.
 *
 * GROUP BY allows query to find just the first occurance of each value in the column,
 * increasing the efficiency of the query.
 *
 * @todo   increase security against injections
 *
 * @see    assemble_records
 * @since  1.0.4
 * @param  string  Requested Column (i.e., 'context')
 * @param  string  Requested Table
 * @return array   Array of items to be output to select dropdowns
 */
function wp_stream_existing_records( $column, $table = '' ) {
	return WP_Stream::$db->get_existing_records( $column, $table );
}
