<?php
/**
 * Ability: stream/update-settings — partial update of Stream settings.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Update_Settings
 */
class Ability_Update_Settings extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/update-settings';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Update Stream Settings', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Partially update Stream settings. Provide only the keys to change; existing values for other keys are preserved. Setting keys follow the {section}_{field} convention (e.g. general_records_ttl).', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => false,
			'idempotent'   => true,
			'instructions' => __( 'Apply a partial update to Stream settings. Always call stream/get-settings first so you can show the user the existing values, and require confirmation before changing keys that affect data retention (e.g. general_records_ttl).', 'stream' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'settings' ),
			'properties'           => array(
				'settings' => array(
					'type'                 => 'object',
					'description'          => 'Partial settings map keyed by {section}_{field}. Values overwrite existing entries; omitted keys are preserved.',
					'additionalProperties' => true,
					'minProperties'        => 1,
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
			'description'          => 'The complete settings array after the update.',
			'additionalProperties' => true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( $input = null ) {
		$option_key = $this->plugin->settings->option_key;
		$current    = (array) get_option( $option_key, array() );
		$updates    = isset( $input['settings'] ) ? (array) $input['settings'] : array();

		$merged = array_merge( $current, $updates );
		update_option( $option_key, $merged );

		// Refresh in-memory copy so subsequent abilities see the change.
		$this->plugin->settings->options = $merged;

		return $merged;
	}
}
