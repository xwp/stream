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
	 */
	public function execute( $input ) {
		global $wpdb;

		if ( empty( $input['confirm'] ) ) {
			return new \WP_Error(
				'stream_purge_not_confirmed',
				__( 'Purge requires confirm: true.', 'stream' ),
				array( 'status' => 400 )
			);
		}

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $input['older_than_days'] ) ) {
			$where[]  = 'stream.created < DATE_SUB(NOW(), INTERVAL %d DAY)';
			$params[] = (int) $input['older_than_days'];
		}
		if ( ! empty( $input['connector'] ) ) {
			$where[]  = 'stream.connector = %s';
			$params[] = (string) $input['connector'];
		}
		if ( ! empty( $input['context'] ) ) {
			$where[]  = 'stream.context = %s';
			$params[] = (string) $input['context'];
		}
		if ( ! empty( $input['action'] ) ) {
			$where[]  = 'stream.action = %s';
			$params[] = (string) $input['action'];
		}

		// Reject confirm-only payloads (no actual filter): refuse rather than truncate the table.
		if ( count( $where ) === 1 ) {
			return new \WP_Error(
				'stream_purge_no_filter',
				__( 'At least one filter (older_than_days, connector, context, action) must be supplied.', 'stream' ),
				array( 'status' => 400 )
			);
		}

		$where_sql = implode( ' AND ', $where );

		// Count first so the response is meaningful even if the cascade DELETE returns the
		// combined affected rows from both tables. By the time we reach this point at least
		// one filter has been added (the count( $where ) === 1 guard above ensures $params
		// is non-empty), so $wpdb->prepare() is always called with placeholders.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$count_sql = "SELECT COUNT(*) FROM {$wpdb->stream} AS stream WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( 0 === $deleted ) {
			return array( 'deleted' => 0 );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$delete_sql = "DELETE stream, meta FROM {$wpdb->stream} AS stream LEFT JOIN {$wpdb->streammeta} AS meta ON meta.record_id = stream.ID WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( $delete_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array( 'deleted' => $deleted );
	}
}
