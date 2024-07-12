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
			if ( false === $timezone ) {
				$timezone = null;
			}
		}

		try {
			$today          = new \DateTimeImmutable( 'today', new \DateTimeZone( $timezone ) );
			$date_intervals = array(
				'today'          => array(
					'label' => esc_html__( 'Today', 'stream' ),
					'start' => $today,
					'end'   => $today->modify( '+1 day -1 microsecond' ),
				),
				'yesterday'      => array(
					'label' => esc_html__( 'Yesterday', 'stream' ),
					'start' => $today->modify( '-1 day' ),
					'end'   => $today->modify( '-1 microsecond' ),
				),
				'last-7-days'    => array(
					/* translators: %d: number of days (e.g. "7") */
					'label' => sprintf( esc_html__( 'Last %d Days', 'stream' ), 7 ),
					'start' => $today->modify( '-7 days' ),
					'end'   => $today,
				),
				'last-14-days'   => array(
					/* translators: %d: number of days (e.g. "7") */
					'label' => sprintf( esc_html__( 'Last %d Days', 'stream' ), 14 ),
					'start' => $today->modify( '-14 days' ),
					'end'   => $today,
				),
				'last-30-days'   => array(
					/* translators: %d: number of days (e.g. "7") */
					'label' => sprintf( esc_html__( 'Last %d Days', 'stream' ), 30 ),
					'start' => $today->modify( '-30 days' ),
					'end'   => $today,
				),
				'this-month'     => array(
					'label' => esc_html__( 'This Month', 'stream' ),
					'start' => $today->modify( 'first day of this month' ),
					'end'   => $today->modify( 'last day of this month' )->modify( '+1 day -1 microsecond' ),
				),
				'last-month'     => array(
					'label' => esc_html__( 'Last Month', 'stream' ),
					'start' => $today->modify( 'first day of last month' ),
					'end'   => $today->modify( 'last day of last month' )->modify( '+1 day -1 microsecond' ),
				),
				'last-3-months'  => array(
					/* translators: %d: number of months (e.g. "3") */
					'label' => sprintf( esc_html__( 'Last %d Months', 'stream' ), 3 ),
					'start' => $today->modify( '-3 months' ),
					'end'   => $today,
				),
				'last-6-months'  => array(
					/* translators: %d: number of months (e.g. "3") */
					'label' => sprintf( esc_html__( 'Last %d Months', 'stream' ), 6 ),
					'start' => $today->modify( '-6 months' ),
					'end'   => $today,
				),
				'last-12-months' => array(
					/* translators: %d: number of months (e.g. "3") */
					'label' => sprintf( esc_html__( 'Last %d Months', 'stream' ), 12 ),
					'start' => $today->modify( '-12 months' ),
					'end'   => $today,
				),
				'this-year'      => array(
					'label' => esc_html__( 'This Year', 'stream' ),
					'start' => $today->modify( 'first day of January' ),
					'end'   => $today->modify( 'last day of December' )->modify( '+1 day -1 microsecond' ),
				),
				'last-year'      => array(
					'label' => esc_html__( 'Last Year', 'stream' ),
					'start' => $today->modify( 'first day of January' )->modify( '-1 year' ),
					'end'   => $today->modify( 'first day of January' )->modify( '-1 microsecond' ),
				),
			);
		} catch ( \Exception $e ) {
			$date_intervals = array();
		}

		return apply_filters( 'wp_stream_predefined_date_intervals', $date_intervals, $timezone );
	}
}
