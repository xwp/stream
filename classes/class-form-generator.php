<?php
namespace WP_Stream;

class Form_Generator {

	/**
	 * List of all registered fields.
	 *
	 * @var array
	 */
	public $fields = array();

	/**
	 * Class constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->fields = array();
	}

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
	public function render_all() {
		$output = '';
		foreach ( $this->fields as $data ) {
			$output .= $this->render_field( $data['type'], $data['args'] );
		}
		return $output;
	}

	public function render_as_table() {
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
	 * @param array  $original_args The options for the field type.
	 */
	public function render_field( $field_type, $original_args ) {
		$args = wp_parse_args( $original_args, array(
			'name'        => '',
			'value'       => '',
			'description' => '',
			'classes'     => '',
		) );

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
			case 'select':
				$args = wp_parse_args( $original_args, array(
					'name'        => '',
					'value'       => '',
					'options'     => array(),
					'classes'     => '',
					'data'        => array(),
				) );

				$current_value = $args['value'];

				$output  = sprintf(
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
				$args = wp_parse_args( $original_args, array(
					'name'        => '',
					'value'       => '',
					'options'     => array(),
					'classes'     => '',
					'data'        => array(),
				) );

				$output = sprintf(
					'<select name="%1$s" id="%1$s" class="select2-select %2$s" %3$s/>',
					esc_attr( $args['name'] ),
					esc_attr( $args['classes'] ),
					$this->prepare_data_string( $args['data'] )
				);

				foreach ( $args['options'] as $parent ) {
					$parent = wp_parse_args( $parent, array(
						'value'    => '',
						'text'     => '',
						'children' => array(),
					) );
					if ( ! empty( $parent['children'] ) ) {
						$output .= sprintf(
							'<optgroup label="%1$s" value="%2$s">',
							$parent['text'],
							$parent['value']
						);
						foreach ( $parent['children'] as $child ) {
							$output .= sprintf(
								'<option value="%1$s" %3$s>%2$s</option>',
								$child['value'],
								$child['text'],
								selected( $args['value'], $child['value'], false )
							);
						}
						$output .= '</optgroup>';
					} else {
						$output .= sprintf(
							'<option value="%1$s" %3$s>%2$s</option>',
							$parent['value'],
							$parent['text'],
							selected( $args['value'], $parent['value'], false )
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
				$output = apply_filters( 'wp_stream_form_render_field', $output, $field_type, $original_args );
				break;
		}

		$output .= ! empty( $args['description'] ) ? wp_kses_post( sprintf( '<p class="description">%s</p>', $args['description'] ) ) : null;

		return $output;
	}

	public function prepare_data_string( $data ) {
		$output = '';
		foreach ( $data as $key => $value ) {
			$key = 'data-' . esc_attr( $key );
			$output .= $key . '="' . esc_attr( $value ) . '" ';
		}
		return $output;
	}
}
