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
 * Return an array of sites for a network in a way that is also backwards compatible
 *
 * @param string|array $args
 *
 * @return array
 */
function wp_stream_get_sites( $args = array() ) {
	if ( function_exists( 'get_sites' ) ) {
		$sites = get_sites( $args );
	} else {
		$sites = array();
		foreach ( wp_get_sites( $args ) as $site ) { // @codingStandardsIgnoreLine Specifically for old version of WP first, in order to provide backward compatibility
			$sites[] = WP_Site::get_instance( $site['blog_id'] );
		}
	}

	return $sites;
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

/**
 * Get the asset min suffix if defined.
 *
 * @return string
 */
function wp_stream_min_suffix() {
	$min = '';
	if ( ! defined( 'SCRIPT_DEBUG' ) || false === SCRIPT_DEBUG ) {
		$min = 'min.';
	}

	return $min;
}
