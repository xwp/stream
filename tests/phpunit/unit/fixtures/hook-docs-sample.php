<?php
/**
 * Sample hooks for Hook_Docs_Generator unit tests.
 *
 * @package WP_Stream
 */

// Static filter with full docblock.
/**
 * Filters a static example value.
 *
 * @param string $value Example value.
 */
apply_filters( 'wp_stream_static_example', 'default' );

// Dynamic hook name.
$connector = 'posts';
/**
 * Filters action links for a connector.
 *
 * @param array  $links    Action links.
 * @param object $record   Record object.
 */
apply_filters( 'wp_stream_action_links_' . $connector, array(), $record );

// Missing docblock.
apply_filters( 'wp_stream_undocumented', true );

// Deprecated filter call.
apply_filters_deprecated(
	'wp_stream_register_column_defaults',
	array( array() ),
	'4.0.1',
	'wp_stream_list_table_columns'
);

// External core hook.
/** This filter is documented in WordPress core (`sidebars_widgets`). */
apply_filters( 'sidebars_widgets', array() );

// Multiline call.
do_action(
	'wp_stream_multiline_action',
	$arg_one,
	$arg_two
);
