<?php

/**
 * stream_query()
 *
 * @deprecated 1.3.1
 * @deprecated Use wp_stream_query()
 * @see wp_stream_query()
 */
function stream_query( $args = array() ) {
	_deprecated_function( __FUNCTION__, '1.3.1', 'wp_stream_query()' );

	return wp_stream_query( $args );
}

/**
 * get_stream_meta()
 *
 * @deprecated 1.3.1
 * @deprecated Use wp_stream_get_meta
 * @see wp_stream_get_meta()
 */
function get_stream_meta( $record_id, $key = '', $single = false ) {
	_deprecated_function( __FUNCTION__, '1.3.1', 'wp_stream_get_meta()' );

	return wp_stream_get_meta( $record_id, $key, $single );
}

/**
 * update_stream_meta()
 *
 * @deprecated 1.3.1
 * @deprecated Use wp_stream_update_meta
 * @see wp_stream_update_meta()
 */
function update_stream_meta( $record_id, $meta_key, $meta_value, $prev_value = '' ) {
	_deprecated_function( __FUNCTION__, '1.3.1', 'wp_stream_update_meta()' );

	return wp_stream_update_meta( $record_id, $meta_key, $meta_value, $prev_value );
}

/**
 * existing_records()
 *
 * @deprecated 1.3.1
 * @deprecated Use wp_stream_existing_records
 * @see wp_stream_existing_records()
 */
function existing_records( $column, $table = '' ) {
	_deprecated_function( __FUNCTION__, '1.3.1', 'wp_stream_existing_records()' );

	return wp_stream_existing_records( $column, $table );
}
