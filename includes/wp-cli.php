<?php
/**
 * Stream commands for WP-CLI
 *
 * @since 2.0.3
 * @see https://github.com/wp-cli/wp-cli
 */
class WP_Stream_WP_CLI_Command extends WP_CLI_Command {

	/**
	 * Subcommand to query a set of Stream records
	 *
	 * You may use any associative argument(s) available in the
	 * WP_Stream_Query::instance()->query() method.
	 *
	 * Examples:
	 * wp stream query --author_role__not_in=administrator --date_after=2015-01-01T12:00:00
	 * wp stream query --author=1 --action=login --records_per_page=50 --fields=created
	 *
	 * @see WP_Stream_Query
	 * @subcommand query
	 */
	public function query( $args, $assoc_args ) {
		self::connection();

		$start      = microtime( true );
		$query_args = array();

		foreach ( $assoc_args as $key => $value ) {
			$query_args[ $key ] = $value;
		}

		if ( empty( $query_args['fields'] ) ) {
			$defaults = array( 'created', 'ip', 'author', 'author_meta.user_login', 'author_role', 'summary' );

			/**
			 * Filter for default fields when arg is not provided
			 *
			 * @return array $defaults
			 */
			$defaults = apply_filters( 'wp_stream_wp_cli_default_fields', (array) $defaults );

			$query_args['fields'] = implode( ',', array_map( 'trim', $defaults ) );
		}

		$records = wp_stream_query( $query_args );

		if ( empty( $records ) ) {
			WP_CLI::success( __( 'No results found.', 'stream' ) );
			return;
		}

		$fields = array_map( 'trim', explode( ',', $query_args['fields'] ) );

		foreach ( $records as $record ) {
			$output = '';

			foreach ( $fields as $field ) {
				$values = wp_list_pluck( $records, $field );

				array_walk( $values, function( &$value ) {
					$value = is_array( $value ) ? $value[0] : $value;
				});

				$longest = max( array_map( 'strlen', $values ) );
				$value   = is_array( $record->$field ) ? implode( ', ', $record->$field ) : $record->$field;
				$output .= $value;
				$diff    = absint( $longest - strlen( $value ) );

				for ( $i = 0; $i < $diff; $i++ ) {
					$output .= ' ';
				}

				$output .= '   ';
			}

			WP_CLI::line( trim( $output ) );
		}

		$found   = count( $records );
		$stop    = microtime( true ) - $start;
		$message = sprintf( _n( '1 result found in %s seconds.', '%s results found in %s seconds.', $found, 'stream' ), number_format( $found ), number_format( $stop, 4 ) );

		WP_CLI::success( $message );
	}

	/**
	 * Checks for a Stream connection and displays an error or success message
	 *
	 * @return void
	 */
	private static function connection() {
		WP_CLI::line( __( 'Establishing secure connection with WP Stream...', 'stream' ) );

		$query = wp_stream_query( array( 'records_per_page' => 1, 'fields' => 'created' ) );

		if ( ! $query ) {
			WP_CLI::error( __( 'SITE IS DISCONNECTED', 'stream' ) );
		} else {
			WP_CLI::success( __( 'SITE IS CONNECTED', 'stream' ) );
		}
	}

}
