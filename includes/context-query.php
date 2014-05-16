<?php
/**
 * Context Query Helper for WP_Stream_Query
 * Based off WP_Meta_Query class from WordPress core
 *
 * Allowed args:
 * Flat style: {context|action|connector}{|__in|__not_in} => array|string
 *  ie: context__in => array('posts') / action__not_in => 'trashed'
 *
 * Complex style:
 * context_query => array(
 *  array(
 *   'context' => array('posts', 'forms'),
 *   'action' => 'updated'
 *  )
 * )
 * OR
 * context_query = array(
 *  array(
 *   'context' => array( 'value' => 'something', 'compare' => 'like' ),
 *   'action' => array( 'value' => 'pub_', 'compare' => 'rlike' ),
 *  )
 * )
 *
 * This supports the 'relation' var just like WP_Meta_Query
 *
 * @author  Shady Sharaf <shady@x-team.com>
 */
class WP_Stream_Context_Query {

	public $relation = null;

	function __construct( $query = false ) {
		if ( $query ) {
			$this->parse_query_vars( $query );
		}
	}

	function parse_query_vars( $query ) {
		$context_query = array();

		// Check for 'flat' query params
		foreach ( array( 'connector', 'context', 'action' ) as $key ) {
			foreach ( array( '', '__in', '__not_in' ) as $i => $suffix ) {
				$lookup  = array( '=', 'IN', 'NOT IN' );
				$compare = $lookup[ $i ];
				if ( ! empty( $query[ $key . $suffix ] ) ) {
					$context_query[] = array(
						$key => array(
							'value'   => $query[ $key . $suffix ],
							'compare' => $compare,
						),
					);
				}
			}
		}

		if ( ! empty( $query['context_query'] ) ) {
			$context_query = array_merge( $context_query, $query['context_query'] );
		}

		if ( isset( $context_query['relation'] ) && 'OR' === strtoupper( $context_query['relation'] ) ) {
			$this->relation = 'OR';
		} else {
			$this->relation = 'AND';
		}

		$this->queries = $context_query;
	}

	function get_sql() {
		global $wpdb;

		if ( empty( $this->queries ) ) {
			return array( 'join' => '', 'where' => '' );
		}

		$context_table  = WP_Stream_DB::$table_context;
		$main_table     = WP_Stream_DB::$table;
		$meta_id_column = 'meta_id';

		$join  = array();
		$where = array();

		$queries = $this->queries;

		$meta_query = new WP_Meta_Query;

		// Context table is always joined
		// $join[] = " INNER JOIN $context_table ON $main_table.ID = $context_table.record_id";

		foreach ( $queries as $i => $query ) {
			foreach ( $query as $key => $args ) {
				$type = $meta_query->get_cast_for_type( isset( $args['type'] ) ? $args['type'] : '' );

				$value = isset( $args['value'] ) ? $args['value'] : null;

				// Allow 'context' => array('val1', 'val2') as well
				if ( is_null( $value ) ) {
					$args = array( 'value' => $args );
					$value = $args['value'];
				}

				if ( isset( $args['compare'] ) ) {
					$compare = strtoupper( $args['compare'] );
				} else {
					$compare = is_array( $value ) ? 'IN' : '=';
				}

				$operators = array(
					'=',
					'!=',
					'LIKE',
					'NOT LIKE',
					'IN',
					'NOT IN',
					'REGEXP',
					'NOT REGEXP',
					'RLIKE',
				);

				if ( ! in_array( $compare, $operators ) ) {
					$compare = '=';
				}

				if ( 'IN' === substr( $compare, -2 ) ) {
					if ( ! is_array( $value ) ) {
						$value = preg_split( '/[,\s]+/', $value );
					}
					$compare_string = '(' . substr( str_repeat( ',%s', count( $value ) ), 1 ) . ')';
				} elseif ( 'LIKE' === substr( $compare, -4 ) ) {
					$value          = '%' . like_escape( $value ) . '%';
					$compare_string = '%s';
				} else {
					$compare_string = '%s';
				}

				if ( ! empty( $where[ $i ] ) ) {
					$where[ $i ] .= ' AND ';
				} else {
					$where[ $i ] = '';
				}

				$where[ $i ] = ' (' . $where[ $i ] . $wpdb->prepare( "CAST($context_table.$key AS {$type}) {$compare} {$compare_string})", $value );
			}
		}

		$where = array_filter( $where );

		if ( empty( $where ) ) {
			$where = '';
		} else {
			$where = ' AND (' . implode( "\n{$this->relation} ", $where ) . ' )';
		}

		$join = implode( "\n", $join );

		/**
		 * Filter allows modification of context sql statement
		 *
		 * @param  array   Array of context sql statement components
		 * @return string  Updated context sql statement
		 */
		return apply_filters_ref_array( 'get_context_sql', array( compact( 'join', 'where' ), $this->queries ) );
	}

}
