<?php
/**
 * Ability: stream/get-connectors — list registered connectors and their actions/contexts.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Get_Connectors
 */
class Ability_Get_Connectors extends Ability {

	use Trait_View_Stream_Permission;

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/get-connectors';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Get Stream Connectors', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'List all registered Stream connectors with their available contexts and actions. Useful for understanding what activity Stream can track on this site.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => true,
			'idempotent'   => true,
			'instructions' => __( 'Use to discover the valid connector / context / action values for filters in stream/get-records and stream/create-exclusion-rule. Connector slugs are stable identifiers, so cache the result if you call abilities repeatedly.', 'stream' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_output_schema() {
		return array(
			'type'        => 'array',
			'description' => 'Registered connectors.',
			'items'       => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'slug'     => array(
						'type'        => 'string',
						'description' => 'Connector slug.',
					),
					'label'    => array(
						'type'        => 'string',
						'description' => 'Localized connector label.',
					),
					'contexts' => array(
						'type'                 => 'object',
						'description'          => 'Map of context slug to label.',
						'additionalProperties' => array( 'type' => 'string' ),
					),
					'actions'  => array(
						'type'                 => 'object',
						'description'          => 'Map of action slug to label.',
						'additionalProperties' => array( 'type' => 'string' ),
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
		unset( $input );

		if ( ! isset( $this->plugin->connectors ) ) {
			return array();
		}

		// Use the metadata-only accessor that includes admin-only connectors
		// (settings, editor, menus, etc.) so REST/MCP callers see the same
		// connector inventory an admin sees in wp-admin. The plain get_all()
		// reflects only connectors registered for the current request type,
		// which is the wrong frame for the abilities-facing "what exists on this site" answer.
		return $this->plugin->connectors->get_all_including_admin_only();
	}
}
