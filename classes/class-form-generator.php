<?php
namespace WP_Stream;

class Form_Generator {
	public $fields = array();

	public function __construct() {
		$this->fields = array();
	}

	public function add_field( $field_type, $args ) {
		$this->fields[] = array(
			'type' => $field_type,
			'args' => $args,
		);
	}

	public function render_all() {
		$output = '';
		foreach ( $this->fields as $data ) {
			$output .= $this->render_field( $data['type'], $data['args'] );
		}
		return $output;
	}

	public function render_field( $field_type, $original_args ) {

		$args = wp_parse_args( $original_args, array(
			'name'  => '',
			'value' => '',
		) );

		$output = '';
		switch ( $field_type ) {
			case 'select':
				$current_value = $args['value'];

				$output  = sprintf(
					'<select name="%1$s">',
					esc_attr( $args['name'] )
				);

				foreach ( $args['options'] as $value => $label ) {
					$output .= sprintf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $value ),
						checked( $value === $current_value, true, false ),
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
					'placeholder' => '',
				) );
				$output = sprintf(
					'<input type="hidden" class="chosen-select" name="%1$s" value="%2$s" data-values=\'%3$s\' data-placeholder="%4$s"/>',
					esc_attr( $args['name'] ),
					esc_attr( $args['value'] ),
					esc_attr( wp_stream_json_encode( $args['options'] ) ),
					esc_attr( $args['placeholder'] )
				);
				break;
			case 'checkbox':
				$output = sprintf(
					'<input type="checkbox" name="%1$s" value="1" %3$s>%2$s',
					$args['name'],
					$args['text'],
					checked( $args['value'], true, false )
				);
				break;
			default:
				$output = apply_filters( 'wp_stream_form_render_field', $output, $field_type, $original_args );
				break;
		}

		return $output;
	}
}
