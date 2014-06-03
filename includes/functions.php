<?php

function wp_stream_filter_input( $type, $variable_name, $filter = null, $options = array() ) {
	return call_user_func_array( array( 'WP_Stream_Filter_Input', 'super' ), func_get_args() );
}

function wp_stream_filter_var( $var, $filter = null, $options = array() ) {
	return call_user_func_array( array( 'WP_Stream_Filter_Input', 'filter' ), func_get_args() );
}

function wp_stream_query( $args = array() ) {
	return WP_Stream_Query::instance()->query( $args );
}

function wp_stream_get_meta( $record_id, $meta_key = '', $single = false ) {
	return WP_Stream::$db->get_meta( $record_id, $meta_key, $single );
}

function wp_stream_add_meta( $record_id, $meta_key, $meta_value ) {
	return WP_Stream::$db->add_meta( $record_id, $meta_key, $meta_value );
}

function wp_stream_update_meta( $record_id, $meta_key, $meta_value, $prev_value = '' ) {
	return WP_Stream::$db->update_meta( $record_id, $meta_key, $meta_value, $prev_value );
}

function wp_stream_delete_meta( $record_id, $meta_key, $meta_value = null, $delete_all = false ) {
	return WP_Stream::$db->delete_meta( $record_id, $meta_key, $meta_value, $delete_all );
}

function wp_stream_delete_records( $args = array() ) {
	if ( $args ) {
		$args['fields']           = 'ID';
		$args['records_per_page'] = -1;
		$records                  = wp_stream_query( $args );
		$params                   = wp_list_pluck( $records, 'ID' );
		if ( empty( $ids ) ) {
			return 0;
		}
	} else {
		$params = true; // Delete them ALL!
	}

	return WP_Stream::$db->delete( $params );
}

/**
 * Returns array of existing values for requested column.
 * Used to fill search filters with only used items, instead of all items.
 *
 * @see    assemble_records
 * @since  1.0.4
 * @param  string  Requested Column (i.e., 'context')
 * @return array   Array of items to be output to select dropdowns
 */
function wp_stream_existing_records( $column ) {
	// Short circuit for now, till Facets is available
	return array();
	$values = WP_Stream::$db->get_col( $column );
	if ( is_array( $values ) && ! empty( $values ) ) {
		return array_combine( $values, $values );
	} else {
		$column = sprintf( 'stream_%s', $column );
		return isset( WP_Stream_Connectors::$term_labels[ $column ] ) ? WP_Stream_Connectors::$term_labels[ $column ] : array();
	}
}
