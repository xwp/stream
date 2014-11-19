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
		);
		$options[] = wp_stream_reports_filter_option( $option_args );

		if ( isset( $item['children'] ) ) {
			foreach ( $item['children'] as $child_value => $child_item ) {
				$selected = false;
				if ( isset( $child_item['connector'] ) && $child_item['connector'] == $args['connector_id'] && isset( $child_item['context'] ) && $child_item['context'] == $args['context_id'] ) {
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
				);
				$options[] = wp_stream_reports_filter_option( $option_args );
			}
		}
	}

	$allowed_html = array(
		'option' => array(
			'value'          => array(),
			'selected'       => array(),
			'disabled'       => array(),
			'class'          => array(),
			'data-connector' => array(),
			'data-context'   => array(),
			'data-action'    => array(),
		),
	);

	printf(
		'<select class="%s">%s</select>',
		esc_attr( $class ),
		wp_kses( implode( '', $options ), $allowed_html )
	);
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
	);

	$args = wp_parse_args( $args, $defaults );
	return sprintf(
		'<option value="%s" %s %s %s %s %s class="%s">%s</option>',
		esc_attr( $args['value'] ),
		$args['selected'],
		$args['disabled'],
		$args['connector'] ? sprintf( 'data-connector="%s"', esc_attr( $args['connector'] ) ) : null,
		$args['context'] ? sprintf( 'data-context="%s"', esc_attr( $args['context'] ) ) : null,
		$args['action'] ? sprintf( 'data-action="%s"', esc_attr( $args['action'] ) ) : null,
		$args['class'] ? esc_attr( $args['class'] ) : null,
		esc_html( $args['label'] )
	);
}

function wp_stream_reports_intervals_html() {
	$date = WP_Stream_Reports_Date_Interval::get_instance();

	// Default interval
	$default = array(
		'key'   => 'all-time',
		'start' => '',
		'end'   => '',
	);
	$user_interval     = WP_Stream_Reports_Settings::get_user_options( 'interval', $default );
	$save_interval_url = add_query_arg(
		array_merge(
			array(
				'action' => 'wp_stream_reports_save_interval',
			),
			WP_Stream_Reports::$nonce
		),
		admin_url( 'admin-ajax.php' )
	);

	include WP_STREAM_REPORTS_VIEW_DIR . 'intervals.php';
}
