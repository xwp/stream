<?php
use Carbon\Carbon;

// Template function
function stream_report_intervals_html() {
	$date = WP_Stream_Report_Date_Interval::get_instance();
	$date->load();

	include WP_STREAM_REPORTS_VIEW_DIR . 'intervals.php';
}

class WP_Stream_Report_Date_Interval {
	/**
	 * Hold Stream Reports Section instance
	 *
	 * @var string
	 */
	public static $instance;

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
		// Ajax declaration to save time interval

	}

	/**
	 * Load function
	 */
	public function load() {
		// Filter the Predefined list of intervals to make it work
		add_filter( 'stream-report-predefined-intervals', array( $this, 'filter_predefined_intervals' ), 20 );

		// Get all default intervals
		$this->intervals = $this->get_predefined_intervals();
	}

	/**
	 * @return mixed|void
	 */
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
					'start' => Carbon::today()->subDay(),
					'end' => Carbon::today()->subSecond(),
				),
				'last-7-days' => array(
					'label' => sprintf( __( 'Last %d Days', 'stream-report' ), 7 ),
					'start' => Carbon::today()->subDays( 7 ),
					'end' => Carbon::today(),
				),
				'last-14-days' => array(
					'label' => sprintf( __( 'Last %d Days', 'stream-report' ), 14 ),
					'start' => Carbon::today()->subDays( 14 ),
					'end' => Carbon::today(),
				),
				'last-30-days' => array(
					'label' => sprintf( __( 'Last %d Days', 'stream-report' ), 30 ),
					'start' => Carbon::today()->subDays( 30 ),
					'end' => Carbon::today(),
				),
				'this-month' => array(
					'label' => __( 'This Month', 'stream-report' ),
					'start' => Carbon::today()->day( 1 ),
				),
				'last-month' => array(
					'label' => __( 'Last Month', 'stream-report' ),
					'start' => Carbon::today()->day( 1 )->subMonth(),
					'end' => Carbon::today()->day( 1 )->subSecond(),
				),
				'last-3-months' => array(
					'label' => sprintf( __( 'Last %d Months', 'stream-report' ), 3 ),
					'start' => Carbon::today()->subMonths( 3 ),
					'end' => Carbon::today(),
				),
				'last-6-months' => array(
					'label' => sprintf( __( 'Last %d Months', 'stream-report' ), 6 ),
					'start' => Carbon::today()->subMonths( 6 ),
					'end' => Carbon::today(),
				),
				'last-12-months' => array(
					'label' => sprintf( __( 'Last %d Months', 'stream-report' ), 12 ),
					'start' => Carbon::today()->subMonths( 12 ),
					'end' => Carbon::today(),
				),
				'this-year' => array(
					'label' => __( 'This Month', 'stream-report' ),
					'start' => Carbon::today()->day( 1 )->month( 1 ),
				),
				'last-year' => array(
					'label' => __( 'Last Month', 'stream-report' ),
					'start' => Carbon::today()->day( 1 )->month( 1 )->subYear(),
					'end' => Carbon::today()->day( 1 )->month( 1 )->subSecond(),
				),
				'all-time' => array(
					'label' => __( 'All Time', 'stream-report' ),
				)
			)
		);
	}

	/**
	 * Filter the predefined intervals to reflect db oldest value
	 * @param $intervals
	 *
	 * @return array
	 */
	public function filter_predefined_intervals( $intervals ){
		$query = stream_query(
			array(
				'order'            => 'ASC',
				'orderby'          => 'created',
				'records_per_page' => 1,
				'ignore_context'   => true,
			)
		);

		$first_stream_item = reset( $query );

		if ( $first_stream_item === false ){
			return false;
		}

		$first_stream_date = \Carbon\Carbon::parse( $first_stream_item->created );

		foreach ( $intervals as $key => $interval ){
			if ( ! isset( $interval['start'] ) || $interval['start'] === false ){
				$intervals[$key]['start'] = $interval['start'] = $first_stream_date;
			}
			if ( ! isset( $interval['end'] ) || $interval['end'] === false ){
				$intervals[$key]['end'] = $interval['end'] = \Carbon\Carbon::now();
			}

			if ( ! is_a( $interval['start'], '\Carbon\Carbon' ) || ! is_a( $interval['end'], '\Carbon\Carbon' ) ) {
				unset( $intervals[$key] );
				continue;
			}
		}

		return $intervals;
	}

	/**
	 * Return active instance of WP_Stream_Report_Date_Interval, create one if it doesn't exist
	 *
	 * @return WP_Stream_Report_Date_Interval
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}
		return self::$instance;
	}
}
