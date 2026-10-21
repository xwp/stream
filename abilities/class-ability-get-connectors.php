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
		return __( 'List all Stream connectors this plugin ships, including inactive connectors and those whose dependencies are not currently satisfied. Use this to filter historical records that may have been logged when those connectors were active.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => true,
			'idempotent'   => true,
			'instructions' => __( 'Use to discover the valid connector / context / action values for filters in stream/get-records and stream/create-exclusion-rule. The list is the plugin\'s shipped inventory, not only connectors whose dependencies are met on this site. Connector slugs are stable identifiers, so cache the result if you call abilities repeatedly.', 'stream' ),
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
			'description' => 'Connectors this plugin ships, including inactive and unsatisfied-dependency connectors.',
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

		// Shipped inventory, including inactive / unsatisfied-dependency
		// connectors, so historical records remain filterable via abilities.
		$connectors = apply_filters(
			'wp_stream_abilities_connectors',
			$this->plugin->connectors->get_all( true )
		);

		return $connectors;
	}
}
