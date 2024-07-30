<?php
/**
 * Renders a JSON of records.
 *
 * @package WP_Stream
 *
 * @var $records
 */

header( 'Content-type: application/json; charset=' . get_option( 'blog_charset' ), true );
echo wp_json_encode( $records, JSON_PRETTY_PRINT );
