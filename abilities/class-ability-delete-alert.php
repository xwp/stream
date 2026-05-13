<?php
/**
 * Ability: stream/delete-alert — permanently delete a Stream alert rule.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Delete_Alert
 */
class Ability_Delete_Alert extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/delete-alert';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Delete Stream Alert', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Permanently delete a Stream alert rule by ID. Returns 404 when the ID is unknown or refers to a non-alert post.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => false,
			'destructive'  => true,
			'idempotent'   => true,
			'instructions' => __( 'Permanently deletes an alert by ID. Run stream/get-alerts first to confirm the alert exists and to show the user which rule will be removed. Idempotent: deleting an already-deleted alert returns 404, not an error to retry.', 'stream' ),
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
					'description' => 'Alert post ID (a wp_stream_alerts post).',
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
			'additionalProperties' => false,
			'properties'           => array(
				'deleted' => array( 'type' => 'boolean' ),
				'id'      => array( 'type' => 'integer' ),
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

		// Load via Alerts::get_alert() so we can leverage the existing factory.
		// It returns an Alert with ID=null for unknown post IDs, which we treat
		// as not-found. The Alert::delete() method then double-checks the post
		// type to refuse deleting non-alert posts.
		$alert = $id > 0 ? $this->plugin->alerts->get_alert( $id ) : null;

		if ( ! $alert || empty( $alert->ID ) ) {
			return new \WP_Error(
				'stream_alert_not_found',
				__( 'Alert not found.', 'stream' ),
				array( 'status' => 404 )
			);
		}

		$result = $alert->delete();

		if ( ! $result ) {
			return new \WP_Error(
				'stream_alert_not_found',
				__( 'Alert not found.', 'stream' ),
				array( 'status' => 404 )
			);
		}

		return array(
			'deleted' => true,
			'id'      => $id,
		);
	}
}
