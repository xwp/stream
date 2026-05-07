<?php
/**
 * Ability: stream/purge-records — delete Stream records matching filters.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Purge_Records
 */
class Ability_Purge_Records extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/purge-records';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Purge Stream Records', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Permanently delete Stream records (and their meta) that match the supplied filters. Destructive — requires confirm: true. At least one filter must be supplied; an empty filter set is rejected to prevent accidental full wipes.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => false,
			'destructive'  => true,
			// HTTP-idempotent: applying the same purge twice ends in the same state.
			// WP REST router requires destructive AND idempotent to route to DELETE.
			'idempotent'   => true,
			'instructions' => __( 'Permanently deletes log records matching the filters. ALWAYS run stream/get-records with the same filters first to show the user how many records will be removed, and require explicit confirmation. The ability also requires confirm=true in the input. There is no undo.', 'stream' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'confirm' ),
			'properties'           => array(
				'confirm'         => array(
					'type'        => 'boolean',
					'description' => 'Must be true to authorize the destructive purge.',
					'enum'        => array( true ),
				),
				'older_than_days' => array(
					'type'        => 'integer',
					'description' => 'Delete only records created more than this many days ago.',
					'minimum'     => 1,
				),
				'connector'       => array(
					'type'        => 'string',
					'description' => 'Connector slug to limit the purge to.',
				),
				'context'         => array(
					'type'        => 'string',
					'description' => 'Context slug to limit the purge to.',
				),
				'action'          => array(
					'type'        => 'string',
					'description' => 'Action slug to limit the purge to.',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_output_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'deleted' => array(
					'type'        => 'integer',
					'description' => 'Number of stream records deleted. Associated meta rows are removed in the same multi-table DELETE.',
					'minimum'     => 0,
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $input Validated input matching get_input_schema(), or null.
	 */
	public function execute( $input = null ) {
		global $wpdb;

		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error(
				'stream_purge_not_confirmed',
				__( 'Purge requires confirm: true.', 'stream' ),
				array( 'status' => 400 )
			);
		}

		$where        = array( '1=1' );
		$params       = array();
		$filter_count = 0;

		if ( ! empty( $input['older_than_days'] ) ) {
			// Stream stores `created` in UTC (Log::log() writes current_time('mysql', true)).
			// MySQL's NOW() uses the server timezone, so comparing against it can
			// delete too many or too few rows on hosts where the server is not UTC.
			// Mirror Admin::purge_scheduled_action(): compute a UTC cutoff in PHP
			// and bind it as a string.
			$cutoff = ( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) )
				->sub( \DateInterval::createFromDateString( ( (int) $input['older_than_days'] ) . ' days' ) )
				->format( 'Y-m-d H:i:s' );

			$where[]  = 'stream.created < %s';
			$params[] = $cutoff;
			++$filter_count;
		}
		if ( ! empty( $input['connector'] ) ) {
			$where[]  = 'stream.connector = %s';
			$params[] = (string) $input['connector'];
			++$filter_count;
		}
		if ( ! empty( $input['context'] ) ) {
			$where[]  = 'stream.context = %s';
			$params[] = (string) $input['context'];
			++$filter_count;
		}
		if ( ! empty( $input['action'] ) ) {
			$where[]  = 'stream.action = %s';
			$params[] = (string) $input['action'];
			++$filter_count;
		}

		// Reject confirm-only payloads (no actual filter): refuse rather than truncate the table.
		if ( 0 === $filter_count ) {
			return new \WP_Error(
				'stream_purge_no_filter',
				__( 'At least one filter (older_than_days, connector, context, action) must be supplied.', 'stream' ),
				array( 'status' => 400 )
			);
		}

		// On any multisite install, scope the purge to the current blog whenever
		// the request is not running inside Network Admin. REST is never
		// is_network_admin(), so this also protects network-activated installs
		// from a site admin (with the settings cap) wiping another site's records
		// via the REST endpoint. Mirrors Network::network_query_args() defaults.
		// Added after the no-filter check so a confirm-only payload is still rejected.
		if ( is_multisite() && ! is_network_admin() ) {
			$where[]  = 'stream.blog_id = %d';
			$params[] = get_current_blog_id();
		}

		$where_sql = implode( ' AND ', $where );

		// Count matching parent rows up-front so the response reports the number
		// of *records* deleted, independent of how many meta rows were attached.
		// $params is guaranteed non-empty here by the $filter_count > 0 guard above.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$count_sql = "SELECT COUNT(*) FROM {$wpdb->stream} AS stream WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$deleted = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

		if ( 0 === $deleted ) {
			return array( 'deleted' => 0 );
		}

		// Delete matching stream rows AND their meta in a single multi-table DELETE,
		// mirroring Admin::purge_scheduled_action(). Doing both sides in one statement
		// avoids a follow-up full-table scan over $wpdb->streammeta to clean up
		// orphans, which on busy sites could lock the meta table for a long time.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$delete_sql = "DELETE stream, meta
			FROM {$wpdb->stream} AS stream
			LEFT JOIN {$wpdb->streammeta} AS meta ON meta.record_id = stream.ID
			WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( $delete_sql, $params ) );

		return array( 'deleted' => $deleted );
	}
}
