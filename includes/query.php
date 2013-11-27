<?php

class WP_Stream_Query {
	
	public static $instance;

	public static function get_instance() {
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
	 * @return [type]             Stream 
	 */
	public function query( $args ) {
		global $wpdb;

		$defaults = array(
			// Pagination params
			'records_per_page' => '10',
			'page'             => 1,
			// Search params
			'search'           => null,
			// Stream core fields filtering
			'type'             => 'stream',
			'object_id'        => null,
			'ip'               => null,
			// Order
			'order'            => 'desc',
			'orderby'          => 'ID',
			// Meta/Taxonomy sub queries
			'meta_query'       => array(),
			'context_query'    => array(),
			// Fields selection
			'fields'           => '',
			);

		$args = wp_parse_args( $args, $defaults );

		$args = apply_filters( 'stream_query_args', $args );

		$join  = '';
		$where = '';

		/**
		 * PARSE META QUERY PARAMS
		 */
		$meta_query = new WP_Meta_Query;
		$meta_query->parse_query_vars( $args );
		if ( ! empty( $meta_query->queries ) ) {
			$clauses = $meta_query->get_sql( 'stream', $wpdb->stream, 'ID' );
			$join   .= str_replace( 'stream_id', 'record_id', $clauses['join'] );
			$where  .= str_replace( 'stream_id', 'record_id', $clauses['where'] );
		}

		/**
		 * PARSE CONTEXT PARAMS
		 */
		// TODO

		/**
		 * PARSE PAGINATION PARAMS
		 */
		$page   = $args['page'];
		$pgstrt = ($page - 1) * $args['records_per_page'];
		$limits = "LIMIT $pgstrt, {$args['records_per_page']}";

		/**
		 * PARSE ORDER PARAMS
		 */
		if ( in_array(
			$args['orderby'],
			array( 'ID', 'site_id', 'object_id', 'author', 'summary', 'visibility', 'parent', 'type', 'created' )
			) ) {
			$orderby = $wpdb->stream . '.' . $args['orderby'];
		}
		elseif ( in_array( $args['orderby'], array( 'connector', 'context', 'action' ) ) ) {
			$join   .= sprintf(
				' INNER JOIN %1$s ON ( %1$s.record_id = %2$s.ID )',
				$wpdb->streamcontext,
				$wpdb->stream
			);
			$orderby = $wpdb->streamcontext . '.' . $args['orderby'];
		}
		elseif ( $args['orderby'] == 'meta_value_num' && ! empty( $args['meta_key'] ) ) {
			$orderby = "CAST($wpdb->streammeta.meta_value AS SIGNED)";
		}
		elseif ( $args['orderby'] == 'meta_value' && ! empty( $args['meta_key'] ) ) {
			$orderby = "$wpdb->streammeta.meta_value";
		}
		else {
			$orderby = "$wpdb->stream.ID";
		}
		$orderby = 'ORDER BY ' . $orderby . ' ' . $args['order'];

		/**
		 * PARSE FIELDS PARAMETER
		 */
		$fields = $args['fields'];
		$select = "$wpdb->stream.*";
		if ( $fields == 'ID' ) {
			$select = "$wpdb->stream.ID";
		}
		elseif ( $fields == 'summary' ) {
			$select = "$wpdb->stream.summary, $wpdb->stream.ID";
		}

		/**
		 * BUILD UP THE FINAL QUERY
		 */
		$sql = "SELECT $select
		FROM $wpdb->stream
		$join
		WHERE 1=1 $where
		$orderby
		$limits";
		
		if ( ! empty( $fields ) ) {
			$results = $wpdb->get_col( $sql );
		} else {
			$results = $wpdb->get_results( $sql );
		}

	}

}

function stream_query( $args ) {
	return WP_Stream_Query::get_instance()->query( $args );
}