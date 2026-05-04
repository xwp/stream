<?php
/**
 * Ability: stream/create-exclusion-rule — append a record exclusion rule.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Create_Exclusion_Rule
 */
class Ability_Create_Exclusion_Rule extends Ability {

	/**
	 * Columns Stream stores in the exclude_rules option (parallel arrays).
	 *
	 * @const array
	 */
	const RULE_COLUMNS = array(
		'author_or_role',
		'connector',
		'context',
		'action',
		'ip_address',
	);

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/create-exclusion-rule';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Create Stream Exclusion Rule', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Append an exclusion rule that prevents matching activity from being recorded. Provide at least one filter (author_or_role, connector, context, action, or ip_address). All filters in a rule must match for the rule to apply.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => false,
			'instructions' => __( 'Add a rule that prevents matching activity from ever being logged. Confirm intent with the user before calling: excluded events cannot be recovered later. Validate filter values with stream/get-connectors when in doubt.', 'stream' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'minProperties'        => 1,
			'properties'           => array(
				'author_or_role' => array(
					'type'        => 'string',
					'description' => 'User ID or role slug. Empty string matches anything.',
				),
				'connector'      => array(
					'type'        => 'string',
					'description' => 'Connector slug (e.g. "posts", "users").',
				),
				'context'        => array(
					'type'        => 'string',
					'description' => 'Context slug under the connector.',
				),
				'action'         => array(
					'type'        => 'string',
					'description' => 'Action slug (e.g. "updated", "deleted").',
				),
				'ip_address'     => array(
					'type'        => 'string',
					'description' => 'Client IP address (IPv4 or IPv6).',
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
				'index' => array(
					'type'        => 'integer',
					'description' => 'Zero-based position of the new rule in the exclude_rules option.',
				),
				'rule'  => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'author_or_role' => array( 'type' => array( 'string', 'null' ) ),
						'connector'      => array( 'type' => array( 'string', 'null' ) ),
						'context'        => array( 'type' => array( 'string', 'null' ) ),
						'action'         => array( 'type' => array( 'string', 'null' ) ),
						'ip_address'     => array( 'type' => array( 'string', 'null' ) ),
					),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( $input = null ) {
		$option_key = $this->plugin->settings->option_key;
		$options    = (array) get_option( $option_key, array() );

		$rules = isset( $options['exclude_rules'] ) && is_array( $options['exclude_rules'] )
			? $options['exclude_rules']
			: array();

		// Ensure all parallel-array columns exist.
		if ( ! isset( $rules['exclude_row'] ) || ! is_array( $rules['exclude_row'] ) ) {
			$rules['exclude_row'] = array();
		}
		foreach ( self::RULE_COLUMNS as $column ) {
			if ( ! isset( $rules[ $column ] ) || ! is_array( $rules[ $column ] ) ) {
				$rules[ $column ] = array();
			}
		}

		$index                          = count( $rules['exclude_row'] );
		$rules['exclude_row'][ $index ] = '';

		$rule = array();
		foreach ( self::RULE_COLUMNS as $column ) {
			$value                      = isset( $input[ $column ] ) ? (string) $input[ $column ] : '';
			$rules[ $column ][ $index ] = $value;
			$rule[ $column ]            = '' === $value ? null : $value;
		}

		$options['exclude_rules'] = $rules;
		update_option( $option_key, $options );

		// Refresh in-memory copy.
		$this->plugin->settings->options = $options;

		return array(
			'index' => $index,
			'rule'  => $rule,
		);
	}
}
