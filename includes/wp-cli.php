<?php
/**
 * Stream commands for WP-CLI
 *
 * @since 2.0.3
 * @see https://github.com/wp-cli/wp-cli
 */
class WP_Stream_WP_CLI_Command extends WP_CLI_Command {

	/**
	 * @subcommand log
	 */
	public function log( $args, $assoc_args ) {
		$query_args = array();

		foreach ( $assoc_args as $key => $value ) {
			$query_args[ $key ] = $value;
		}

		$records = wp_stream_query( $query_args );

		if ( empty( $records ) ) {
			WP_CLI::success( __( 'No results found.', 'stream' ) );

			return;
		}

		foreach ( $records as $record ) {
			if ( ! empty( $assoc_args['fields'] ) ) {
				$fields = array_map( 'trim', explode( ',', $assoc_args['fields'] ) );
				$output = '';

				foreach ( $fields as $field ) {
					$output .= isset( $record->$field ) ? is_array( $record->$field ) ? implode( ', ', $record->$field ) : $record->$field : null;
					$output .= '   ';
				}
			} else {
				$author = new WP_Stream_Author( (int) $record->author, (array) $record->author_meta );
				$output = sprintf( '%s   %s   %s (%d)   %s', $record->created, $record->ip, $author->get_display_name(), $record->author, $record->summary );
			}

			$output = trim( $output );

			WP_CLI::line( $output );
		}

		$total   = count( $records );
		$message = sprintf( _n( '1 result found.', '%s results found.', $total, 'stream' ), $total );

		WP_CLI::success( $message );
	}

}
