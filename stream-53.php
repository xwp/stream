<?php

require __DIR__ . '/classes/class-plugin.php';
$GLOBALS['wp_stream'] = new WP_Stream\Plugin();

/**
 * Helper for external plugins which wish to use Stream
 *
 * @return WP_Stream\Plugin
 */
function wp_stream_get_instance() {
	return $GLOBALS['wp_stream'];
}
