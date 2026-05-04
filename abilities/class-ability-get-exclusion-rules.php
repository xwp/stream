<?php
/**
 * Ability: stream/get-exclusion-rules — return current Stream exclusion rules.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Get_Exclusion_Rules
 */
class Ability_Get_Exclusion_Rules extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/get-exclusion-rules';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Get Stream Exclusion Rules', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Return the configured exclusion rules that prevent matching activity from being recorded.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => true,
			'idempotent'   => true,
			'instructions' => __( 'Use to inspect which activity is being silently dropped before it reaches the log. Run before stream/create-exclusion-rule so you can avoid duplicate rules.', 'stream' ),
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
			'description' => 'Exclusion rules. Each entry is an object with optional author_or_role, connector, context, action, and ip_address fields. Stream stores rules as parallel arrays keyed by index.',
			'items'       => array(
				'type'                 => 'object',
				'additionalProperties' => true,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( $input = null ) {
		unset( $input );

		$options = (array) $this->plugin->settings->options;
		$rules   = isset( $options['exclude_rules'] ) ? (array) $options['exclude_rules'] : array();

		// Reuse Log's row pivot so output matches how Stream applies the rules internally.
		$rows = $this->plugin->log->exclude_rules_by_rows( $rules );

		// Drop the internal exclude_row marker from the output.
		$out = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				unset( $row['exclude_row'] );
			}
			$out[] = $row;
		}

		return array_values( $out );
	}
}
