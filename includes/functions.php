<?php
/**
 * Defines common functionality used throughout the plugin.
 *
 * @package WP_Stream
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
 * @return mixed|false|null Value of the requested variable on success, FALSE if the filter fails, or NULL if the $variable_name is not set.
 */
function wp_stream_filter_input( $type, $variable_name, $filter = null, $options = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- reason: parameters are forwarded via func_get_args() to Filter_Input::super().
	return call_user_func_array( array( '\WP_Stream\Filter_Input', 'super' ), func_get_args() );
}

/**
 * Filters a variable with a specified filter.
 *
 * This is a polyfill function intended to be used in place of PHP's
 * filter_var() function, which can occasionally be unreliable.
 *
 * @param string $value   Value to filter.
 * @param int    $filter  The ID of the filter to apply.
 * @param mixed  $options Associative array of options or bitwise disjunction of flags. If filter accepts options, flags can be provided in "flags" field of array. For the "callback" filter, callable type should be passed. The callback must accept one argument, the value to be filtered, and return the value after filtering/sanitizing it.
 *
 * @return Returns the filtered data, or FALSE if the filter fails.
 */
function wp_stream_filter_var( $value, $filter = null, $options = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- reason: parameters are forwarded via func_get_args() to Filter_Input::filter().
	return call_user_func_array( array( '\WP_Stream\Filter_Input', 'filter' ), func_get_args() );
}

/**
 * Converts a time into an ISO 8601 extended formatted string.
 *
 * @param int|bool $time   Seconds since unix epoch.
 * @param int      $offset Timezone offset.
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
	$offset_string = sprintf( 'Etc/GMT%s%d', $offset < 0 ? '+' : '-', abs( $offset ) );

	$timezone = new DateTimeZone( $offset_string );
	$date     = new DateTime( gmdate( 'Y-m-d H:i:s.' . $micro_seconds, $microtime ), $timezone );

	return $date->format( 'Y-m-d\TH:i:sO' );
}

/**
 * Return an array of sites for a network.
 *
 * On single-site, `get_sites()` is not loaded by WordPress, so this returns
 * an empty array instead of calling an undefined function.
 *
 * @param string|array $args  Argument to filter results by.
 *
 * @return array
 */
function wp_stream_get_sites( $args = array() ) {
	if ( ! is_multisite() ) {
		return array();
	}

	if ( empty( $args['limit'] ) ) {
		$args['limit'] = 0;
	}

	$args['number'] = $args['limit'];

	return get_sites( $args );
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
 * Check if the default front-end WP Cron is enabled. It doesn't
 * mean that the Cron is disabled in general.
 *
 * @return bool
 */
function wp_stream_is_cron_enabled() {
	return ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? false : true;
}
