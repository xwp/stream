<?php

function wp_stream_reports_selector( $data_types, $args, $class ) {
	$options  = array();
	foreach ( $data_types as $key => $item ) {
		$selected = false;

		if ( isset( $item['connector'] ) && $item['connector'] == $args['connector_id'] && isset( $item['context'] ) && $item['context'] == null ) {
			$selected = true;
		} else if ( isset( $item['action'] ) && $item['action'] == $args['action_id'] ) {
			$selected = true;
		}

		$option_args = array(
			'value'     => $key,
			'label'     => isset( $item['label'] ) ? $item['label'] : null,
			'selected'  => selected( $selected, true, false ),
			'disabled'  => isset( $item['disabled'] ) ? $item['disabled'] : null,
			'class'     => isset( $item['children'] ) ? 'level-1' : null,

			'connector' => isset( $item['connector'] ) ? $item['connector'] : null,
			'context'   => isset( $item['context'] ) ? $item['context'] : null,
			'action'    => isset( $item['action'] ) ? $item['action'] : null,
			'blog'      => isset( $item['blog'] ) ? $item['blog'] : null,
		);
		$options[] = wp_stream_reports_filter_option( $option_args );

		if ( isset( $item['children'] ) ) {
			foreach ( $item['children'] as $child_value => $child_item ) {
				$selected = false;
				if ( isset( $child_item['connector'] ) && $child_item['connector'] == $args['connector_id'] && isset( $child_item['context'] ) && $child_item['context'] == $args['context_id'] ) {
					$selected = true;
				} else if ( isset( $child_item['blog'] ) && $child_item['blog'] == $args['blog_id'] ) {
					$selected = true;
				}

				$option_args  = array(
					'value'     => $child_value,
					'label'     => isset( $child_item['label'] ) ? $child_item['label'] : null,
					'selected'  => selected( $selected, true, false ),
					'disabled'  => isset( $child_item['disabled'] ) ? $child_item['disabled'] : null,
					'class'     => 'level-2',
					
					'connector' => isset( $child_item['connector'] ) ? $child_item['connector'] : null,
					'context'   => isset( $child_item['context'] ) ? $child_item['context'] : null,
					'action'    => isset( $child_item['action'] ) ? $child_item['action'] : null,			
					'blog'      => isset( $child_item['blog'] ) ? $child_item['blog'] : null,
				);
				$options[] = wp_stream_reports_filter_option( $option_args );
			}
		}
	}
	$out = sprintf(
		'<select class="%s">%s</select>',
		$class,
		implode( '', $options )
	);

	echo $out;
}

function wp_stream_reports_filter_option( $args ) {
		$defaults = array(
			'value'     => null,
			'selected'  => null,
			'disabled'  => null,
			'class'     => null,
			'label'     => null,

			'connector' => null,
			'context'   => null,
			'action'    => null,
			'blog'      => null,
		);
		
		$args = wp_parse_args( $args, $defaults );
		return sprintf(
			'<option value="%s" %s %s %s %s %s %s class="%s">%s</option>',
			esc_attr( $args['value'] ),
			$args['selected'],
			$args['disabled'],
			$args['connector'] ? sprintf( 'data-connector="%s"', esc_attr( $args['connector'] ) ) : null,
			$args['context'] ? sprintf( 'data-context="%s"', esc_attr( $args['context'] ) ) : null,
			$args['action'] ? sprintf( 'data-action="%s"', esc_attr( $args['action'] ) ) : null,
			$args['blog'] ? sprintf( 'data-blog="%s"', esc_attr( $args['blog'] ) ) : null,
			$args['class'] ? esc_attr( $args['class'] ) : null,
			esc_html( $args['label'] )
		);
}

