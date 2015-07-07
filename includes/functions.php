<?php

/**
 * Gets a specific external variable by name and optionally filters it.
 *
 * This is a polyfill function intended to be used in place of PHP's
 * filter_input() function, which can occasionally be unreliable.
 *
 * @param int    $type           One of INPUT_GET, INPUT_POST, INPUT_COOKIE, INPUT_SERVER, or INPUT_ENV.
 * @param string $variable_name  Name of a variable to get.
 * @param int    $filter         The ID of the filter to apply.
 * @param mixed  $options        Associative array of options or bitwise disjunction of flags. If filter accepts options, flags can be provided in "flags" field of array.
 *
 * @return Value of the requested variable on success, FALSE if the filter fails, or NULL if the $variable_name is not set.
 */
function wp_stream_filter_input( $type, $variable_name, $filter = null, $options = array() ) {
	return call_user_func_array( array( '\WP_Stream\Filter_Input', 'super' ), func_get_args() );
}

/**
 * Filters a variable with a specified filter.
 *
 * This is a polyfill function intended to be used in place of PHP's
 * filter_var() function, which can occasionally be unreliable.
 *
 * @param string $var      Value to filter.
 * @param int    $filter   The ID of the filter to apply.
 * @param mixed  $options  Associative array of options or bitwise disjunction of flags. If filter accepts options, flags can be provided in "flags" field of array. For the "callback" filter, callable type should be passed. The callback must accept one argument, the value to be filtered, and return the value after filtering/sanitizing it.
 *
 * @return Returns the filtered data, or FALSE if the filter fails.
 */
function wp_stream_filter_var( $var, $filter = null, $options = array() ) {
	return call_user_func_array( array( '\WP_Stream\Filter_Input', 'filter' ), func_get_args() );
}

/**
 * Converts a time into an ISO 8601 extended formatted string.
 *
 * @param int|bool $time Seconds since unix epoc
 * @param int $offset Hour offset
 *
 * @return string an ISO 8601 extended formatted time
 */
function wp_stream_get_iso_8601_extended_date( $time = false, $offset = 0 ) {
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
 * Encode to JSON in a way that is also backwards compatible
 *
 * @param mixed $data
 * @param int $options (optional)
 * @param int $depth (optional)
 *
 * @return string
 */
function wp_stream_json_encode( $data, $options = 0, $depth = 512 ) {
	if ( function_exists( 'wp_json_encode' ) ) {
		$json = wp_json_encode( $data, $options, $depth );
	} else {
		// @codingStandardsIgnoreStart
		if ( version_compare( PHP_VERSION, '5.5', '<' ) ) {
			$json = json_encode( $data, $options );
		} else {
			$json = json_encode( $data, $options, $depth );
		}
		// @codingStandardsIgnoreEnd
	}

	return $json;
}

/**
 * Check if Stream is running on WordPress.com VIP
 *
 * @return bool
 */
function wp_stream_is_vip() {
	return function_exists( 'wpcom_vip_load_plugin' );
}

/**
 * True if native WP Cron is enabled, otherwise false
 *
 * @return bool
 */
function wp_stream_is_cron_enabled() {
	return ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? false : true;
}
