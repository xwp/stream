<?php
/**
 * CLI entry point for hook reference generation.
 *
 * @package WP_Stream
 */

$plugin_dir = realpath( __DIR__ . '/../..' );
if ( false === $plugin_dir ) {
	fwrite( STDERR, "Could not resolve plugin root.\n" );
	exit( 1 );
}

require_once __DIR__ . '/class-hook-docs-generator.php';

$output_path = $plugin_dir . '/docs/hooks.md';
$check_only  = in_array( '--check', $argv, true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- CLI argv.

$generator = new Stream_Hook_Docs_Generator( $plugin_dir );

if ( $check_only ) {
	if ( $generator->is_stale( $output_path ) ) {
		fwrite( STDERR, "docs/hooks.md is out of date. Run: npm run docs:hooks\n" );
		exit( 1 );
	}
	exit( 0 );
}

$docs_dir = dirname( $output_path );
if ( ! is_dir( $docs_dir ) && ! mkdir( $docs_dir, 0755, true ) && ! is_dir( $docs_dir ) ) {
	fwrite( STDERR, "Could not create docs directory.\n" );
	exit( 1 );
}

if ( ! $generator->write_markdown( $output_path ) ) {
	fwrite( STDERR, "Failed to write {$output_path}\n" );
	exit( 1 );
}

fwrite( STDOUT, "Wrote {$output_path}\n" );
