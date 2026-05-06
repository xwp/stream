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
					'description' => 'Number of stream records deleted (meta rows are cascaded by record_id).',
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

		// Delete stream rows first and capture rows_affected so the response reflects
		// the actual count (rather than a stale pre-DELETE COUNT). $params is guaranteed
		// non-empty here by the count( $where ) === 1 guard above. MySQL requires the
		// "DELETE alias FROM tbl AS alias" form when the WHERE references an alias.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$delete_sql = "DELETE stream FROM {$wpdb->stream} AS stream WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( $delete_sql, $params ) );
		$deleted = (int) $wpdb->rows_affected;

		if ( 0 === $deleted ) {
			return array( 'deleted' => 0 );
		}

		// Sweep orphaned meta rows whose parent record was just deleted. Idempotent
		// and safe to run unconditionally; the LEFT JOIN scopes the cleanup to
		// orphans across the whole streammeta table, which also catches any prior
		// orphans without growing this query's blast radius beyond a single sweep.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			"DELETE meta FROM {$wpdb->streammeta} AS meta
			 LEFT JOIN {$wpdb->stream} AS stream ON stream.ID = meta.record_id
			 WHERE stream.ID IS NULL"
		);

		return array( 'deleted' => $deleted );
	}
}
