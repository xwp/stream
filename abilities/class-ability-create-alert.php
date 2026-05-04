<?php
/**
 * Ability: stream/create-alert — create a Stream alert rule.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Create_Alert
 */
class Ability_Create_Alert extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/create-alert';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Create Stream Alert', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Create a new Stream alert rule. Alerts notify configured channels when records matching the trigger filters are logged.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => false,
			'instructions' => __( 'Create an alert that fires whenever a record matches the supplied filters. Validate the connector/context/action with stream/get-connectors first, and confirm with the user before creating the alert because it changes site behavior.', 'stream' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'alert_type', 'trigger_author', 'trigger_context', 'trigger_action' ),
			'properties'           => array(
				'alert_type'      => array(
					'type'        => 'string',
					'description' => 'Notifier slug. Built-in types are none, highlight, email, ifttt, slack. Other slugs may be registered by extensions.',
				),
				'trigger_author'  => array(
					'type'        => 'string',
					'description' => 'User ID or role slug to match. Use "any" to match all authors.',
				),
				'trigger_context' => array(
					'type'        => 'string',
					'description' => 'Connector or "connector-context" slug. Use "any" to match all contexts.',
				),
				'trigger_action'  => array(
					'type'        => 'string',
					'description' => 'Action slug to match (e.g. "updated"). Use "any" to match all actions.',
				),
				'alert_meta'      => array(
					'type'                 => 'object',
					'description'          => 'Additional notifier-specific configuration (e.g. email recipients, slack webhook).',
					'additionalProperties' => true,
				),
				'status'          => array(
					'type'        => 'string',
					'description' => 'Initial alert status.',
					'enum'        => array( 'wp_stream_enabled', 'wp_stream_disabled' ),
					'default'     => 'wp_stream_enabled',
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
				'id'         => array( 'type' => 'integer' ),
				'status'     => array(
					'type' => 'string',
					'enum' => array( 'wp_stream_enabled', 'wp_stream_disabled' ),
				),
				'title'      => array( 'type' => 'string' ),
				'alert_type' => array( 'type' => array( 'string', 'null' ) ),
				'alert_meta' => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( $input ) {
		$status = isset( $input['status'] ) ? $input['status'] : 'wp_stream_enabled';

		$post_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => $status,
				'post_title'  => '',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$extra_meta = isset( $input['alert_meta'] ) ? (array) $input['alert_meta'] : array();
		$alert_meta = array_merge(
			$extra_meta,
			array(
				'trigger_author'  => $input['trigger_author'],
				'trigger_context' => $input['trigger_context'],
				'trigger_action'  => $input['trigger_action'],
			)
		);

		update_post_meta( $post_id, 'alert_type', $input['alert_type'] );
		update_post_meta( $post_id, 'alert_meta', $alert_meta );

		return array(
			'id'         => (int) $post_id,
			'status'     => (string) get_post_status( $post_id ),
			'title'      => (string) get_the_title( $post_id ),
			'alert_type' => get_post_meta( $post_id, 'alert_type', true ),
			'alert_meta' => (array) get_post_meta( $post_id, 'alert_meta', true ),
		);
	}
}
