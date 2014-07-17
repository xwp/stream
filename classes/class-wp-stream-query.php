<?php

class WP_Stream_Query {

	public static $instance;

	/**
	 * @return WP_Stream_Query
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

	/**
	 * Query Stream records
	 *
	 * @param  array|string $args Query args
	 * @return array              Stream Records
	 */
	public function query( $args ) {
		global $wpdb;

		$defaults = array(
			// Search param
			'search'                => null,
			'search_field'          => 'summary',
			'distinct'              => null,
			'record_greater_than'   => null,
			// Date-based filters
			'date'                  => null,
			'date_from'             => null,
			'date_to'               => null,
			// Pagination params
			'records_per_page'      => get_option( 'posts_per_page' ),
			'paged'                 => 1,
			// Order
			'order'                 => 'desc',
			'orderby'               => 'date',
			// Meta/Taxonomy sub queries
			'meta'                  => array(),
			// Fields selection
			'fields'                => null,
		);

		// Additional property fields
		$properties = array(
			'record'        => null,
			'type'          => 'stream',
			'record_parent' => null,
			'author'        => null,
			'author_role'   => null,
			'ip'            => null,
			'object_id'     => null,
			'site_id'       => null,
			'blog_id'       => null,
			'visibility'    => null,
			'connector'     => null,
			'context'       => null,
			'action'        => null,
		);

		// Add property fields to defaults, including their __in/__not_in variations
		foreach ( $properties as $property => $default ) {
			if ( ! isset( $defaults[ $property ] ) ) {
				$defaults[ $property ] = $default;
			}
			$defaults[ "{$property}__in" ]     = array();
			$defaults[ "{$property}__not_in" ] = array();
		}

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter allows additional arguments to query $args
		 *
		 * @param  array  Array of query arguments
		 * @return array  Updated array of query arguments
		 */
		$args = apply_filters( 'wp_stream_query_args', $args );

		/**
		 * Query results
		 * @var  array
		 */
		$results = WP_Stream::$db->query( $args );

		/**
		 * Allow developers/extensions to modify results array
		 * @param   array  $results  Query Results
		 * @return  array
		 */
		return apply_filters( 'wp_stream_results', $results );
	}
}
