<?php
header( 'Content-type: application/json; charset=' . get_option( 'blog_charset' ), true );
if ( version_compare( PHP_VERSION, '5.4', '<' ) ) {
	echo wp_stream_json_encode( $records ); // xss ok
} else {
	echo wp_stream_json_encode( $records, JSON_PRETTY_PRINT ); // xss ok
}
