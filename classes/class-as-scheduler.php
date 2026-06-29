<?php
/**
 * Action Scheduler implementation of the {@see Scheduler} interface.
 *
 * Thin wrappers over the `as_*()` API. This is the default scheduler and
 * preserves Stream's historical behavior exactly: batched purge chains run
 * through Action Scheduler, are grouped, and remain visible under
 * Tools → Scheduled Actions. The in-progress markers are no-ops because
 * Action Scheduler tracks RUNNING state in its own store.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - AS_Scheduler
 */
class AS_Scheduler implements Scheduler {

	/**
	 * Enqueue a one-off asynchronous action.
	 *
	 * @param string $hook  Action hook name.
	 * @param array  $args  Arguments passed positionally to the callback.
	 * @param string $group Optional grouping label.
	 * @return void
	 */
	public function enqueue_async( $hook, $args = array(), $group = '' ) {
		as_enqueue_async_action( $hook, $args, $group );
	}

	/**
	 * Schedule a recurring action if one is not already scheduled.
	 *
	 * @param int    $timestamp First run, as a Unix timestamp.
	 * @param int    $interval  Recurrence interval in seconds.
	 * @param string $hook      Action hook name.
	 * @param array  $args      Arguments passed positionally to the callback.
	 * @param string $group     Optional grouping label.
	 * @return void
	 */
	public function schedule_recurring( $timestamp, $interval, $hook, $args = array(), $group = '' ) {
		if ( false === as_next_scheduled_action( $hook ) ) {
			as_schedule_recurring_action( $timestamp, $interval, $hook, $args, $group );
		}
	}

	/**
	 * Get the next scheduled timestamp for a hook.
	 *
	 * @param string $hook Action hook name.
	 * @param array  $args Arguments the action was scheduled with.
	 * @return int|false
	 */
	public function next_scheduled( $hook, $args = array() ) {
		return as_next_scheduled_action( $hook, empty( $args ) ? null : $args );
	}

	/**
	 * Whether any instance of a hook is scheduled.
	 *
	 * @param string $hook Action hook name.
	 * @return bool
	 */
	public function has_scheduled( $hook ) {
		return as_has_scheduled_action( $hook );
	}

	/**
	 * Whether any of the given hooks is pending or in progress.
	 *
	 * Checks both PENDING and RUNNING statuses so a chain that is mid-flight
	 * (the batch worker is executing and has not yet enqueued the next batch)
	 * still reports as running, preventing a second parallel chain from
	 * stacking against the same rows.
	 *
	 * @param array $hooks Action hook names to probe.
	 * @return bool
	 */
	public function any_pending_or_running( $hooks ) {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}

		foreach ( (array) $hooks as $hook ) {
			$found = as_get_scheduled_actions(
				array(
					'hook'     => $hook,
					'status'   => array(
						\ActionScheduler_Store::STATUS_PENDING,
						\ActionScheduler_Store::STATUS_RUNNING,
					),
					'per_page' => 1,
				),
				'ids'
			);
			if ( ! empty( $found ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Unschedule every pending instance of a hook.
	 *
	 * @param string $hook Action hook name.
	 * @return void
	 */
	public function unschedule_all( $hook ) {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook );
		}
	}

	/**
	 * No-op: Action Scheduler tracks RUNNING state in its own store.
	 *
	 * @param string $context Short identifier for the running work.
	 * @return void
	 */
	public function mark_running( $context ) {}

	/**
	 * No-op counterpart to {@see AS_Scheduler::mark_running()}.
	 *
	 * @param string $context Short identifier for the running work.
	 * @return void
	 */
	public function mark_done( $context ) {}
}
