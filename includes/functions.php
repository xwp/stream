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

function wp_stream_get_meta( $record, $meta_key = '', $single = false ) {
	if ( is_string( $record ) ) {
		$record_meta = WP_Stream::$db->get_record_meta( $record, $meta_key, true );
	} elseif ( isset( $record->stream_meta->$meta_key ) ) {
		$record_meta = $record->stream_meta->$meta_key;
	} else {
		return '';
	}

	if ( $single ) {
		return $record_meta;
	} else {
		return array( $record_meta );
	}
}

/**
 * Converts a time into an ISO 8601 extended formatted string.
 *
 * @param int Seconds since unix epoc
 * @param int Hour offset
 *
 * @return string an ISO 8601 extended formatted time
 */
function wp_stream_get_iso_8601_extended_date( $time = false, $offset = 0 )	{
	if ( $time ) {
		$microtime = (float) $time . '.0000';
	} else {
		$microtime = microtime( true );
	}

	$micro_seconds = sprintf( '%06d', ( $microtime - floor( $microtime ) ) * 1000000 );
	$offset_string = sprintf( 'Etc/GMT%s%s', $offset < 0 ? '+' : '-', abs( $offset ) );

	$timezone = new DateTimeZone( $offset_string );
	$date     = new DateTime( date( 'Y-m-d H:i:s.' . $micro_seconds, $microtime ), $timezone );

	return sprintf(
		'%s%03d%s',
		$date->format( 'Y-m-d\TH:i:s.' ),
		floor( $date->format( 'u' ) / 1000 ),
		$date->format( 'O' )
	);
}

/**
 * Returns array of existing values for requested field.
 * Used to fill search filters with only used items, instead of all items.
 *
 * @see    assemble_records
 * @since  1.0.4
 * @param  string  Requested field (i.e., 'context')
 * @return array   Array of items to be output to select dropdowns
 */
function wp_stream_existing_records( $field ) {
	$values = WP_Stream::$db->get_distinct_field_values( $field );

	if ( is_array( $values ) && ! empty( $values ) ) {
		return array_combine( $values, $values );
	} else {
		$field = sprintf( 'stream_%s', $field );
		return isset( WP_Stream_Connectors::$term_labels[ $field ] ) ? WP_Stream_Connectors::$term_labels[ $field ] : array();
	}
}
