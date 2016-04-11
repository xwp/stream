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
			default:
				$output = apply_filters( 'wp_stream_form_render_field', $output, $field_type, $original_args );
				break;
		}

		return $output;
	}
}
