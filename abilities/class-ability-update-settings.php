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
					'description'          => 'Partial settings map keyed by {section}_{field} (e.g. general_records_ttl). Unknown keys are rejected; values are normalized through Stream\'s settings sanitizer. Omitted keys are preserved.',
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
	 *
	 * @param mixed $input Validated input matching get_input_schema(), or null.
	 */
	public function execute( $input = null ) {
		$option_key = $this->plugin->settings->option_key;
		$current    = (array) get_option( $option_key, array() );
		$updates    = isset( $input['settings'] ) ? (array) $input['settings'] : array();

		// Build allowlist of {section}_{field} keys from registered settings.
		$valid_keys = array();
		foreach ( $this->plugin->settings->get_fields() as $section => $section_data ) {
			if ( empty( $section_data['fields'] ) || ! is_array( $section_data['fields'] ) ) {
				continue;
			}
			foreach ( $section_data['fields'] as $field ) {
				if ( ! empty( $field['name'] ) ) {
					$valid_keys[] = $section . '_' . $field['name'];
				}
			}
		}

		// Drop unknown keys before sanitization so callers fail fast.
		$filtered = array_intersect_key( $updates, array_flip( $valid_keys ) );
		if ( empty( $filtered ) ) {
			return new \WP_Error(
				'stream_no_valid_settings',
				__( 'No recognized setting keys were provided. Setting keys follow the {section}_{field} convention.', 'stream' ),
				array( 'status' => 400 )
			);
		}

		// Run only the incoming keys through Stream's sanitize pipeline so
		// values are normalized to their declared field type, then merge over
		// the existing options so unrelated keys are preserved.
		$sanitized = $this->plugin->settings->sanitize_settings( $filtered );
		$merged    = array_merge( $current, $sanitized );

		update_option( $option_key, $merged );

		// Refresh in-memory copy so subsequent abilities see the change.
		$this->plugin->settings->options = $merged;

		return $merged;
	}
}
