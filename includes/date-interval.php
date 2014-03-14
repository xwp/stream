<?php
use Carbon\Carbon;

// Template function
function stream_reports_intervals_html() {
	$date = new WP_Stream_Date_Interval();

	// Default interval
	$default = array(
		'key'   => 'all-time',
		'start' => '',
		'end'   => '',
	);

	$user_interval = WP_Stream_Reports_Settings::get_user_options( 'interval', $default );

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
