<?php

// This file is meant to be access directly
if ( defined( 'ABSPATH' ) ) {
	wp_die( 'Doing it wrong.', 'stream' );
}

define( 'WP_USE_THEMES', false );
require( '../../../../wp-load.php' );

$api     = new WP_Stream_API();
$migrate = new WP_Stream_Migrate();

$api_key = wp_stream_filter_input( INPUT_GET, 'api_key' );

if ( ! $api_key || $api_key !== $api->api_key ) {
	wp_die( 'Unauthorized.', 'stream' );
}

$records = json_encode( $migrate->get_records() ); //xss ok

header( 'Content-type: application/json' );
header( 'Content-Length: ' . strlen( $records ) );

echo $records;

die();