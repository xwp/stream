<?php
/**
 * Matches hydrated alerts against a Stream record.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alerts_Trigger_Engine
 *
 * @package WP_Stream
 */
class Alerts_Trigger_Engine {

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( public $plugin ) {
		$this->register_hooks();
	}

	/**
	 * Register record-insert matching hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter(
			'wp_stream_record_inserted',
			array(
				$this,
				'check_records',
			),
			10,
			2
		);
	}

	/**
	 * Checks record being processed against active alerts.
	 *
	 * @filter wp_stream_record_inserted
	 *
	 * @param int   $record_id The record being processed.
	 * @param array $recordarr Record data.
	 *
	 * @return array
	 */
	public function check_records( $record_id, $recordarr ) {
		$args = array(
			'post_type'   => Alerts::POST_TYPE,
			'post_status' => 'wp_stream_enabled',
		);

		$alerts   = new \WP_Query( $args );
		$hydrated = array();
		foreach ( $alerts->posts as $alert ) {
			$hydrated[] = $this->plugin->alerts->get_alert( $alert->ID );
		}

		foreach ( $this->matching_alerts( $hydrated, $record_id, $recordarr ) as $alert ) {
			$alert->send_alert( $record_id, $recordarr ); // @todo send_alert expects int, not array.
		}

		return $recordarr;
	}

	/**
	 * Return alerts whose check_record() result is true.
	 *
	 * Matching goes through Alert::check_record() so the
	 * wp_stream_alert_trigger_check filter still applies.
	 *
	 * @param array $alerts    Hydrated Alert objects (false entries are skipped).
	 * @param int   $record_id The record being processed.
	 * @param array $recordarr Record data.
	 * @return array Alert objects that matched.
	 */
	public function matching_alerts( array $alerts, $record_id, array $recordarr ) {
		$matches = array();
		foreach ( $alerts as $alert ) {
			if ( false === $alert ) {
				continue;
			}

			$status = $alert->check_record( $record_id, $recordarr );
			if ( $status ) {
				$matches[] = $alert;
			}
		}

		return $matches;
	}
}
