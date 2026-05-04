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
			'readonly'   => true,
			'idempotent' => true,
		);
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
	public function execute( $input ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;

		$records = $this->plugin->db->get_records( array( 'record' => $id ) );

		if ( empty( $records ) ) {
			return new \WP_Error(
				'stream_record_not_found',
				__( 'Record not found.', 'stream' ),
				array( 'status' => 404 )
			);
		}

		$record         = (array) $records[0];
		$record['meta'] = (array) get_metadata( 'record', $record['ID'] );

		return $record;
	}
}
