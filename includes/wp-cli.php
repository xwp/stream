<?php
/**
 * Stream commands for WP-CLI
 *
 * @since 2.0.3
 * @see https://github.com/wp-cli/wp-cli
 */
class WP_Stream_WP_CLI_Command extends WP_CLI_Command {

	/**
	 * Query subcommand to retreive a list of activity records
	 *
	 * You may use any associative argument available in the
	 * WP_Stream_Query object.
	 *
	 * @see WP_Stream_Query
	 * @subcommand query
	 * @synopsis [--arg=<key>]
	 */
	public function query( $args, $assoc_args ) {
		$query_args = array();

		foreach ( $assoc_args as $key => $value ) {
			$query_args[ $key ] = $value;
		}

		if ( empty( $query_args['fields'] ) ) {
			$defaults = array( 'created', 'ip', 'author_meta.user_login', 'author_role', 'summary' );

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
			$output   = '';

			foreach ( $fields as $field ) {
				$values  = wp_list_pluck( $records, $field );

				array_walk( $values, function( &$value ) {
					$value = is_array( $value ) ? implode( ', ', $value ) : $value;
				});

				$longest = max( array_map( 'strlen', $values ) );
				$value   = is_array( $record->$field ) ? implode( ', ', $record->$field ) : $record->$field;
				$output .= $value;
				$diff    = absint( $longest - strlen( $value ) );

				for ( $i = 0;  $i < $diff; $i++ ) {
					$output .= ' ';
				}

				$output .= '   ';
			}

			WP_CLI::line( trim( $output ) );
		}

		$found   = count( $records );
		$message = sprintf( _n( '1 result found.', '%s results found.', $found, 'stream' ), number_format( $found ) );

		WP_CLI::success( $message );
	}

}
