<?php
/**
 * Stream command for WP-CLI
 *
 * @see https://github.com/wp-cli/wp-cli
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - CLI
 */
class CLI extends \WP_CLI_Command {

	/**
	 * Query a set of Stream records.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--<field>=<value>]
	 * : One or more args to pass to WP_Stream_Query.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, count, json, json_pretty, csv. Default: table
	 *
	 * ## AVAILABLE FIELDS TO QUERY
	 *
	 * You can build a query from these fields:
	 *
	 * * user_id
	 * * user_id__in
	 * * user_id__not_in
	 * * user_role
	 * * user_role__in
	 * * user_role__not_in
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
	 * * user_id
	 * * user_role
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
	 *
	 * ## EXAMPLES
	 *
	 *     wp stream query --user_role__not_in=administrator --date_after=2015-01-01T12:00:00
	 *     wp stream query --user_id=1 --action=login --records_per_page=50 --fields=created
	 *
	 * @see WP_Stream_Query
	 * @see https://github.com/xwp/stream/wiki/WP-CLI-Command
	 * @see https://github.com/xwp/stream/wiki/Query-Reference
	 *
	 * @param array $args        Unused.
	 * @param array $assoc_args  Fields to return data for.
	 */
	public function query( $args, $assoc_args ) {
		unset( $args );

		$query_args        = array();
		$formatted_records = array();

		$this->connection();

		if ( empty( $assoc_args['fields'] ) ) {
			$fields = array(
				'created',
				'ip',
				'user_id',
				'user_role',
				'summary',
			);
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

		$records = wp_stream_get_instance()->db->query( $query_args );

		// Make structure Formatter compatible.
		foreach ( (array) $records as $key => $record ) {
			$formatted_records[ $key ] = array();

			// Catch any fields missing in records.
			foreach ( $fields as $field ) {
				if ( ! array_key_exists( $field, $record ) ) {
					$record->$field = null;
				}
			}

			foreach ( $record as $field_name => $field ) {

				$formatted_records[ $key ] = array_merge(
					$formatted_records[ $key ],
					$this->format_field( $field_name, $field )
				);
			}
		}

		if ( isset( $assoc_args['format'] ) && 'table' !== $assoc_args['format'] ) {
			if ( 'count' === $assoc_args['format'] ) {
				\WP_CLI::line( count( $records ) );
			}

			if ( 'json' === $assoc_args['format'] ) {
				\WP_CLI::line( wp_stream_json_encode( $formatted_records ) );
			}

			if ( 'json_pretty' === $assoc_args['format'] ) {
				if ( version_compare( PHP_VERSION, '5.4', '<' ) ) {
					\WP_CLI::line( wp_stream_json_encode( $formatted_records ) ); // xss ok.
				} else {
					\WP_CLI::line( wp_stream_json_encode( $formatted_records, JSON_PRETTY_PRINT ) ); // xss ok.
				}
			}

			if ( 'csv' === $assoc_args['format'] ) {
				\WP_CLI::line( $this->csv_format( $formatted_records ) );
			}

			return;
		}

		$formatter = new \WP_CLI\Formatter(
			$assoc_args,
			$fields
		);

		$formatter->display_items( $formatted_records );
	}

	/**
	 * Convert any field to a flat array.
	 *
	 * @param string $name   The output array element name.
	 * @param mixed  $object Any value to be converted to an array.
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
	 * Convert an array of flat records to CSV
	 *
	 * @param array $array  The input array of records.
	 */
	private function csv_format( $array ) {
		$output = fopen( 'php://output', 'w' ); // @codingStandardsIgnoreLine Clever output for WP CLI using php://output

		foreach ( $array as $line ) {
			fputcsv( $output, $line ); // @codingStandardsIgnoreLine
		}

		fclose( $output ); // @codingStandardsIgnoreLine
	}

	/**
	 * Checks for a Stream connection and displays an error or success message.
	 *
	 * @return void
	 */
	private function connection() {
		$query = wp_stream_get_instance()->db->query(
			array(
				'records_per_page' => 1,
				'fields'           => 'created',
			)
		);

		if ( ! $query ) {
			\WP_CLI::error( esc_html__( 'SITE IS DISCONNECTED', 'stream' ) );
		}
	}
}
