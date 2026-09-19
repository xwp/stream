<?php
/**
 * Plugin Name: Stream Error Log Alert (example)
 * Description: Sample Stream alert type that writes triggered records to the PHP error log. Copy this folder into wp-content/plugins to try it. Stream does not load this plugin itself.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Author: XWP
 * Author URI: https://xwp.co
 * License: GPL-2.0-or-later
 * Text Domain: stream-error-log-alert
 *
 * @package Stream_Error_Log_Alert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Append the error-log alert type instance to Stream's registry.
 *
 * @param array<string, \WP_Stream\Alert_Type> $types Alert type instances keyed by slug.
 * @return array<string, \WP_Stream\Alert_Type>
 */
function stream_error_log_alert_register( $types ) {
	if ( ! class_exists( \WP_Stream\Alert_Type::class ) ) {
		return $types;
	}

	$plugin = wp_stream_get_instance();
	if ( ! $plugin instanceof \WP_Stream\Plugin ) {
		return $types;
	}

	require_once __DIR__ . '/class-alert-type-error-log.php';

	$type                 = new \WP_Stream\Alert_Type_Error_Log( $plugin );
	$types[ $type->slug ] = $type;

	return $types;
}

add_filter( 'wp_stream_alert_types', 'stream_error_log_alert_register' );
