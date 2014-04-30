<?php

abstract class WP_Stream_DB_Base {

	function parse( $args ) {
		$defaults = array(
			// Pagination params
			'records_per_page'      => get_option( 'posts_per_page' ),
			'paged'                 => 1,
			// Search param
			'search'                => null,
			'search_field'          => null,
			// Stream core fields filtering
			'type'                  => 'stream',
			'object_id'             => null,
			'ip'                    => null,
			'site_id'               => is_multisite() ? get_current_site()->id : 1,
			'blog_id'               => is_network_admin() ? null : get_current_blog_id(),
			// Author params
			'author'                => null,
			'author_role'           => null,
			// Date-based filters
			'date'                  => null,
			'date_from'             => null,
			'date_to'               => null,
			// Visibility filters
			'visibility'            => null,
			// __in params
			'record_greater_than'   => null,
			'record__in'            => array(),
			'record__not_in'        => array(),
			'record_parent'         => '',
			'record_parent__in'     => array(),
			'record_parent__not_in' => array(),
			'author__in'            => array(),
			'author__not_in'        => array(),
			'author_role__in'       => array(),
			'author_role__not_in'   => array(),
			'ip__in'                => array(),
			'ip__not_in'            => array(),
			// Order
			'order'                 => 'desc',
			'orderby'               => 'ID',
			// Meta/Taxonomy sub queries
			'meta_query'            => array(),
			'context_query'         => array(),
			// Fields selection
			'fields'                => '',
			'distinct'              => false,
			'ignore_context'        => null,
			// Hide records that match the exclude rules
			'hide_excluded'         => ! empty( WP_Stream_Settings::$options['exclude_hide_previous_records'] ),
		);

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter allows additional arguments to query $args
		 *
		 * @param  array  Array of query arguments
		 * @return array  Updated array of query arguments
		 */
		$args = apply_filters( 'wp_stream_query_args', $args );

		if ( true === $args['hide_excluded'] ) {
			// Remove record of excluded connector
			$args['connector__not_in'] = WP_Stream_Settings::get_excluded_by_key( 'connectors' );

			// Remove record of excluded context
			$args['context__not_in'] = WP_Stream_Settings::get_excluded_by_key( 'contexts' );

			// Remove record of excluded actions
			$args['action__not_in'] = WP_Stream_Settings::get_excluded_by_key( 'actions' );

			// Remove record of excluded author
			$args['author__not_in'] = WP_Stream_Settings::get_excluded_by_key( 'authors_and_roles' );

			// Remove record of excluded ip
			$args['ip__not_in'] = WP_Stream_Settings::get_excluded_by_key( 'ip_addresses' );
		}

		return $args;
	}

	function query( $args ) {
		throw new Exception( 'Database drivers should all implement `query` method.' );
	}

	function store( $data ) {
		throw new Exception( 'Database drivers should all implement `store` method.' );
	}

	function delete( $args ) {
		throw new Exception( 'Database drivers should all implement `delete` method.' );
	}

	function reset() {
		throw new Exception( 'Database drivers should all implement `reset` method.' );
	}

}
