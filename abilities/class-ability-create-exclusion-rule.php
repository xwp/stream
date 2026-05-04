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
					'description' => 'User ID or role slug.',
					'maxLength'   => 100,
				),
				'connector'      => array(
					'type'        => 'string',
					'description' => 'Connector slug (e.g. "posts", "users"). Validated against registered connectors.',
					'maxLength'   => 100,
				),
				'context'        => array(
					'type'        => 'string',
					'description' => 'Context slug under the connector.',
					'maxLength'   => 100,
				),
				'action'         => array(
					'type'        => 'string',
					'description' => 'Action slug (e.g. "updated", "deleted").',
					'maxLength'   => 100,
				),
				'ip_address'     => array(
					'type'        => 'string',
					'description' => 'Client IP address (IPv4 or IPv6).',
					'format'      => 'ip',
					'maxLength'   => 45,
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
		$input = (array) $input;

		// Sanitize each incoming value; reject all-empty payloads so we never
		// store a no-op rule that would silently match nothing (or, depending
		// on Log::record_excluded() semantics, everything).
		$sanitized = array();
		foreach ( self::RULE_COLUMNS as $column ) {
			$raw                  = isset( $input[ $column ] ) ? (string) $input[ $column ] : '';
			$sanitized[ $column ] = sanitize_text_field( $raw );
		}

		// JSON Schema's format:ip is a hint, not enforced by rest_validate_value_from_schema.
		// Validate explicitly so bogus IPs never reach storage.
		if ( '' !== $sanitized['ip_address'] && ! filter_var( $sanitized['ip_address'], FILTER_VALIDATE_IP ) ) {
			return new \WP_Error(
				'stream_invalid_ip',
				__( 'ip_address must be a valid IPv4 or IPv6 address.', 'stream' ),
				array( 'status' => 400 )
			);
		}

		// Validate connector against registered connectors when provided.
		if ( '' !== $sanitized['connector'] ) {
			$known = isset( $this->plugin->connectors->connectors )
				? array_keys( (array) $this->plugin->connectors->connectors )
				: array();
			if ( ! empty( $known ) && ! in_array( $sanitized['connector'], $known, true ) ) {
				return new \WP_Error(
					'stream_unknown_connector',
					/* translators: %s: connector slug */
					sprintf( __( 'Unknown connector: %s. Use stream/get-connectors to list valid slugs.', 'stream' ), $sanitized['connector'] ),
					array( 'status' => 400 )
				);
			}
		}

		// Reject payloads where every filter is empty after sanitization. The
		// JSON Schema minProperties:1 only ensures *a* key was supplied; this
		// guards against {"author_or_role": ""}.
		$has_value = false;
		foreach ( $sanitized as $value ) {
			if ( '' !== $value ) {
				$has_value = true;
				break;
			}
		}
		if ( ! $has_value ) {
			return new \WP_Error(
				'stream_empty_rule',
				__( 'At least one filter value must be non-empty.', 'stream' ),
				array( 'status' => 400 )
			);
		}

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
			$value                      = $sanitized[ $column ];
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
