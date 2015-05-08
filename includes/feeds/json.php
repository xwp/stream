<?php
header( 'Content-type: application/json; charset=' . get_option( 'blog_charset' ), true );
echo wp_stream_json_encode( $records, JSON_PRETTY_PRINT );
