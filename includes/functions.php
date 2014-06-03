<?php

function wp_stream_filter_input( $type, $variable_name, $filter = null, $options = array() ) {
	return call_user_func_array( array( 'WP_Stream_Filter_Input', 'super' ), func_get_args() );
}

function wp_stream_filter_var( $var, $filter = null, $options = array() ) {
	return call_user_func_array( array( 'WP_Stream_Filter_Input', 'filter' ), func_get_args() );
}

function wp_stream_query( $args = array() ) {
	return WP_Stream_Query::get_instance()->query( $args );
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
	global $wpdb;

	switch ( $table ) {
		case 'stream' :
			$rows = $wpdb->get_results( "SELECT {$column} FROM {$wpdb->stream} GROUP BY {$column}", 'ARRAY_A' );
			break;
		case 'meta' :
			$rows = $wpdb->get_results( "SELECT {$column} FROM {$wpdb->streammeta} GROUP BY {$column}", 'ARRAY_A' );
			break;
		default :
			$rows = $wpdb->get_results( "SELECT {$column} FROM {$wpdb->streamcontext} GROUP BY {$column}", 'ARRAY_A' );
	}

	if ( is_array( $rows ) && ! empty( $rows ) ) {
		foreach ( $rows as $row ) {
			foreach ( $row as $cell => $value ) {
				$output_array[ $value ] = $value;
			}
		}
		return (array) $output_array;
	} else {
		$column = sprintf( 'stream_%s', $column );
		return isset( WP_Stream_Connectors::$term_labels[ $column ] ) ? WP_Stream_Connectors::$term_labels[ $column ] : array();
	}
}