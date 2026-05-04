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
			'readonly'   => true,
			'idempotent' => true,
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
	 */
	public function execute( $input ) {
		unset( $input );

		$out        = array();
		$connectors = isset( $this->plugin->connectors->connectors ) ? (array) $this->plugin->connectors->connectors : array();

		foreach ( $connectors as $slug => $connector ) {
			$out[] = array(
				'slug'     => (string) $slug,
				'label'    => method_exists( $connector, 'get_label' ) ? (string) $connector->get_label() : (string) $slug,
				'contexts' => method_exists( $connector, 'get_context_labels' ) ? (array) $connector->get_context_labels() : array(),
				'actions'  => method_exists( $connector, 'get_action_labels' ) ? (array) $connector->get_action_labels() : array(),
			);
		}

		return $out;
	}
}
