<?php
// Template function
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
				'action' => 'stream_reports_save_interval',
			),
			WP_Stream_Reports::$nonce
		),
		admin_url( 'admin-ajax.php' )
	);

	include WP_STREAM_REPORTS_VIEW_DIR . 'intervals.php';
}


class WP_Stream_Reports_Date_Interval extends WP_Stream_Date_Interval {

	/**
	 * Hold WP_Stream_Reports_Date_Interval instance
	 *
	 * @var string
	 */
	public static $instance;

	/*
	 * Handle parent constructor
	 */
	public function __construct() {
		// Call parent constructor
		parent::__construct();

		// Ajax declaration to save time interval
		$ajax_hooks = array(
			'stream_reports_save_interval' => 'save_interval',
		);

		// Register all ajax action and check referer for this class
		WP_Stream_Reports::handle_ajax_request( $ajax_hooks, $this );
	}

	/**
	 * Handle ajax saving of time intervals
	 */
	public function save_interval() {
		$interval = array(
			'key'   => wp_stream_filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING, array( 'default' => '' ) ),
			'start' => wp_stream_filter_input( INPUT_GET, 'start', FILTER_SANITIZE_STRING, array( 'default' => '' ) ),
			'end'   => wp_stream_filter_input( INPUT_GET, 'end', FILTER_SANITIZE_STRING, array( 'default' => '' ) ),
		);

		// Get predefined interval for validation
		$avail_intervals = $this->get_predefined_intervals();

		if ( '' !== $interval['key'] && 'custom' !== $interval['key']  && ! isset( $avail_intervals[ $interval['key'] ] ) ) {
			wp_die( esc_html__( 'That time interval is not available.', 'stream-reports' ) );
		}

		// Only store dates if we are dealing with custom dates and no relative preset
		if ( 'custom' !== $interval['key'] ) {
			$interval['start'] = '';
			$interval['end']   = '';
		}

		WP_Stream_Reports_Settings::update_user_option_and_redirect( 'interval', $interval );
	}

	/**
	 * Return active instance of WP_Stream_Reports, create one if it doesn't exist
	 *
	 * @return WP_Stream_Reports
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}
		return self::$instance;
	}

}
