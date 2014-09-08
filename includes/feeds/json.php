<?php
header( 'Content-type: application/json; charset=' . get_option( 'blog_charset' ), true );
if ( version_compare( PHP_VERSION, '5.4', '>=' ) ) {
	echo json_encode( $records, JSON_PRETTY_PRINT );
} else {
	echo json_encode( $records );
}
