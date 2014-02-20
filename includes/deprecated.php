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
		$alternate = sprintf( __( 'Use %s instead.', 'stream' ), esc_html( $alternate ) );
	} else {
		$alternate = __( 'There is no alternative available.', 'stream' );
	}

	trigger_error(
		sprintf(
			__( '%1$s is <strong>deprecated</strong> since Stream version %2$s! %3$s', 'stream' ),
			esc_html( $function ),
			esc_html( $version ),
			esc_html( $alternate )
		)
	);

	trigger_error( print_r( $backtrace ) );
}
