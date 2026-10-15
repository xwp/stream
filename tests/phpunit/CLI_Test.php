<?php
namespace WP_Stream;

use ReflectionMethod;
use ReflectionProperty;
use WP_CLI;
use WP_CLI\ExitException;

class CLI_Test extends WP_StreamTestCase {
	/**
	 * Invokes the private CLI::connection() method, capturing any
	 * WP_CLI::error() exit as an ExitException instead of terminating
	 * the test process.
	 *
	 * @throws ExitException When the connection check fails.
	 */
	private function invoke_connection() {
		$capture_exit = new ReflectionProperty( WP_CLI::class, 'capture_exit' );
		$capture_exit->setAccessible( true );
		$capture_exit->setValue( null, true );

		$connection = new ReflectionMethod( CLI::class, 'connection' );
		$connection->setAccessible( true );

		try {
			$connection->invoke( new CLI() );
		} finally {
			$capture_exit->setValue( null, false );
		}
	}

	/**
	 * Appends a never-true predicate so the connection check matches no rows.
	 *
	 * @param string $where Existing WHERE fragment.
	 * @return string
	 */
	public static function force_no_query_matches( $where ) {
		return $where . ' AND 1=0';
	}

	/**
	 * A query that legitimately matches zero records is not a disconnection.
	 */
	public function test_connection_does_not_error_on_empty_result() {
		global $wpdb;

		// Force the connection check's query to match nothing, without
		// touching any actual data other tests rely on.
		add_filter( 'wp_stream_db_query_where', array( self::class, 'force_no_query_matches' ) );

		try {
			$this->invoke_connection();
		} finally {
			remove_filter( 'wp_stream_db_query_where', array( self::class, 'force_no_query_matches' ) );
		}

		// Reaching this line means WP_CLI::error() was never triggered.
		$this->assertEmpty( $wpdb->last_error );
	}

	/**
	 * A genuine database error should still be reported as a disconnected site.
	 */
	public function test_connection_errors_on_database_failure() {
		global $wpdb;

		$original_table = $wpdb->stream;
		$wpdb->stream   = $wpdb->prefix . 'stream_table_that_does_not_exist';
		$wpdb->suppress_errors( true );

		try {
			$this->expectException( ExitException::class );
			$this->invoke_connection();
		} finally {
			$wpdb->stream = $original_table;
			$wpdb->suppress_errors( false );
			$wpdb->last_error = '';
		}
	}
}
