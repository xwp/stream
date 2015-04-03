<?php
/**
 * Stream command for WP-CLI
 *
 * @since 2.0.3
 * @see https://github.com/wp-cli/wp-cli
 */
class WP_Stream_WP_CLI_Command extends WP_CLI_Command {

	/**
	 * Query a set of Stream records.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--<field>=<value>]
	 * : One or more args to pass to wp_stream_query.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, json_pretty. Default: table
	 *
	 * ## AVAILABLE FIELDS TO QUERY
	 *
	 * You can build a query from these fields:
	 *
	 * * author
	 * * author__in
	 * * author__not_in
	 * * author_role
	 * * author_role__in
	 * * author_role__not_in
	 * * date
	 * * date_from
	 * * date_to
	 * * date_after
	 * * date_before
	 * * ip
	 * * ip__in
	 * * ip__not_in
	 * * connector
	 * * connector__in
	 * * connector__not_in
	 * * context
	 * * context__in
	 * * context__not_in
	 * * action
	 * * action__in
	 * * action__not_in
	 * * search
	 * * search_field
	 * * record
	 * * record__in
	 * * record__not_in
	 * * records_per_page
	 * * paged
	 * * order
	 * * orderby
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each post:
	 *
	 * * created
	 * * ip
	 * * author
	 * * author_meta.user_login
	 * * author_role
	 * * summary
	 *
	 * These fields are optionally available:
	 *
	 * * ID
	 * * site_id
	 * * blog_id
	 * * object_id
	 * * connector
	 * * context
	 * * action
	 * * author_meta
	 * * stream_meta
	 * * meta.links.self
	 * * meta.links.collection
	 * * meta.score
	 * * meta.sort
	 *
	 * ## EXAMPLES
	 *
	 *     wp stream query --author_role__not_in=administrator --date_after=2015-01-01T12:00:00
	 *     wp stream query --author=1 --action=login --records_per_page=50 --fields=created
	 *
	 * @see WP_Stream_Query
	 * @see https://github.com/wp-stream/stream/wiki/Query-Reference
	 */
	public function query( $args, $assoc_args ) {
		$query_args        = array();
		$formatted_records = array();

		$this->connection();

		if ( empty( $assoc_args['fields'] ) ) {
			$fields = array( 'created', 'ip', 'author', 'author_meta.user_login', 'author_role', 'summary' );
		} else {
			$fields = explode( ',', $assoc_args['fields'] );
		}

		foreach ( $assoc_args as $key => $value ) {
			if ( 'format' === $key ) {
				continue;
			}

			$query_args[ $key ] = $value;
		}

		$query_args['fields'] = implode( ',', $fields );

		$records = wp_stream_query( $query_args );

		if ( isset( $assoc_args['format'] ) ) {
			if ( 'json' === $assoc_args['format'] ) {
				$output = json_encode( $records );
			} elseif ( 'json_pretty' === $assoc_args['format'] ) {
				$output = json_encode( $records, JSON_PRETTY_PRINT );
			}

			echo $output . "\n";

			return;
		}

		// Make structure Formatter compatible
		foreach ( (array) $records as $key => $record ) {
			$formatted_records[ $key ] = array();

			foreach ( $record as $field_name => $field ) {
				$formatted_records[ $key ] = array_merge(
					$formatted_records[ $key ],
					$this->format_field( $field_name, $field )
				);
			}
		}

		$formatter = new \WP_CLI\Formatter(
			$assoc_args,
			$fields
		);

		$formatter->display_items( $formatted_records );

		if ( 0 === ( $found = count( $records ) ) ) {
			WP_CLI::line( __( 'No records found.', 'stream' ) );
		} else {
			WP_CLI::line( sprintf( _n( '1 record found.', '%s records found.', $found, 'stream' ), number_format( $found ) ) );
		}
	}

	/**
	 * Convert any field to a flat array.
	 *
	 * @param string $name    The output array element name
	 * @param mixed  $object  Any value to be converted to an array
	 *
	 * @return array  The flat array
	 */
	private function format_field( $name, $object ) {
		$array = array();

		if ( is_object( $object ) ) {
			foreach ( $object as $key => $property ) {
				$array = array_merge( $array, $this->format_field( $name . '.' . $key, $property ) );
			}
		} elseif ( is_array( $object ) ) {
			$array[ $name ] = $object[0];
		} else {
			$array[ $name ] = $object;
		}

		return $array;
	}

	/**
	 * Checks for a Stream connection and displays an error or success message.
	 *
	 * @return void
	 */
	private function connection() {
		$query = wp_stream_query( array( 'records_per_page' => 1, 'fields' => 'created' ) );

		if ( ! $query ) {
			WP_CLI::error( __( 'SITE IS DISCONNECTED', 'stream' ) );
		}
	}

}
