<?php

class WP_Stream_Reports_Charts {

	public function __construct() {

		// Load records
		add_filter( 'wp_stream_reports_load_records', array( $this, 'sort_coordinates_by_count' ), 10, 2 );
		add_filter( 'wp_stream_reports_load_records', array( $this, 'limit_coordinates' ), 10, 2 );

		// Make charts
		add_filter( 'wp_stream_reports_make_chart', array( $this, 'pie_chart_coordinates' ), 10, 2 );
		add_filter( 'wp_stream_reports_make_chart', array( $this, 'bar_chart_coordinates' ), 10, 2 );
		add_filter( 'wp_stream_reports_make_chart', array( $this, 'line_chart_coordinates' ), 10, 2 );

		// Chart finalization
		add_filter( 'wp_stream_reports_finalize_chart', array( $this, 'apply_chart_settings' ), 10, 2 );

	}

	public function get_chart_options( $args, $records ) {

		$coordinates = apply_filters( 'wp_stream_reports_make_chart', $records, $args );
		$values      = apply_filters( 'wp_stream_reports_finalize_chart', $coordinates, $args );

		$show_controls = count( $values ) > 1;

		return array(
			'type'       => $args['chart_type'],
			'guidelines' => true,
			'tooltip'    => array(
				'show'   => true,
			),
			'values'     => $values,
			'controls'   => $show_controls,
			'stacked'    => (bool) $args['group'],
			'grouped'    => false,
		);
	}

	/**
	 * Sorts each set of data by the number of records in them
	 */
	public function sort_coordinates_by_count( $records ) {
		$counts = array();
		foreach ( $records as $field => $data ){

			$count = count( $data );
			if ( ! array_key_exists( $count, $counts ) ) {
				$counts[ $count ] = array();
			}

			$counts[ $count ][] = array(
				'key' => $field,
				'data' => $data,
			);
		}

		krsort( $counts );

		$output = array();
		foreach ( $counts as $count => $element ) {

			foreach ( $element as $element_data ) {
				$output[ $element_data['key'] ] = $element_data['data'];
			}
		}

		return $output;
	}

	/**
	 * Merges all records past limit into single record
	 */
	public function limit_coordinates( $records, $args ) {
		$limit = apply_filters( 'wp_stream_reports_record_limit', 10 );
		if ( 0 === $limit ) {
			return $records;
		}

		$top_elements      = array_slice( $records, 0, $limit, true );
		$leftover_elements = array_slice( $records, $limit );

		if ( ! $leftover_elements ) {
			return $top_elements;
		}

		$other_element = array();
		foreach ( $leftover_elements as $data ) {
			$other_element = array_merge( $other_element, $data );
		}

		$top_elements['report-others'] = $other_element;

		return $top_elements;
	}

	public function line_chart_coordinates( $records, $args ) {
		if ( 'line' !== $args['chart_type'] ) {
			return $records;
		}

		$sorted = array();

		// Get date count for each sort
		foreach ( $records as $type => $items ) {
			$sorted[ $type ] = $this->count_by_field( 'created', $items, array( $this, 'collapse_dates' ) );
		}

		$sorted = $this->pad_fields( $sorted );

		foreach ( $sorted as $type => &$items ) {
			ksort( $items );
		}

		$coordinates = array();

		foreach ( $sorted as $line_name => $points ) {
			$line_data = array(
				'key'    => $line_name,
				'values' => array(),
			);

			foreach ( $points as $x => $y ) {
				$line_data['values'][] = array(
					'x' => $x,
					'y' => $y,
				);
			}

			$coordinates[] = $line_data;
		}

		return $coordinates;
	}

	public function pie_chart_coordinates( $records, $args ) {
		if ( 'pie' !== $args['chart_type'] ) {
			return $records;
		}

		$counts = array();

		foreach ( $records as $type => $items ) {
			$counts[] = array(
				'key'   => $type,
				'value' => count( $items ),
			);
		}

		return $counts;
	}

	public function bar_chart_coordinates( $records, $args ) {
		if ( 'multibar' !== $args['chart_type'] ) {
			return $records;
		}

		$sorted = array();

		// Get date count for each sort
		foreach ( $records as $type => $items ) {
			$sorted[ $type ] = $this->count_by_field( 'created', $items, array( $this, 'collapse_dates' ) );
		}

		$sorted = $this->pad_fields( $sorted );

		foreach ( $sorted as $type => &$items ) {
			ksort( $items );
		}

		$coordinates = array();

		foreach ( $sorted as $line_name => $points ) {
			$line_data = array(
				'key'    => $line_name,
				'values' => array(),
			);

			foreach ( $points as $x => $y ) {
				$line_data['values'][] = array(
					'x' => $x,
					'y' => $y,
				);
			}

			$coordinates[] = $line_data;
		}

		return $coordinates;
	}

	/**
	 * Counts the number of objects with similar field properties in an array
	 * @return array
	 */
	public function count_by_field( $field, $records, $callback = '' ) {
		$sorted = $this->group_by_field( $field, $records, $callback );
		$counts = array();

		foreach ( array_keys( $sorted ) as $key ) {
			$counts[ $key ] = count( $sorted[ $key ] );
		}

		return $counts;
	}

	/**
	 * Groups objects with similar field properties into arrays
	 * @return array
	 */
	public function group_by_field( $field, $records, $callback = '' ) {
		$sorted = array();

		foreach ( $records as $record ) {
			$key = $record->$field;

			if ( is_callable( $callback ) ) {
				$key = call_user_func( $callback, $key );
			}

			if ( array_key_exists( $key, $sorted ) && is_array( $sorted[ $key ] ) ) {
				$sorted[ $key ][] = $record;
			} else {
				$sorted[ $key ] = array( $record );
			}
		}

		return $sorted;
	}

	/**
	 * Offsets the record created date by the timezone
	 * @return array
	 */
	public function offset_record_dates( $records ) {
		$offset = get_option( 'gmt_offset' );
		foreach ( $records as $record => $items ) {
			foreach ( $items as $key => $item ) {
				$records[ $record ][ $key ]->created = wp_stream_get_iso_8601_extended_date( strtotime( $item->created ), $offset );
			}
		}
		return $records;
	}

	/**
	 * Adds blank fields for all keys present in any array
	 * @return array
	 */
	public function pad_fields( $records ) {
		$keys = array();

		foreach ( $records as $dataset ) {
			$keys = array_unique( array_merge( $keys, array_keys( $dataset ) ) );
		}

		$new_records = array();

		foreach ( $keys as $key ) {
			foreach ( $records as $data_key => $dataset ) {
				if ( ! array_key_exists( $data_key, $new_records ) ) {
					$new_records[ $data_key ] = array();
				}

				$new_records[ $data_key ][ $key ] = isset( $records[ $data_key ][ $key ] ) ? $records[ $data_key ][ $key ] : 0;
			}
		}

		return $new_records;
	}

	/**
	 * Used to group data points by day
	 */
	protected function collapse_dates( $date ) {
		return strtotime( date( 'Y-m-d', strtotime( $date ) ) );
	}

	/**
	 * Disable coordinate plots that have been disabled by the user
	 */
	public function apply_chart_settings( $coordinates, $args ) {
		foreach ( $coordinates as $key => $dataset ) {
			if ( in_array( $key, $args['disabled'] ) ) {
				$coordinates[ $key ]['disabled'] = true;
			}
		}

		return $coordinates;
	}

}
