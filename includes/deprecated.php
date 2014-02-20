<?php

/**
 * Marks a function as deprecated and informs when it has been used.
 *
 * This function is to be used in every Stream function that is deprecated.
 *
 * @uses wp_stream_deprecated_function_run
 * @uses wp_stream_deprecated_function_trigger_error
 *
 * @param string  $function   The function that was called
 * @param string  $version    The version of Stream that deprecated the function
 * @param string  $alternate  (optional) The function that should be called instead
 * @param array   $backtrace  (optional) Contains stack backtrace of deprecated function
 */
function wp_stream_deprecated_function( $function, $version, $alternate = null, $backtrace = null ) {
	/**
	 * Passes the function name, the Stream version that deprecated the function,
	 * and what function to use instead.
	 *
	 * @param string $function   The function that was called
	 * @param string $version    The version of Stream that deprecated the function
	 * @param string $alternate  The function that should be called instead
	 * @return bool
	 */
	do_action( 'wp_stream_deprecated_function_run', $function, $version, $alternate );

	/**
	 * Expects boolean value of true to trigger any errors, or false to suppress them.
	 *
	 * @param bool
	 * @return bool
	 */
	$show_errors = apply_filters( 'wp_stream_deprecated_function_trigger_error', current_user_can( 'manage_options' ) );

	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! $show_errors ) {
		return;
	}

	if ( ! empty( $alternate ) ) {
		$alternate = sprintf(
			__( 'Use %s instead.', 'stream' ),
			sprintf(
				'<code>%s</code>',
				$alternate
			)
		);
	} else {
		$alternate = __( 'There is no alternative available.', 'stream' );
	}

	trigger_error(
		sprintf(
			__( '%1$s is <strong>deprecated</strong> since Stream version %2$s! %3$s', 'stream' ),
			sprintf(
				'<code>%s</code>',
				esc_html( $function )
			),
			esc_html( $version ),
			$alternate // xss ok
		)
	);
}

/**
 * stream_query()
 *
 * @deprecated 1.2.4
 */
function stream_query( $args = array() ) {
	wp_stream_deprecated_function( __FUNCTION__, '1.2.4', 'wp_stream_query', debug_backtrace() );

	return wp_stream_query( $args );
}

/**
 * get_stream_meta()
 *
 * @deprecated 1.2.4
 */
function get_stream_meta( $record_id, $key = '', $single = false ) {
	wp_stream_deprecated_function( __FUNCTION__, '1.2.4', 'wp_stream_get_meta', debug_backtrace() );

	return wp_stream_get_meta( $record_id, $key, $single );
}

/**
 * update_stream_meta()
 *
 * @deprecated 1.2.4
 */
function update_stream_meta( $record_id, $meta_key, $meta_value, $prev_value = '' ) {
	wp_stream_deprecated_function( __FUNCTION__, '1.2.4', 'wp_stream_update_meta', debug_backtrace() );

	return wp_stream_update_meta( $record_id, $meta_key, $meta_value, $prev_value );
}

/**
 * existing_records()
 *
 * @deprecated 1.2.4
 */
function existing_records( $column, $table = '' ) {
	wp_stream_deprecated_function( __FUNCTION__, '1.2.4', 'wp_stream_existing_records', debug_backtrace() );

	return wp_stream_existing_records( $column, $table );
}
