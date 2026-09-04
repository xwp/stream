<?php
/**
 * Builds DB::get_records() argument arrays from list-table filter input.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - List_Table_Query_Builder
 */
class List_Table_Query_Builder {

	/**
	 * Scalar filter params copied when non-empty.
	 *
	 * @var array<int, string>
	 */
	private const SCALAR_PARAMS = array(
		'search',
		'date',
		'date_from',
		'date_to',
		'date_after',
		'date_before',
	);

	/**
	 * Record property filters, including `__in` / `__not_in` variants.
	 *
	 * @var array<int, string>
	 */
	private const PROPERTY_PARAMS = array(
		'record',
		'site_id',
		'blog_id',
		'object_id',
		'user_id',
		'user_role',
		'ip',
		'connector',
		'context',
		'action',
	);

	/**
	 * Build query args for DB::get_records() from sanitized filter input.
	 *
	 * List_Table maps `wp_stream_filter_input` (and pagination) into `$input`.
	 * This method does not read the request or call the DB.
	 *
	 * @param array $input Sanitized request values plus pagination keys
	 *                     (`paged`, `records_per_page`).
	 * @return array Arguments for DB::get_records().
	 */
	public function build_args( array $input ): array {
		$args = array();

		if ( ! empty( $input['order'] ) ) {
			$args['order'] = $input['order'];
		}

		if ( ! empty( $input['orderby'] ) ) {
			$args['orderby'] = $input['orderby'];
		}

		foreach ( self::SCALAR_PARAMS as $param ) {
			if ( ! empty( $input[ $param ] ) ) {
				$args[ $param ] = $input[ $param ];
			}
		}

		foreach ( self::PROPERTY_PARAMS as $property ) {
			$this->apply_property_filter( $args, $input, $property );
		}

		if ( isset( $input['paged'] ) ) {
			$args['paged'] = $input['paged'];
		}

		if ( isset( $args['context'] ) && 0 === strpos( (string) $args['context'], 'group-' ) ) {
			$args['connector'] = str_replace( 'group-', '', $args['context'] );
			$args['context']   = '';
		}

		if ( ! isset( $args['records_per_page'] ) ) {
			$args['records_per_page'] = $input['records_per_page'] ?? 20;
		}

		$args['records_per_page'] = apply_filters( 'stream_records_per_page', $args['records_per_page'] );

		return $args;
	}

	/**
	 * Apply a scalar property and its `__in` / `__not_in` list variants.
	 *
	 * @param array  $args     Args bag (by reference).
	 * @param array  $input    Sanitized input.
	 * @param string $property Property name.
	 * @return void
	 */
	private function apply_property_filter( array &$args, array $input, string $property ): void {
		$value = $input[ $property ] ?? null;

		// Allow 0 values.
		if ( isset( $value ) && '' !== $value && false !== $value ) {
			$args[ $property ] = $value;
		}

		$value_in = $input[ $property . '__in' ] ?? null;

		if ( $value_in ) {
			$args[ $property . '__in' ] = is_array( $value_in ) ? $value_in : explode( ',', $value_in );
		}

		$value_not_in = $input[ $property . '__not_in' ] ?? null;

		if ( $value_not_in ) {
			$args[ $property . '__not_in' ] = is_array( $value_not_in )
				? $value_not_in
				: explode( ',', $value_not_in );
		}
	}
}
