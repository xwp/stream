<?php
/**
 * PHPUnit bootstrap for the no-WordPress unit suite.
 *
 * @package WP_Stream
 */

$wp_stream_unit_autoload_file = dirname( __DIR__, 3 ) . '/vendor/autoload.php';

if ( ! is_readable( $wp_stream_unit_autoload_file ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI bootstrap before WP loads.
	fwrite( STDERR, "Composer autoload not found. Run composer install.\n" );
	exit( 1 );
}

require_once $wp_stream_unit_autoload_file;

/**
 * Autoload WP_Stream classes from classes/class-*.php (same mapping as Plugin::autoload).
 *
 * @param string $class_name Fully-qualified class name.
 */
function wp_stream_unit_autoload( $class_name ) {
	if ( ! preg_match( '/^WP_Stream\\\\(?P<autoload>[^\\\\]+)$/', $class_name, $matches ) ) {
		return;
	}

	$path = dirname( __DIR__, 3 ) . '/classes/class-' . strtolower( str_replace( '_', '-', $matches['autoload'] ) ) . '.php';

	if ( is_readable( $path ) ) {
		require_once $path;
	}
}

spl_autoload_register( 'wp_stream_unit_autoload' );
