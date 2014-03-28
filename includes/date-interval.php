<?php

// Load Carbon to Handle dates much easier
if ( ! class_exists( 'Carbon\Carbon' ) ) {
	require_once WP_STREAM_INC_DIR . 'vendor/Carbon.php';
}

use Carbon\Carbon;

class WP_Stream_Date_Interval {

	/**
	 * Contains an array of all available intervals
	 *
	 * @var array $intervals
	 */
	public $intervals;

	/**
	 * Class constructor
	 */
	public function __construct() {
		// Filter the Predefined list of intervals to make it work
		add_filter( 'wp_stream_predefined_date_intervals', array( $this, 'filter_predefined_intervals' ), 20 );

		// Get all default intervals
		$this->intervals = $this->get_predefined_intervals();
	}

	/**
	 * @return mixed|void
	 */
	public function get_predefined_intervals() {
		$timezone = get_option( 'timezone_string' );

		if ( empty( $timezone ) ) {
			$gmt_offset = (int) get_option( 'gmt_offset' );
			$timezone   = timezone_name_from_abbr( null, $gmt_offset * 3600, true );
			if ( false === $timezone ) {
				$timezone = timezone_name_from_abbr( null, $gmt_offset * 3600, false );
			}
			if ( false === $timezone ) {
				$timezone = null;
			}
		}

		return apply_filters(
			'wp_stream_predefined_date_intervals',
			array(
				'today' => array(
					'label' => esc_html__( 'Today', 'stream' ),
					'start' => Carbon::today( $timezone )->startOfDay(),
					'end'   => Carbon::today( $timezone )->startOfDay(),
				),
				'yesterday' => array(
					'label' => esc_html__( 'Yesterday', 'stream' ),
					'start' => Carbon::today( $timezone )->startOfDay()->subDay(),
					'end'   => Carbon::today( $timezone )->startOfDay()->subSecond(),
				),
				'last-7-days' => array(
					'label' => sprintf( esc_html__( 'Last %d Days', 'stream' ), 7 ),
					'start' => Carbon::today( $timezone )->subDays( 7 ),
					'end'   => Carbon::today( $timezone ),
				),
				'last-14-days' => array(
					'label' => sprintf( esc_html__( 'Last %d Days', 'stream' ), 14 ),
					'start' => Carbon::today( $timezone )->subDays( 14 ),
					'end'   => Carbon::today( $timezone ),
				),
				'last-30-days' => array(
					'label' => sprintf( esc_html__( 'Last %d Days', 'stream' ), 30 ),
					'start' => Carbon::today( $timezone )->subDays( 30 ),
					'end'   => Carbon::today( $timezone ),
				),
				'this-month' => array(
					'label' => esc_html__( 'This Month', 'stream' ),
					'start' => Carbon::today( $timezone )->day( 1 ),
				),
				'last-month' => array(
					'label' => esc_html__( 'Last Month', 'stream' ),
					'start' => Carbon::today( $timezone )->day( 1 )->subMonth(),
					'end'   => Carbon::today( $timezone )->day( 1 )->subSecond(),
				),
				'last-3-months' => array(
					'label' => sprintf( esc_html__( 'Last %d Months', 'stream' ), 3 ),
					'start' => Carbon::today( $timezone )->subMonths( 3 ),
					'end'   => Carbon::today( $timezone ),
				),
				'last-6-months' => array(
					'label' => sprintf( esc_html__( 'Last %d Months', 'stream' ), 6 ),
					'start' => Carbon::today( $timezone )->subMonths( 6 ),
					'end'   => Carbon::today( $timezone ),
				),
				'last-12-months' => array(
					'label' => sprintf( esc_html__( 'Last %d Months', 'stream' ), 12 ),
					'start' => Carbon::today( $timezone )->subMonths( 12 ),
					'end'   => Carbon::today( $timezone ),
				),
				'this-year' => array(
					'label' => esc_html__( 'This Year', 'stream' ),
					'start' => Carbon::today( $timezone )->day( 1 )->month( 1 ),
				),
				'last-year' => array(
					'label' => esc_html__( 'Last Year', 'stream' ),
					'start' => Carbon::today( $timezone )->day( 1 )->month( 1 )->subYear(),
					'end'   => Carbon::today( $timezone )->day( 1 )->month( 1 )->subSecond(),
				),
			),
			$timezone
		);
	}

	/**
	 * Filter the predefined intervals to reflect db oldest value
	 * @param $intervals
	 *
	 * @return array
	 */
	public function filter_predefined_intervals( $intervals ) {
		$query = stream_query(
			array(
				'order'            => 'ASC',
				'orderby'          => 'created',
				'records_per_page' => 1,
				'ignore_context'   => true,
			)
		);

		$first_stream_item = reset( $query );

		if ( false === $first_stream_item ) {
			return array();
		}

		$first_stream_date = \Carbon\Carbon::parse( $first_stream_item->created );

		foreach ( $intervals as $key => $interval ) {
			if ( ! isset( $interval['start'] ) || false === $interval['start'] ) {
				$intervals[ $key ]['start'] = $interval['start'] = $first_stream_date;
			}
			if ( ! isset( $interval['end'] ) || false === $interval['end'] ) {
				$intervals[ $key ]['end'] = $interval['end'] = \Carbon\Carbon::now();
			}
			if ( ! is_a( $interval['start'], '\Carbon\Carbon' ) || ! is_a( $interval['end'], '\Carbon\Carbon' ) ) {
				unset( $intervals[ $key ] );
				continue;
			}
		}

		return $intervals;
	}

}
