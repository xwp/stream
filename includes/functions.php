<?php

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
		if ( version_compare( PHP_VERSION, '5.5', '<' ) ) {
			$json = json_encode( $data, $options );
		} else {
			$json = json_encode( $data, $options, $depth );
		}
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