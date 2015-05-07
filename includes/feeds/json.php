<?php
header( 'Content-type: application/json; charset=' . get_option( 'blog_charset' ), true );
if ( version_compare( PHP_VERSION, '5.4', '>=' ) ) {
	echo wp_json_encode( $records, JSON_PRETTY_PRINT );
} else {
	echo wp_json_encode( $records );
}
