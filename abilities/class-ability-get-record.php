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

	use Trait_View_Stream_Permission;

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
	 *
	 * @param mixed $input Validated input matching get_input_schema(), or null.
	 */
	public function execute( $input = null ) {
		$id = isset( $input['id'] ) ? (int) $input['id'] : 0;

		// On any multisite install, scope reads to the current blog unless the
		// request is running inside Network Admin — this mirrors
		// Network::network_query_args() (default blog_id is get_current_blog_id()
		// outside network admin) and applies the same protection in REST
		// contexts, where is_network_admin() is always false. Without this
		// guard, a user with view_stream on one site of a network-activated
		// install could fetch records from other sites by guessing IDs.
		$blog_id = ( is_multisite() && ! is_network_admin() )
			? get_current_blog_id()
			: null;

		$row = Record::get_by_id( $id, $blog_id );

		if ( empty( $row ) ) {
			return new \WP_Error(
				'stream_record_not_found',
				__( 'Record not found.', 'stream' ),
				array( 'status' => 404 )
			);
		}

		// Normalize empty meta to a stdClass so wp_json_encode() emits {} and
		// satisfies the declared meta: object output schema. get_metadata()
		// returns [] for records with no meta, which JSON-encodes as a list.
		if ( ! isset( $row['meta'] ) || ! is_array( $row['meta'] ) || array() === $row['meta'] ) {
			$row['meta'] = new \stdClass();
		}

		return $row;
	}
}
