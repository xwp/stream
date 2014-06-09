<?php

function wp_stream_reports_selector( $data_types, $args ) {

	$selected = '';

	$options = array();
	foreach ( $data_types as $key => $item ) {

		$option_args = array(
			'value'     => $key,
			'selected'  => selected( $key, $selected, false ),
			'disabled'  => isset( $item['disabled'] ) ? $item['disabled'] : null,
			'group'     => isset( $item['children'] ) ? $key : null,
			'class'     => isset( $item['children'] ) ? 'level-1' : null,
			'label'     => isset( $item['label'] ) ? $item['label'] : null,
			'type'      => isset( $item['type'] ) ? $item['type'] : null,
		);
		$options[] = wp_stream_reports_filter_option( $option_args );

		if ( isset( $item['children'] ) ) {
			foreach ( $item['children'] as $child_value => $child_item ) {
				$option_args  = array(
					'value'     => $child_value,
					'selected'  => selected( $child_value, $selected, false ),
					'disabled'  => isset( $child_item['disabled'] ) ? $child_item['disabled'] : null,
					'group'     => $key,
					'class'     => 'level-2',
					'label'     => isset( $child_item['label'] ) ? '- ' . $child_item['label'] : null,
					'type'      => isset( $child_item['type'] ) ? $child_item['type'] : null,
				);
				$options[] = wp_stream_reports_filter_option( $option_args );
			}
		}
	}
	$out = sprintf(
		'<select class="chart-option chart-dataset">%s</select>',
		implode( '', $options )
	);

	echo $out;
}

function wp_stream_reports_filter_option( $args ) {
		$defaults = array(
			'value'     => null,
			'selected'  => null,
			'disabled'  => null,
			'group'     => null,
			'type'      => null,
			'class'     => null,
			'label'     => null,
		);
		
		$args = wp_parse_args( $args, $defaults );
		return sprintf(
			'<option value="%s" %s %s %s %s class="%s">%s</option>',
			esc_attr( $args['value'] ),
			$args['selected'],
			$args['disabled'],
			$args['group'] ? sprintf( 'data-group="%s"', esc_attr( $args['group'] ) ) : null,
			$args['type'] ? sprintf( 'data-type="%s"', esc_attr( $args['type'] ) ) : null,
			$args['class'] ? esc_attr( $args['class'] ) : null,
			esc_html( $args['label'] )
		);
}

