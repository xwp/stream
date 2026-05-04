<?php
/**
 * Ability: stream/get-settings — return the current Stream plugin settings.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Get_Settings
 */
class Ability_Get_Settings extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/get-settings';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Get Stream Settings', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Return the current Stream plugin settings (role access, record TTL, exclusion rules, advanced flags).', 'stream' );
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
			'type'                 => 'object',
			'additionalProperties' => true,
			'description'          => 'Settings keyed by {section}_{field} (e.g. general_records_ttl, advanced_enable_abilities_api).',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( $input ) {
		unset( $input );

		return (array) $this->plugin->settings->options;
	}
}
