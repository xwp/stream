<?php
/**
 * Generates an WP Admin form.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Form_Generator
 */
class Form_Generator {

	/**
	 * List of all registered fields.
	 *
	 * @var array
	 */
	public $fields = array();

	/**
	 * Adds a new field to the form.
	 *
	 * @param string $field_type The type of field being added.
	 * @param array  $args Options for the field. See render_field().
	 * @return void
	 */
	public function add_field( $field_type, $args ) {
		$this->fields[] = array(
			'type' => $field_type,
			'args' => $args,
		);
	}

	/**
	 * Renders all fields currently registered.
	 *
	 * @return string
	 */
	public function render_fields() {
		$output = '';
		foreach ( $this->fields as $data ) {
			$output .= $this->render_field( $data['type'], $data['args'] );
		}
		return $output;
	}

	/**
	 * Renders all fields currently registered as a table.
	 *
	 * @return string
	 */
	public function render_fields_table() {
		$output = '<table class="form-table">';
		foreach ( $this->fields as $data ) {
			$title = ( array_key_exists( 'title', $data['args'] ) ) ? $data['args']['title'] : '';

			$output .= '<tr><th>' . $title . '</th><td>';
			$output .= $this->render_field( $data['type'], $data['args'] );
			$output .= '</td><tr>';
		}
		$output .= '</table>';
		return $output;
	}

	/**
	 * Renders a single field.
	 *
	 * @param string $field_type The type of field being rendered.
	 * @param array  $args The options for the field type.
	 *
	 * @return string
	 */
	public function render_field( $field_type, $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'name'        => '',
				'value'       => '',
				'options'     => array(),
				'description' => '',
				'classes'     => '',
				'data'        => array(),
				'multiple'    => false,
			)
		);

		$output = '';
		switch ( $field_type ) {
			case 'text':
				$output = sprintf(
					'<input type="text" name="%1$s" id="%1$s" class="%2$s" value="%3$s" />',
					esc_attr( $args['name'] ),
					esc_attr( $args['classes'] ),
					esc_attr( $args['value'] )
				);
				break;
			case 'hidden':
				$output = sprintf(
					'<input type="hidden" name="%1$s" id="%1$s" class="%2$s" value="%3$s" />',
					esc_attr( $args['name'] ),
					esc_attr( $args['classes'] ),
					esc_attr( $args['value'] )
				);
				break;
			case 'select':
				$current_value = $args['value'];

				$output = sprintf(
					'<select name="%1$s" class="%2$s" id="%1$s">',
					esc_attr( $args['name'] ),
					esc_attr( $args['classes'] )
				);

				foreach ( $args['options'] as $value => $label ) {
					$output .= sprintf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $value ),
						selected( $value === $current_value, true, false ),
						esc_html( $label )
					);
				}
				$output .= '</select>';
				break;
			case 'select2':
				$values = array();

				$multiple = ( $args['multiple'] ) ? 'multiple ' : '';
				$output   = sprintf(
					'<select name="%1$s" id="%1$s" class="select2-select %2$s" %3$s%4$s>',
					esc_attr( $args['name'] ),
					esc_attr( $args['classes'] ),
					$this->prepare_data_attributes_string( $args['data'] ),
					$multiple
				);

				if ( array_key_exists( 'placeholder', $args['data'] ) && ! $multiple ) {
					$output .= '<option value=""></option>';
				}

				foreach ( $args['options'] as $parent ) {
					$parent = wp_parse_args(
						$parent,
						array(
							'value'    => '',
							'text'     => '',
							'children' => array(),
						)
					);
					if ( empty( $parent['value'] ) ) {
						continue;
					}
					if ( is_array( $args['value'] ) ) {
						$selected = selected( in_array( $parent['value'], $args['value'], true ), true, false );
					} else {
						$selected = selected( $args['value'], $parent['value'], false );
					}
					$output  .= sprintf(
						'<option class="parent" value="%1$s" %3$s>%2$s</option>',
						$parent['value'],
						$parent['text'],
						$selected
					);
					$values[] = $parent['value'];
					if ( ! empty( $parent['children'] ) ) {
						foreach ( $parent['children'] as $child ) {
							$output  .= sprintf(
								'<option class="child" value="%1$s" %3$s>%2$s</option>',
								$child['value'],
								$child['text'],
								selected( $args['value'], $child['value'], false )
							);
							$values[] = $child['value'];
						}
						$output .= '</optgroup>';
					}
				}

				$selected_values = explode( ',', $args['value'] );
				foreach ( $selected_values as $selected_value ) {
					if ( ! empty( $selected_value ) && ! in_array( $selected_value, array_map( 'strval', $values ), true ) ) {
						$output .= sprintf(
							'<option value="%1$s" %2$s>%1$s</option>',
							$selected_value,
							selected( true, true, false )
						);
					}
				}

				$output .= '</select>';
				break;
			case 'checkbox':
				$output = sprintf(
					'<input type="checkbox" name="%1$s" id="%1$s" value="1" %3$s>%2$s',
					$args['name'],
					$args['text'],
					checked( $args['value'], true, false )
				);
				break;
			default:
				$output = apply_filters( 'wp_stream_form_render_field', $output, $field_type, $args );
				break;
		}

		$output .= ! empty( $args['description'] ) ? sprintf( '<p class="description">%s</p>', $args['description'] ) : null;

		return $output;
	}

	/**
	 * Prepares string with HTML data attributes
	 *
	 * @param array $data List of key/value data pairs to prepare.
	 * @return string
	 */
	public function prepare_data_attributes_string( $data ) {
		$output = '';
		foreach ( $data as $key => $value ) {
			$key     = 'data-' . esc_attr( $key );
			$output .= $key . '="' . esc_attr( $value ) . '" ';
		}
		return $output;
	}
}
