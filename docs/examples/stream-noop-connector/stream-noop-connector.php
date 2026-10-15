<?php
/**
 * Plugin Name: Stream Noop Connector (example)
 * Description: Sample Stream connector that logs one event when an admin button is clicked. Copy this folder into wp-content/plugins to try it. Stream does not load this plugin itself.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Author: XWP
 * Author URI: https://xwp.co
 * License: GPL-2.0-or-later
 * Text Domain: stream-noop-connector
 *
 * @package Stream_Noop_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Append the noop connector instance to Stream's registry.
 *
 * @param array<string, \WP_Stream\Connector> $instances Connector instances keyed by slug.
 * @return array<string, \WP_Stream\Connector>
 */
function stream_noop_connector_register( $instances ) {
	if ( ! class_exists( \WP_Stream\Connector::class ) ) {
		return $instances;
	}

	require_once __DIR__ . '/class-connector-noop.php';

	$connector                     = new \WP_Stream\Connector_Noop();
	$instances[ $connector->name ] = $connector;

	return $instances;
}

/**
 * Hook the connector into Stream before Connectors loads on init priority 9.
 *
 * @return void
 */
function stream_noop_connector_plugins_loaded() {
	add_filter( 'wp_stream_connectors', 'stream_noop_connector_register' );
}

/**
 * Register the Tools submenu that hosts the demo button.
 *
 * @return void
 */
function stream_noop_connector_admin_menu() {
	add_management_page(
		__( 'Stream Noop Demo', 'stream-noop-connector' ),
		__( 'Stream Noop Demo', 'stream-noop-connector' ),
		'manage_options',
		'stream-noop-connector',
		'stream_noop_connector_render_page'
	);
}

/**
 * Render the demo page with a button that fires one Stream event.
 *
 * @return void
 */
function stream_noop_connector_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Display flag after the nonce-verified admin-post redirect. No state is changed here.
	$done = isset( $_GET['done'] ) ? sanitize_text_field( wp_unslash( $_GET['done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Stream Noop Demo', 'stream-noop-connector' ) . '</h1>';

	if ( '1' === $done ) {
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html__( 'Logged one noop event. Open Stream to see it.', 'stream-noop-connector' );
		echo '</p></div>';
	}

	echo '<p>' . esc_html__( 'Click the button to log a single fake Stream record from this sample connector.', 'stream-noop-connector' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="stream_noop_demo" />';
	wp_nonce_field( 'stream_noop_demo', 'stream_noop_demo_nonce' );
	submit_button( __( 'Log a noop event', 'stream-noop-connector' ) );
	echo '</form>';
	echo '</div>';
}

/**
 * Handle the demo button: verify caps and nonce, then fire the connector action.
 *
 * @return void
 */
function stream_noop_connector_handle_click() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'stream-noop-connector' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'stream_noop_demo', 'stream_noop_demo_nonce' );

	/**
	 * Fires when the sample noop demo button is clicked.
	 *
	 * The noop connector listens on this action and writes one Stream record.
	 */
	do_action( 'stream_noop_demo_clicked' );

	wp_safe_redirect(
		add_query_arg(
			'done',
			'1',
			admin_url( 'tools.php?page=stream-noop-connector' )
		)
	);
	exit;
}

add_action( 'plugins_loaded', 'stream_noop_connector_plugins_loaded' );
add_action( 'admin_menu', 'stream_noop_connector_admin_menu' );
add_action( 'admin_post_stream_noop_demo', 'stream_noop_connector_handle_click' );
