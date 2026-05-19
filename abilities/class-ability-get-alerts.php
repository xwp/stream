<?php
/**
 * Ability: stream/get-alerts — list configured alert rules.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Get_Alerts
 */
class Ability_Get_Alerts extends Ability {

	use Trait_View_Stream_Permission;

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/get-alerts';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Get Stream Alerts', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'List all configured Stream alert rules. Use the status filter to narrow to enabled or disabled alerts only.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => true,
			'idempotent'   => true,
			'instructions' => __( 'Use to enumerate existing alert rules before creating new ones (stream/create-alert) or removing them (stream/delete-alert). Pass status="enabled" to see only active alerts.', 'stream' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by alert status.',
					'enum'        => array( 'enabled', 'disabled', 'any' ),
					'default'     => 'any',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_output_schema() {
		return array(
			'type'        => 'array',
			'description' => 'Configured alert rules.',
			'items'       => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'id'         => array( 'type' => 'integer' ),
					'status'     => array(
						'type' => 'string',
						'enum' => array( Alerts::STATUS_ENABLED, Alerts::STATUS_DISABLED ),
					),
					'title'      => array( 'type' => 'string' ),
					'alert_type' => array( 'type' => array( 'string', 'null' ) ),
					'alert_meta' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
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
		$requested = isset( $input['status'] ) ? $input['status'] : 'any';

		switch ( $requested ) {
			case 'enabled':
				$statuses = array( Alerts::STATUS_ENABLED );
				break;
			case 'disabled':
				$statuses = array( Alerts::STATUS_DISABLED );
				break;
			default:
				$statuses = array( Alerts::STATUS_ENABLED, Alerts::STATUS_DISABLED );
		}

		$alerts = $this->plugin->alerts->get_alerts( $statuses );

		// Output enum is fixed (STATUS_ENABLED / STATUS_DISABLED). Alerts::get_alerts()
		// only fetches those two statuses today, but a third party could trash an
		// alert post or wire up a custom status. Defensive filter keeps the
		// response schema-valid for any downstream changes.
		$allowed_statuses = array( Alerts::STATUS_ENABLED, Alerts::STATUS_DISABLED );

		$out = array();
		foreach ( $alerts as $alert ) {
			if ( ! in_array( (string) $alert->status, $allowed_statuses, true ) ) {
				continue;
			}

			// Alert::$alert_meta defaults to array(); an empty PHP array() also
			// JSON-encodes as a list ([]), which violates the declared object
			// output schema. Normalize empty/non-array values to a real object
			// so wp_json_encode() emits {} when there is no meta.
			$alert_meta = is_array( $alert->alert_meta ) && ! empty( $alert->alert_meta )
				? $alert->alert_meta
				: new \stdClass();

			$out[] = array(
				'id'         => (int) $alert->ID,
				'status'     => (string) $alert->status,
				'title'      => (string) get_the_title( $alert->ID ),
				'alert_type' => $alert->alert_type,
				'alert_meta' => $alert_meta,
			);
		}

		return $out;
	}
}
