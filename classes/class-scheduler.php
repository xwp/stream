<?php
/**
 * Scheduler abstraction for Stream's deferred work.
 *
 * Stream defers record purging and large-table resets to a background
 * scheduler so they do not block admin requests. Historically this was
 * Action Scheduler (AS) exclusively. This interface decouples the calling
 * code (Admin, Settings) from the concrete scheduler so the same purge
 * logic can run either through Action Scheduler or through WP-Cron,
 * selected at runtime via the `wp_stream_use_action_scheduler` filter.
 *
 * Implementations:
 *  - {@see AS_Scheduler}   — Action Scheduler (default; bundled dependency).
 *  - {@see Cron_Scheduler} — WP-Cron fallback for hosts with reliable cron
 *                            (e.g. Cavalcade) that prefer not to use AS.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Interface - Scheduler
 */
interface Scheduler {

	/**
	 * Enqueue a one-off asynchronous action to run as soon as possible.
	 *
	 * Values in $args are passed positionally to the hook callback, in the
	 * order they appear in the array, mirroring Action Scheduler semantics.
	 * How the args are *stored* is backend-specific: AS keeps the array as
	 * given (preserving Stream's historical behavior and the keyed display
	 * in Tools → Scheduled Actions), while cron stores array_values(). Args
	 * therefore only round-trip through next_scheduled() on the backend
	 * that scheduled them — which is the only supported usage.
	 *
	 * @param string $hook  Action hook name.
	 * @param array  $args  Arguments passed positionally to the callback.
	 * @param string $group Optional grouping label (used by AS; ignored by cron).
	 * @return void
	 */
	public function enqueue_async( $hook, $args = array(), $group = '' );

	/**
	 * Schedule a recurring action if one is not already scheduled.
	 *
	 * The "already scheduled" probe may be hook-scoped (ignoring args and
	 * group): the AS backend intentionally checks the hook only, preserving
	 * Stream's historical behavior and preventing recurrences with differing
	 * args from stacking. Callers must treat one recurring action per hook
	 * as the contract; the sole caller schedules with empty args.
	 *
	 * @param int    $timestamp First run, as a Unix timestamp.
	 * @param int    $interval  Recurrence interval in seconds.
	 * @param string $hook      Action hook name.
	 * @param array  $args      Arguments passed positionally to the callback.
	 * @param string $group     Optional grouping label (used by AS; ignored by cron).
	 * @return void
	 */
	public function schedule_recurring( $timestamp, $interval, $hook, $args = array(), $group = '' );

	/**
	 * Get the next scheduled timestamp for a hook (with matching args).
	 *
	 * @param string $hook Action hook name.
	 * @param array  $args Arguments the action was scheduled with.
	 * @return int|false Unix timestamp of the next run, or false if none.
	 */
	public function next_scheduled( $hook, $args = array() );

	/**
	 * Whether any instance of a hook is scheduled, regardless of its args.
	 *
	 * @param string $hook Action hook name.
	 * @return bool
	 */
	public function has_scheduled( $hook );

	/**
	 * Whether any of the given hooks is pending or currently in progress.
	 *
	 * Used as the auto-purge overlap guard and to drive the Settings UI
	 * "currently running" notices.
	 *
	 * @param array $hooks Action hook names to probe.
	 * @return bool
	 */
	public function any_pending_or_running( $hooks );

	/**
	 * Unschedule every pending instance of a hook.
	 *
	 * @param string $hook Action hook name.
	 * @return void
	 */
	public function unschedule_all( $hook );

	/**
	 * Mark a deferred-work context as actively running.
	 *
	 * No-op for schedulers that track in-progress state natively (AS). The
	 * cron fallback uses it to bridge the window between a callback starting
	 * and the next chained event being scheduled, so the overlap guard does
	 * not report "idle" mid-chain.
	 *
	 * @param string $context Short identifier for the running work.
	 * @return void
	 */
	public function mark_running( $context );

	/**
	 * Clear the running marker set by {@see Scheduler::mark_running()}.
	 *
	 * @param string $context Short identifier for the running work.
	 * @return void
	 */
	public function mark_done( $context );
}
