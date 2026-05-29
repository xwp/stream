<?php
/**
 * Calculate date intervals for a specific timezone.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Date_Interval
 */
class Date_Interval {
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
		// Get all default intervals.
		$this->intervals = $this->get_predefined_intervals();
	}

	/**
	 * Returns date interval data based upon "Timezone" site setting.
	 *
	 * @todo add better inline comments
	 * @return mixed
	 */
	public function get_predefined_intervals() {
		$timezone = get_option( 'timezone_string' );

		if ( empty( $timezone ) ) {
			$gmt_offset = (int) get_option( 'gmt_offset' );
			$timezone   = timezone_name_from_abbr( '', $gmt_offset * 3600, true );
			if ( false === $timezone ) {
				$timezone = timezone_name_from_abbr( '', $gmt_offset * 3600, false );
			}
		}

		try {
			$timezone_object = $timezone ? new \DateTimeZone( $timezone ) : null;
		} catch ( \Exception $e ) {
			$timezone_object = null;
		}

		try {
			$today          = new \DateTimeImmutable( 'today', $timezone_object );
			$date_intervals = $this->generate_date_intervals( $today );
		} catch ( \Exception $e ) {
			$date_intervals = array();
		}

		/**
		 * Allow other plugins to filter the predefined date intervals.
		 *
		 * @param array  $date_intervals Date intervals array.
		 * @param string $timezone       Timezone.
		 */
		return apply_filters( 'wp_stream_predefined_date_intervals', $date_intervals, $timezone );
	}

	/**
	 * Generate date intervals relative to date object provided.
	 *
	 * @param \DateTimeImmutable $date Date object.
	 *
	 * @return array[]
	 */
	public function generate_date_intervals( \DateTimeImmutable $date ) {
		return array(
			'today'          => array(
				'label' => esc_html__( 'Today', 'stream' ),
				'start' => $date,
				'end'   => $date->modify( '+1 day -1 microsecond' ),
			),
			'yesterday'      => array(
				'label' => esc_html__( 'Yesterday', 'stream' ),
				'start' => $date->modify( '-1 day' ),
				'end'   => $date->modify( '-1 microsecond' ),
			),
			'last-7-days'    => array(
				/* translators: %d: number of days (e.g. "7") */
				'label' => sprintf( esc_html__( 'Last %d Days', 'stream' ), 7 ),
				'start' => $date->modify( '-7 days' ),
				'end'   => $date,
			),
			'last-14-days'   => array(
				/* translators: %d: number of days (e.g. "7") */
				'label' => sprintf( esc_html__( 'Last %d Days', 'stream' ), 14 ),
				'start' => $date->modify( '-14 days' ),
				'end'   => $date,
			),
			'last-30-days'   => array(
				/* translators: %d: number of days (e.g. "7") */
				'label' => sprintf( esc_html__( 'Last %d Days', 'stream' ), 30 ),
				'start' => $date->modify( '-30 days' ),
				'end'   => $date,
			),
			'this-month'     => array(
				'label' => esc_html__( 'This Month', 'stream' ),
				'start' => $date->modify( 'first day of this month' ),
				'end'   => $date->modify( 'last day of this month' )->modify( '+1 day -1 microsecond' ),
			),
			'last-month'     => array(
				'label' => esc_html__( 'Last Month', 'stream' ),
				'start' => $date->modify( 'first day of last month' ),
				'end'   => $date->modify( 'last day of last month' )->modify( '+1 day -1 microsecond' ),
			),
			'last-3-months'  => array(
				/* translators: %d: number of months (e.g. "3") */
				'label' => sprintf( esc_html__( 'Last %d Months', 'stream' ), 3 ),
				'start' => $date->modify( '-3 months' ),
				'end'   => $date,
			),
			'last-6-months'  => array(
				/* translators: %d: number of months (e.g. "3") */
				'label' => sprintf( esc_html__( 'Last %d Months', 'stream' ), 6 ),
				'start' => $date->modify( '-6 months' ),
				'end'   => $date,
			),
			'last-12-months' => array(
				/* translators: %d: number of months (e.g. "3") */
				'label' => sprintf( esc_html__( 'Last %d Months', 'stream' ), 12 ),
				'start' => $date->modify( '-12 months' ),
				'end'   => $date,
			),
			'this-year'      => array(
				'label' => esc_html__( 'This Year', 'stream' ),
				'start' => $date->modify( 'first day of January' ),
				'end'   => $date->modify( 'last day of December' )->modify( '+1 day -1 microsecond' ),
			),
			'last-year'      => array(
				'label' => esc_html__( 'Last Year', 'stream' ),
				'start' => $date->modify( 'first day of January' )->modify( '-1 year' ),
				'end'   => $date->modify( 'first day of January' )->modify( '-1 microsecond' ),
			),
		);
	}
}
