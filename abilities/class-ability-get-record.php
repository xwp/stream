<?php
/**
 * Ability: stream/get-record — fetch a single Stream record by ID.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Get_Record
 */
class Ability_Get_Record extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/get-record';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Get Stream Record', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Retrieve a single Stream log record by its ID, including its associated metadata.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => true,
			'idempotent'   => true,
			'instructions' => __( 'Use after stream/get-records to fetch the full record plus its metadata for a specific log entry, when summary fields from the list response are not enough. Pass the integer ID returned by stream/get-records.', 'stream' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Read abilities use Stream's view capability so editors / other allowed
	 * roles can call them, matching the admin UI's record-viewing permissions.
	 */
	public function permission_callback( $input = array() ) {
		unset( $input );
		return current_user_can( 'view_stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'id' ),
			'properties'           => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Stream record ID.',
					'minimum'     => 1,
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
			'additionalProperties' => true,
			'properties'           => array(
				'ID'        => array( 'type' => 'integer' ),
				'site_id'   => array( 'type' => 'integer' ),
				'blog_id'   => array( 'type' => 'integer' ),
				'object_id' => array( 'type' => array( 'integer', 'null' ) ),
				'user_id'   => array( 'type' => 'integer' ),
				'user_role' => array( 'type' => 'string' ),
				'summary'   => array( 'type' => 'string' ),
				'created'   => array( 'type' => 'string' ),
				'connector' => array( 'type' => 'string' ),
				'context'   => array( 'type' => 'string' ),
				'action'    => array( 'type' => 'string' ),
				'ip'        => array( 'type' => array( 'string', 'null' ) ),
				'meta'      => array(
					'type'        => 'object',
					'description' => 'Per-record metadata key/value pairs.',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( $input = null ) {
		global $wpdb;

		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;

		// Stream's Query class doesn't expose a single-ID filter (record__in
		// is broken for one-element arrays due to array_shift() in
		// Query::query()), so query the table directly. We add an explicit
		// blog_id filter on multisite so the response can never leak a record
		// from another site on a network install — the admin records page is
		// scoped per-site and abilities must match.
		$where    = '';
		$prepared = array( $id );
		if ( is_multisite() && ! $this->plugin->is_network_activated() ) {
			$where      = ' AND blog_id = %d';
			$prepared[] = get_current_blog_id();
		}

		// $wpdb->stream and {$where} are constructed from string literals in this
		// method (no user input), and $prepared holds only an int id and (on
		// multisite) the integer blog id from get_current_blog_id().
		$sql = "SELECT * FROM {$wpdb->stream} WHERE ID = %d{$where}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $prepared ), ARRAY_A );

		if ( empty( $row ) ) {
			return new \WP_Error(
				'stream_record_not_found',
				__( 'Record not found.', 'stream' ),
				array( 'status' => 404 )
			);
		}

		$row['meta'] = (array) get_metadata( 'record', $row['ID'] );

		return $row;
	}
}
