<?php
use Carbon\Carbon;

class WP_Stream_Report_Date_Interval {

	private $args = array(
		'start' => false,
		'end' => false,
	);

	public function __construct( $args = array() ){

		$this->args = (object) wp_parse_args( $this->args, $args );

		$go = $this->get_predefined_intervals();
	}

	public function get_predefined_intervals(){
		return apply_filters(
			'stream-report-predefined-intervals',
			array(
				'today' => array(
					'label' => __( 'Today', 'stream-report' ),
					'start' => Carbon::today(),
				),
				'yesterday' => array(
					'label' => __( 'Yesterday', 'stream-report' ),
					'depends' => 'today',
					'start' => Carbon::today()->subDay(),
					'end' => Carbon::today()->subSecond(),
				),
				'last-7-days' => array(
					'label' => sprintf( __( 'Last %d Days', 'stream-report' ), 7 ),
					'depends' => 'yesterday',
					'start' => Carbon::today()->subDays( 7 ),
					'end' => Carbon::today(),
				),
				'last-14-days' => array(
					'label' => sprintf( __( 'Last %d Days', 'stream-report' ), 14 ),
					'depends' => 'last-7-days',
					'start' => Carbon::today()->subDays( 14 ),
					'end' => Carbon::today(),
				),
				'last-30-days' => array(
					'label' => sprintf( __( 'Last %d Days', 'stream-report' ), 30 ),
					'depends' => 'last-14-days',
					'start' => Carbon::today()->subDays( 30 ),
					'end' => Carbon::today(),
				),
				'this-month' => array(
					'label' => __( 'This Month', 'stream-report' ),
					'start' => Carbon::today()->day( 1 ),
				),
				'last-month' => array(
					'label' => __( 'Last Month', 'stream-report' ),
					'depends' => 'this-month',
					'start' => Carbon::today()->day( 1 )->subMonth(),
					'end' => Carbon::today()->day( 1 )->subSecond(),
				),
				'last-3-months' => array(
					'label' => sprintf( __( 'Last %d Months', 'stream-report' ), 3 ),
					'depends' => 'last-month',
					'start' => Carbon::today()->subMonths( 3 ),
					'end' => Carbon::today(),
				),
				'last-6-months' => array(
					'label' => sprintf( __( 'Last %d Months', 'stream-report' ), 6 ),
					'depends' => 'last-3-months',
					'start' => Carbon::today()->subMonths( 6 ),
					'end' => Carbon::today(),
				),
				'last-12-months' => array(
					'label' => sprintf( __( 'Last %d Months', 'stream-report' ), 12 ),
					'depends' => 'last-6-months',
					'start' => Carbon::today()->subMonths( 12 ),
					'end' => Carbon::today(),
				),
				'this-year' => array(
					'label' => __( 'This Month', 'stream-report' ),
					'start' => Carbon::today()->day( 1 )->month( 1 ),
				),
				'last-year' => array(
					'label' => __( 'Last Month', 'stream-report' ),
					'depends' => 'this-year',
					'start' => Carbon::today()->day( 1 )->month( 1 )->subYear(),
					'end' => Carbon::today()->day( 1 )->month( 1 )->subSecond(),
				),
				'all-time' => array(
					'label' => __( 'All Time', 'stream-report' ),
				)
			)
		);
	}

	public function html(){
		$html = '';

		return $html;
	}
}