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
	 *
	 * @param mixed $input Validated input matching get_input_schema(), or null.
	 */
	public function execute( $input = null ) {
		$status = isset( $input['status'] ) ? $input['status'] : 'wp_stream_enabled';

		// Validate alert_type against the registered notifier slugs. The schema
		// can't enum these because alert types are extensible via the
		// wp_stream_alert_types filter -- a hardcoded enum would lock out
		// 3rd-party notifiers. Validate at execute() time instead.
		$registered_types = isset( $this->plugin->alerts->alert_types )
			? array_keys( (array) $this->plugin->alerts->alert_types )
			: array();
		if ( ! empty( $registered_types ) && ! in_array( $input['alert_type'], $registered_types, true ) ) {
			return new \WP_Error(
				'stream_unknown_alert_type',
				sprintf(
					/* translators: 1: alert_type slug supplied by caller, 2: comma-separated list of registered alert type slugs */
					__( 'Unknown alert_type "%1$s". Registered types: %2$s.', 'stream' ),
					(string) $input['alert_type'],
					implode( ', ', $registered_types )
				),
				array( 'status' => 400 )
			);
		}

		// Mirror the admin form's connector-context split so Alert::get_title()
		// and Alert_Trigger_Context::check_record() see the same data shape
		// they see when an admin creates the alert through the UI.
		$trigger_context_raw = (string) $input['trigger_context'];
		if ( false !== strpos( $trigger_context_raw, '-' ) ) {
			list( $trigger_connector, $trigger_context ) = explode( '-', $trigger_context_raw, 2 );
		} else {
			$trigger_connector = $trigger_context_raw;
			$trigger_context   = '';
		}

		$extra_meta = isset( $input['alert_meta'] ) ? (array) $input['alert_meta'] : array();
		$alert_meta = array_merge(
			$extra_meta,
			array(
				'trigger_author'    => $input['trigger_author'],
				'trigger_connector' => $trigger_connector,
				'trigger_context'   => $trigger_context,
				'trigger_action'    => $input['trigger_action'],
			)
		);

		// Build an Alert model so we can reuse Stream's title-generation logic
		// (otherwise wp_insert_post stores 'Auto Draft' for empty post_title).
		$alert_model = new Alert(
			(object) array(
				'alert_type' => $input['alert_type'],
				'alert_meta' => $alert_meta,
				'status'     => $status,
			),
			$this->plugin
		);
		$post_title  = $alert_model->get_title();

		$post_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => $status,
				'post_title'  => $post_title,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

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
