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
	 * @return void
	 */
	public function render_fields() {
		foreach ( $this->fields as $data ) {
			$this->render_field( $data['type'], $data['args'] );
		}
	}

	/**
	 * Renders all fields currently registered as a table.
	 *
	 * @return void
	 */
	public function render_fields_table() {
		echo '<table class="form-table">';
		foreach ( $this->fields as $data ) {
			$title = ( array_key_exists( 'title', $data['args'] ) ) ? $data['args']['title'] : '';

			printf( '<tr><th>%s</th><td>', esc_html( $title ) );
			$this->render_field( $data['type'], $data['args'] );
			echo '</td><tr>';
		}
		echo '</table>';
	}

	/**
	 * Renders or returns a single field.
	 *
	 * @param string $field_type  The type of field being rendered.
	 * @param array  $args        The options for the field type.
	 * @param bool   $echo_output Whether to echo the output or return it.
	 *
	 * @return string|void
	 */
	public function render_field( $field_type, $args, $echo_output = true ) {
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

				$multiple = ( $args['multiple'] ) ? ' multiple' : '';
				$output   = sprintf(
					'<select name="%1$s" id="%1$s" class="select2-select %2$s" %3$s%4$s>',
					esc_attr( $args['name'] ),
					esc_attr( $args['classes'] ),
					$this->prepare_data_attributes_string( $args['data'] ), // The data attributes are escaped in the function.
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
						'<option class="parent" value="%1$s" %2$s>%3$s</option>',
						esc_attr( $parent['value'] ),
						$selected,
						esc_html( $parent['text'] )
					);
					$values[] = $parent['value'];
					if ( ! empty( $parent['children'] ) ) {
						foreach ( $parent['children'] as $child ) {
							$output  .= sprintf(
								'<option class="child" value="%1$s" %2$s>%3$s</option>',
								esc_attr( $child['value'] ),
								selected( $args['value'], $child['value'], false ),
								esc_html( $child['text'] )
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
							'<option value="%1$s" selected="selected">%2$s</option>',
							esc_attr( $selected_value ),
							esc_html( $selected_value )
						);
					}
				}

				$output .= '</select>';
				break;
			case 'checkbox':
				$output = sprintf(
					'<input type="checkbox" name="%1$s" id="%1$s" value="1" %2$s>%3$s',
					esc_attr( $args['name'] ),
					checked( $args['value'], true, false ),
					esc_html( $args['text'] )
				);
				break;
			default:
				$output = apply_filters( 'wp_stream_form_render_field', $output, $field_type, $args );
				break;
		}

		if ( ! empty( $args['description'] ) ) {
			$output .= sprintf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}

		if ( ! $echo_output ) {
			return $output;
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			$output .= sprintf(
				'data-%s="%s" ',
				esc_attr( $key ),
				esc_attr( $value )
			);
		}
		return $output;
	}
}
