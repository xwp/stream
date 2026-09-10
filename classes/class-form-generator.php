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
	 */
	public array $fields = array();

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
				$placeholder = '';
				if ( ! empty( $args['data']['placeholder'] ) ) {
					$placeholder = sprintf( ' placeholder="%s"', esc_attr( $args['data']['placeholder'] ) );
				}
				$output = sprintf(
					'<input type="text" name="%1$s" id="%1$s" class="%2$s" value="%3$s"%4$s />',
					esc_attr( $args['name'] ),
					esc_attr( $args['classes'] ),
					esc_attr( $args['value'] ),
					$placeholder
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
			case 'grouped_select':
				$values = array();

				$multiple = ( $args['multiple'] ) ? ' multiple' : '';
				$label    = '';
				if ( ! empty( $args['data']['placeholder'] ) ) {
					$label = sprintf( ' aria-label="%s"', esc_attr( $args['data']['placeholder'] ) );
				}
				$output = sprintf(
					'<select name="%1$s" id="%1$s" class="%2$s" %3$s%4$s%5$s>',
					esc_attr( $args['name'] ),
					esc_attr( $args['classes'] ),
					$this->prepare_data_attributes_string( $args['data'] ), // The data attributes are escaped in the function.
					$multiple,
					$label
				);

				if ( array_key_exists( 'placeholder', $args['data'] ) && ! $multiple ) {
					$output .= sprintf(
						'<option value="">%s</option>',
						esc_html( $args['data']['placeholder'] )
					);
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
					if ( '' === (string) $parent['value'] && empty( $parent['children'] ) ) {
						continue;
					}

					// Group header (no value of its own): render children inside an optgroup.
					if ( '' === (string) $parent['value'] ) {
						$output .= sprintf(
							'<optgroup label="%s">',
							esc_attr( $parent['text'] )
						);
						foreach ( $parent['children'] as $child ) {
							$output  .= $this->render_select_option( $child, $args['value'] );
							$values[] = $child['value'];
						}
						$output .= '</optgroup>';
						continue;
					}

					// Selectable parent option followed by its children, matching the previous flat markup.
					$output  .= $this->render_select_option( $parent, $args['value'], 'parent' );
					$values[] = $parent['value'];
					foreach ( $parent['children'] as $child ) {
						$output  .= $this->render_select_option( $child, $args['value'], 'child' );
						$values[] = $child['value'];
					}
				}

				$selected_values = is_array( $args['value'] ) ? $args['value'] : explode( ',', (string) $args['value'] );
				foreach ( $selected_values as $selected_value ) {
					if ( ! empty( $selected_value ) && ! in_array( (string) $selected_value, array_map( 'strval', $values ), true ) ) {
						$output .= sprintf(
							'<option value="%1$s" selected="selected">%2$s</option>',
							esc_attr( $selected_value ),
							esc_html( $selected_value )
						);
					}
				}

				$output .= '</select>';
				break;
			case 'user_combobox':
				$output = $this->render_user_combobox( $args );
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
	 * Accessible user search combobox (hidden value + search input + listbox).
	 *
	 * Optional `role_options` render as a "Roles" group inside the listbox
	 * (single control), mirroring the previous Select2 dropdown's grouping.
	 * The hidden input carries the submitted value: a user id or role slug.
	 *
	 * @param array $args Field args.
	 * @return string Markup.
	 */
	private function render_user_combobox( $args ) {
		$placeholder = '';
		if ( ! empty( $args['data']['placeholder'] ) ) {
			$placeholder = (string) $args['data']['placeholder'];
		}

		$selected_label = isset( $args['selected_label'] ) ? (string) $args['selected_label'] : '';
		$search_label   = $placeholder ? $placeholder : __( 'Search users', 'stream' );
		$value          = (string) $args['value'];
		$hidden_classes = trim( 'stream-user-combobox__value ' . (string) $args['classes'] );

		$role_options_attr = '';
		if ( ! empty( $args['role_options'] ) && is_array( $args['role_options'] ) ) {
			$role_data = array();
			foreach ( $args['role_options'] as $role_option ) {
				$role_option = wp_parse_args(
					$role_option,
					array(
						'value' => '',
						'text'  => '',
					)
				);
				if ( '' === (string) $role_option['value'] ) {
						continue;
				}
				$role_data[] = array(
					'value' => (string) $role_option['value'],
					'label' => (string) $role_option['text'],
				);
			}
			$role_options_attr = sprintf(
				' data-role-options="%s"',
				esc_attr( wp_json_encode( $role_data ) )
			);
		}

		$hidden_id = '';
		if ( ! empty( $args['id'] ) ) {
			$hidden_id = (string) $args['id'];
		} elseif ( false === strpos( (string) $args['name'], '[' ) ) {
			$hidden_id = (string) $args['name'];
		}
		$id_attr = '' !== $hidden_id ? sprintf( ' id="%s"', esc_attr( $hidden_id ) ) : '';

		return sprintf(
			'<div class="stream-user-combobox" data-placeholder="%1$s" data-selected-label="%2$s"%3$s><input type="search" class="stream-user-combobox__input" role="combobox" aria-expanded="false" aria-autocomplete="list" autocomplete="off" placeholder="%4$s" aria-label="%4$s" /><input type="hidden" name="%5$s"%6$s class="%7$s" value="%8$s" /><ul class="stream-user-combobox__listbox" role="listbox" hidden></ul></div>',
			esc_attr( $placeholder ),
			esc_attr( $selected_label ),
			$role_options_attr,
			esc_attr( $search_label ),
			esc_attr( $args['name'] ),
			$id_attr,
			esc_attr( $hidden_classes ),
			esc_attr( $value )
		);
	}

	/**
	 * Render a single <option> for a native select.
	 *
	 * @param array        $option  Option array (`value`, `text`).
	 * @param string|array $current Currently selected value(s).
	 * @param string       $css_class Optional option class ('parent' or 'child').
	 * @return string
	 */
	private function render_select_option( $option, $current, $css_class = '' ) {
		$option = wp_parse_args(
			$option,
			array(
				'value' => '',
				'text'  => '',
			)
		);

		if ( is_array( $current ) ) {
			$selected = selected( in_array( (string) $option['value'], array_map( 'strval', $current ), true ), true, false );
		} else {
			$selected = selected( (string) $current, (string) $option['value'], false );
		}

		$class_attr = '' !== $css_class ? sprintf( ' class="%s"', esc_attr( $css_class ) ) : '';

		return sprintf(
			'<option%1$s value="%2$s" %3$s>%4$s</option>',
			$class_attr,
			esc_attr( $option['value'] ),
			$selected,
			esc_html( $option['text'] )
		);
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
