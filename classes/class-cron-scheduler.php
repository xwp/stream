<?php
/**
 * WP-Cron implementation of the {@see Scheduler} interface.
 *
 * Fallback scheduler for environments that bundle Stream, run reliable cron
 * (e.g. Cavalcade), and prefer not to use Action Scheduler — selected by
 * returning false from the `wp_stream_use_action_scheduler` filter. See
 * issue #1907.
 *
 * Behavioral notes vs. {@see AS_Scheduler}:
 *  - Deferred work is not visible under Tools → Scheduled Actions; inspect
 *    via WP-Cron tooling (e.g. WP Crontrol) instead.
 *  - WP-Cron has no native "running" state store. The overlap guard combines
 *    a scan of pending events with a short-lived transient set by
 *    {@see Cron_Scheduler::mark_running()} while a callback executes, so a
 *    chain that is mid-flight (between batches) still reports as running.
 *    This is a best-effort approximation, not a hard lock.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Cron_Scheduler
 */
class Cron_Scheduler implements Scheduler {

	/**
	 * Custom cron recurrence name used for the recurring auto-purge.
	 *
	 * @const string
	 */
	const RECURRENCE = 'wp_stream_auto_purge_recurrence';

	/**
	 * Transient key for the best-effort "purge running" marker.
	 *
	 * @const string
	 */
	const RUNNING_TRANSIENT = 'wp_stream_scheduler_running';

	/**
	 * Register the custom cron recurrence used by the recurring auto-purge.
	 *
	 * The schedule must be registered on every request: WP-Cron re-reads the
	 * interval from `wp_get_schedules()` each time it reschedules a recurring
	 * event, so an unregistered name would break rescheduling.
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'register_recurrence' ) );
	}

	/**
	 * Inject Stream's custom cron recurrence (12 hours, matching the legacy
	 * `twicedaily` interval used before the Action Scheduler migration).
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array
	 */
	public function register_recurrence( $schedules ) {
		$schedules[ self::RECURRENCE ] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Twice Daily (Stream auto-purge)', 'stream' ),
		);

		return $schedules;
	}

	/**
	 * Enqueue a one-off action to run on the next cron tick.
	 *
	 * @param string $hook  Action hook name.
	 * @param array  $args  Arguments passed positionally to the callback.
	 * @param string $group Unused (Action Scheduler concept).
	 * @return void
	 */
	public function enqueue_async( $hook, $args = array(), $group = '' ) {
		wp_schedule_single_event( time(), $hook, array_values( (array) $args ) );
	}

	/**
	 * Schedule a recurring event if one is not already scheduled.
	 *
	 * The recurrence interval is fixed to {@see Cron_Scheduler::RECURRENCE}
	 * (12 hours); the $interval argument is accepted for interface parity but
	 * not used, since WP-Cron recurrences are named schedules registered up
	 * front. The sole caller schedules at the 12-hour cadence.
	 *
	 * @param int    $timestamp First run, as a Unix timestamp.
	 * @param int    $interval  Recurrence interval in seconds (unused; see above).
	 * @param string $hook      Action hook name.
	 * @param array  $args      Arguments passed positionally to the callback.
	 * @param string $group     Unused (Action Scheduler concept).
	 * @return void
	 */
	public function schedule_recurring( $timestamp, $interval, $hook, $args = array(), $group = '' ) {
		$args = array_values( (array) $args );

		if ( false === wp_next_scheduled( $hook, $args ) ) {
			wp_schedule_event( $timestamp, self::RECURRENCE, $hook, $args );
		}
	}

	/**
	 * Get the next scheduled timestamp for a hook (with matching args).
	 *
	 * @param string $hook Action hook name.
	 * @param array  $args Arguments the event was scheduled with.
	 * @return int|false
	 */
	public function next_scheduled( $hook, $args = array() ) {
		return wp_next_scheduled( $hook, array_values( (array) $args ) );
	}

	/**
	 * Whether any instance of a hook is scheduled, regardless of its args.
	 *
	 * @param string $hook Action hook name.
	 * @return bool
	 */
	public function has_scheduled( $hook ) {
		return $this->cron_has_any( $hook );
	}

	/**
	 * Whether any of the given hooks is pending, or a callback is currently
	 * running (best-effort, via the running transient).
	 *
	 * @param array $hooks Action hook names to probe.
	 * @return bool
	 */
	public function any_pending_or_running( $hooks ) {
		foreach ( (array) $hooks as $hook ) {
			if ( $this->cron_has_any( $hook ) ) {
				return true;
			}
		}

		return (bool) get_transient( self::RUNNING_TRANSIENT );
	}

	/**
	 * Unschedule every pending instance of a hook.
	 *
	 * @param string $hook Action hook name.
	 * @return void
	 */
	public function unschedule_all( $hook ) {
		wp_unschedule_hook( $hook );
	}

	/**
	 * Set the best-effort "purge running" marker.
	 *
	 * Bridges the window between a chained callback starting and the next
	 * event being scheduled, so the overlap guard does not momentarily read
	 * as idle mid-chain. Self-expires so a fatal mid-callback cannot wedge
	 * the guard permanently.
	 *
	 * @param string $context Short identifier for the running work.
	 * @return void
	 */
	public function mark_running( $context ) {
		set_transient( self::RUNNING_TRANSIENT, 1, 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Clear the "purge running" marker.
	 *
	 * @param string $context Short identifier for the running work.
	 * @return void
	 */
	public function mark_done( $context ) {
		delete_transient( self::RUNNING_TRANSIENT );
	}

	/**
	 * Scan the cron array for any pending event matching a hook, ignoring args.
	 *
	 * `wp_next_scheduled()` matches on a specific args signature; this detects
	 * a hook scheduled with any args (e.g. successive batch windows).
	 *
	 * @param string $hook Action hook name.
	 * @return bool
	 */
	protected function cron_has_any( $hook ) {
		$crons = _get_cron_array();

		if ( empty( $crons ) ) {
			return false;
		}

		foreach ( $crons as $events ) {
			if ( isset( $events[ $hook ] ) && ! empty( $events[ $hook ] ) ) {
				return true;
			}
		}

		return false;
	}
}
