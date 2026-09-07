<?php
/**
 * Sanitizes posted Stream settings values by field type.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Settings_Sanitizer
 */
class Settings_Sanitizer {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * @param array $input Raw posted values keyed by `{section}_{name}`.
	 * @return array
	 */
	public function sanitize_settings_for_save( $input ) {
		return $this->sanitize_settings( $input, $this->plugin->settings->registry->get_fields() );
	}

	/**
	 * Sanitize posted settings using field definitions.
	 *
	 * Empty or missing keys are skipped. Output keys use `{section}_{name}`.
	 *
	 * @param array $input  Raw posted values keyed by `{section}_{name}`.
	 * @param array $fields Section/field definitions from the registry.
	 * @return array
	 */
	public function sanitize_settings( $input, $fields ) {
		$output = array();

		foreach ( $fields as $section => $data ) {
			if ( empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
				continue;
			}

			foreach ( $data['fields'] as $field ) {
				$type = ! empty( $field['type'] ) ? $field['type'] : null;
				$name = ! empty( $field['name'] ) ? sprintf( '%s_%s', $section, $field['name'] ) : null;

				if ( empty( $type ) || ! isset( $input[ $name ] ) || '' === $input[ $name ] ) {
					continue;
				}

				$output[ $name ] = $this->sanitize_setting_by_field_type( $input[ $name ], $type );
			}
		}

		return $output;
	}

	/**
	 * Sanitize a setting value based on the field type.
	 *
	 * @param mixed  $value      The value to be sanitized.
	 * @param string $field_type The type of field.
	 * @return mixed The sanitized value.
	 */
	public function sanitize_setting_by_field_type( $value, $field_type ) {
		switch ( $field_type ) {
			case 'number':
				$sanitized_value = is_numeric( $value ) ? intval( trim( $value ) ) : '';
				break;
			case 'checkbox':
				$sanitized_value = is_numeric( $value ) ? absint( trim( $value ) ) : '';
				break;
			default:
				if ( is_array( $value ) ) {
					$sanitized_value = $value;

					array_walk_recursive(
						$sanitized_value,
						array( self::class, 'sanitize_walk_value' )
					);
				} else {
					$sanitized_value = sanitize_text_field( trim( $value ) );
				}
		}

		return $sanitized_value;
	}

	/**
	 * Sanitize a single nested value during array_walk_recursive.
	 *
	 * @param mixed $value Value passed by reference from array_walk_recursive.
	 * @param mixed $key   Array key (unused; provided by array_walk_recursive).
	 */
	public static function sanitize_walk_value( &$value, $key = null ) {
		unset( $key );
		$value = sanitize_text_field( trim( $value ) );
	}
}
