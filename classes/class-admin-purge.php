<?php
/**
 * TTL auto-purge, large-table erase, and orphan-meta cleanup.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use DateTime;
use DateTimeZone;
use DateInterval;

/**
 * Class - Admin_Purge
 */
class Admin_Purge {

	/**
	 * Class constructor.
	 *
	 * @param Admin $admin Admin façade.
	 */
	public function __construct( private Admin $admin ) {
		$this->register_hooks();
	}

	/**
	 * Register WordPress actions and filters for purge / cron / async erase.
	 */
	private function register_hooks(): void {
		add_action( 'wp_loaded', array( $this, 'purge_schedule_setup' ) );
		add_action( Admin::AUTO_PURGE_ACTION, array( $this, 'purge_scheduled_action' ) );
		add_action( Admin::AUTO_PURGE_BATCH_ACTION, array( $this, 'auto_purge_batch' ), 10, 3 );
		add_action( Admin::AUTO_PURGE_REAPER_ACTION, array( $this, 'auto_purge_reaper' ) );
		add_action( Admin::ASYNC_DELETION_ACTION, array( $this, 'erase_large_records' ), 10, 4 );
		add_action( 'admin_notices', array( $this, 'display_large_table_cron_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'display_large_table_cron_notice' ) );
	}

	/**
	 * Checks if the async deletion process is running.
	 *
	 * Checks pending AND in-flight state, mirroring
	 * {@see Admin_Purge::is_running_auto_purge()}. Under WP-Cron the event is
	 * removed from the cron array before its callback runs, so a
	 * pending-only probe would momentarily read idle mid-chain and briefly
	 * re-expose the reset link in Settings. The batch worker keeps the
	 * best-effort running marker set for that window (see
	 * {@see Admin_Purge::erase_large_records()}). The marker transient is shared
	 * with the auto-purge chain, which only makes both guards more
	 * conservative — never less safe.
	 *
	 * @return bool True if the async deletion process is running, false otherwise.
	 */
	public function is_running_async_deletion() {
		$scheduler = $this->admin->plugin->scheduler;
		if ( empty( $scheduler ) ) {
			return false;
		}
		return $scheduler->any_pending_or_running( array( Admin::ASYNC_DELETION_ACTION ) );
	}

	/**
	 * Checks if any auto-purge action is currently scheduled or in-flight.
	 *
	 * Returns true when either the batched chain worker or the terminal
	 * orphan reaper is pending OR running. The recurring scheduler is
	 * intentionally excluded — it is always pending under normal operation,
	 * so including it here would make the probe useless. Used by the
	 * Settings → Advanced UI to render an "Auto-purge currently running"
	 * notice and by the recurring callback as an overlap guard.
	 *
	 * Checks both PENDING and IN-PROGRESS statuses so a chain that is
	 * mid-execution (e.g. the batch worker is currently running and has not
	 * yet enqueued the next batch) still reports as running. Without the
	 * RUNNING check the overlap guard can let a second parallel chain stack
	 * against the same rows.
	 *
	 * @return bool
	 */
	public function is_running_auto_purge() {
		$scheduler = $this->admin->plugin->scheduler;
		if ( empty( $scheduler ) ) {
			return false;
		}

		return $scheduler->any_pending_or_running(
			array( Admin::AUTO_PURGE_BATCH_ACTION, Admin::AUTO_PURGE_REAPER_ACTION )
		);
	}

	/**
	 * Clears stream records from the database.
	 *
	 * @return void
	 */
	public function erase_stream_records() {
		global $wpdb;

		// If this is a multisite and it's not network activated,
		// only delete the entries from the blog which made the request.
		if ( $this->admin->plugin->is_multisite_not_network_activated() ) {

			// First check the log size.
			$stream_log_size = $this->admin->get_blog_record_table_size();

			// If this is a large log and we need to delete only the entries
			// pertaining to an individual site, we will need to do those in batches.
			if ( $this->admin->plugin->is_large_records_table( $stream_log_size ) ) {
				$this->schedule_erase_large_records( $stream_log_size );
				return;
			}

			$wpdb->query(
				$wpdb->prepare(
					"DELETE `stream`, `meta`
					FROM {$wpdb->stream} AS `stream`
					LEFT JOIN {$wpdb->streammeta} AS `meta`
					ON `meta`.`record_id` = `stream`.`ID`
					WHERE `blog_id`=%d;",
					get_current_blog_id()
				)
			);
		} else {
			// If we are deleting all the entries, we can truncate the tables.
			$wpdb->query( "TRUNCATE {$wpdb->streammeta};" );
			$wpdb->query( "TRUNCATE {$wpdb->stream};" );
			// Tidy up any meta which may have been added in between the two truncations.
			$this->delete_orphaned_meta();
		}
	}

	/**
	 * Schedule the initial event to start erasing the logs from now.
	 *
	 * @param int $log_size The number of rows which will be affected.
	 * @return void
	 */
	public function schedule_erase_large_records( int $log_size ) {
		global $wpdb;

		$last_entry = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->stream} WHERE `blog_id`=%d ORDER BY ID DESC LIMIT 1",
				get_current_blog_id()
			)
		);

		// If there are no entries to erase, don't try to erase them.
		if ( empty( $last_entry ) ) {
			return;
		}

		// We are going to delete this many and this many only.
		// This is to avoid the situation where rows keep getting added
		// between the Action Scheduler runs and they never stop.
		$args = array(
			'total'      => (int) $log_size,
			'done'       => 0,
			'last_entry' => (int) $last_entry,
			'blog_id'    => (int) get_current_blog_id(),
		);

		$this->admin->plugin->scheduler->enqueue_async( Admin::ASYNC_DELETION_ACTION, $args );

		$this->maybe_warn_large_table_without_action_scheduler(
			(int) $log_size,
			__( 'reset the Stream database (delete all records for this site)', 'stream' )
		);
	}

	/**
	 * Warn when a large-table batched operation has to lean on WP-Cron.
	 *
	 * Action Scheduler is purpose-built to drain long self-chaining batch
	 * jobs reliably; default WP-Cron fires opportunistically on traffic and
	 * can stall a multi-hour chain on a low-traffic site. When Stream is
	 * running the WP-Cron fallback (the `wp_stream_use_action_scheduler`
	 * filter returned false, or the bundled AS library is absent) against a
	 * table over the large-table threshold, surface a notice pointing the
	 * operator at a deterministic WP-CLI drain instead of failing silently.
	 *
	 * Delivery depends on context. Under WP-CLI the warning is emitted
	 * immediately via {@see Admin::notice()} (WP_CLI::warning) — scheduling
	 * the batch chain onto WP-Cron does not drain it, so a headless /
	 * low-traffic site is exactly where the chain can stall. Outside WP-CLI
	 * neither call site renders its own output (the recurring purge runs
	 * under DOING_CRON; the manual reset redirects and exits before its
	 * shutdown hook output reaches the browser), so the message is persisted
	 * to {@see Admin::LARGE_TABLE_CRON_NOTICE_OPTION} and rendered on the
	 * next admin page load by {@see Admin_Purge::display_large_table_cron_notice()}.
	 *
	 * No-op when Action Scheduler is the active backend (built to drain long
	 * chains). The `wp_stream_enable_auto_purge` filter deliberately does NOT
	 * gate this helper: it governs TTL retention purging only, while this
	 * warning also covers the manual database reset — an operator who manages
	 * retention externally can still click "Reset Stream Database" and needs
	 * the stall warning. The auto-purge call site is already gated by the
	 * filter's early return in {@see Admin_Purge::purge_scheduled_action()}.
	 *
	 * @param int    $record_count Number of rows the operation will touch.
	 * @param string $operation    Human-readable, translated description of what the
	 *                             batched work does (e.g. "delete records older than
	 *                             the retention period"), interpolated into the notice.
	 * @return void
	 */
	public function maybe_warn_large_table_without_action_scheduler( int $record_count, string $operation ) {
		if ( $this->admin->plugin->scheduler instanceof AS_Scheduler ) {
			return;
		}

		if ( ! $this->admin->plugin->is_large_records_table( $record_count ) ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: operation description (e.g. "delete records older than the retention period"), 2: number of records, 3: WP-CLI command. */
			__( 'Stream queued a large batched operation to %1$s (%2$s records) to WP-Cron because Action Scheduler is disabled. The records are removed in chained batches as WP-Cron runs. This completes on its own where reliable cron is configured (a Linux crontab or third-party cron service triggering wp-cron.php on a fixed interval, without an execution timeout). On sites relying on default traffic-triggered WP-Cron the chain may stall before it finishes, leaving records only partly removed; to run it to completion deterministically, use WP-CLI: %3$s', 'stream' ),
			$operation,
			number_format_i18n( $record_count ),
			'<code>wp cron event run --due-now</code>'
		);

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			// Immediate WP_CLI::warning — the operator is watching the terminal.
			$this->admin->notice( $message );
			return;
		}

		// Persist for the next admin page load. Neither call site can render
		// output itself: the recurring purge runs under DOING_CRON (response
		// discarded) and the manual reset redirects + exits before shutdown
		// output reaches the browser. No autoload — this is set rarely and
		// read only in the admin.
		update_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION, $message, false );
	}

	/**
	 * Render (and clear) the persisted large-table WP-Cron warning.
	 *
	 * Counterpart to {@see Admin_Purge::maybe_warn_large_table_without_action_scheduler()}:
	 * displays the stored warning on the first admin page an operator with
	 * the Stream settings capability loads after a large batched operation
	 * was queued onto WP-Cron.
	 *
	 * @action admin_notices
	 * @action network_admin_notices
	 *
	 * @return void
	 */
	public function display_large_table_cron_notice() {
		if ( ! current_user_can( $this->admin->settings_cap ) ) {
			return;
		}

		$message = get_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );
		if ( empty( $message ) ) {
			return;
		}

		delete_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );

		printf(
			'<div class="notice notice-warning">%s</div>',
			wp_kses_post( wpautop( $message ) )
		);
	}

	/**
	 * Erases large records from the stream table.
	 *
	 * This function deletes records from the stream table in batches, starting from a given entry ID.
	 * It deletes records in reverse chronological order, starting from the largest ID and going back.
	 * The number of records deleted in each batch is determined by the batch size, which can be filtered
	 * using the 'wp_stream_batch_size' hook.
	 *
	 * @param int $total      The total number of records to be deleted.
	 * @param int $done       The number of records that have already been deleted.
	 * @param int $last_entry The ID of the last entry that was deleted.
	 * @param int $blog_id    The ID of the blog for which the records should be deleted.
	 * @return void
	 */
	public function erase_large_records( int $total, int $done, int $last_entry, int $blog_id ) {
		global $wpdb;

		// Best-effort "running" marker, mirroring auto_purge_batch(). Under
		// WP-Cron the event is dequeued before this callback runs, so without
		// the marker is_running_async_deletion() would momentarily read idle
		// between batches and briefly re-expose the reset link in Settings.
		// No-op under Action Scheduler; self-expires on a fatal.
		$this->admin->plugin->scheduler->mark_running( 'async_deletion' );

		$start_from = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->stream} WHERE ID < %d AND `blog_id`=%d ORDER BY ID DESC LIMIT 1",
				$last_entry + 1, // A tweak to get it correct the first time through.
				get_current_blog_id()
			)
		);

		if ( empty( $start_from ) ) {
			// Terminal batch: nothing left to delete, no further event will
			// be chained, and no work follows within this callback — safe to
			// clear the marker immediately (unlike the auto-purge chain,
			// whose terminal batch hands off to the reaper).
			$this->admin->plugin->scheduler->mark_done( 'async_deletion' );
			return;
		}

		/**
		 * Filters the number of records in the {$wpdb->stream} table to do at a time.
		 *
		 * @since 4.1.0
		 *
		 * @param int $batch_size The batch size, default 250000.
		 */
		$batch_size = apply_filters( 'wp_stream_batch_size', 250000 );

		// This will tend to erase them in reverse chronological order,
		// ie it will start from the largest ID and go back from there.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE `stream`, `meta`
				FROM {$wpdb->stream} AS `stream`
				LEFT JOIN {$wpdb->streammeta} AS `meta`
				ON `meta`.`record_id` = `stream`.`ID`
				WHERE ID <= %d AND ID >= %d AND `blog_id`=%d;",
				$start_from,
				$start_from - $batch_size,
				get_current_blog_id()
			)
		);

		$remaining = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->stream} WHERE `blog_id`=%d", $blog_id )
		);

		$done = $total - $remaining;

		$this->admin->plugin->scheduler->enqueue_async(
			Admin::ASYNC_DELETION_ACTION,
			array(
				'total'      => (int) $total,
				'done'       => (int) $done,
				'last_entry' => (int) $start_from - $batch_size, // The last ID checked.
				'blog_id'    => (int) $blog_id,
			)
		);
	}

	/**
	 * Schedules a purge of records.
	 *
	 * @return void
	 */
	public function purge_schedule_setup() {
		// Clear the legacy WP-Cron event scheduled by Stream <= 4.1.x so it
		// cannot double-fire alongside the new recurring action.
		if ( wp_next_scheduled( 'wp_stream_auto_purge' ) ) {
			wp_clear_scheduled_hook( 'wp_stream_auto_purge' );
		}

		$scheduler = $this->admin->plugin->scheduler;

		/**
		 * Filter whether Stream schedules its TTL record auto-purge at all.
		 *
		 * Custom storage drivers that manage retention externally (TTL
		 * indexes, partition rotation, a warehouse job, etc.) can return
		 * false to disable all TTL purge scheduling regardless of the
		 * scheduler backend. Any already-registered recurring purge is
		 * unscheduled from both backends so it cannot keep firing.
		 *
		 * @param bool $enabled Whether auto-purge scheduling is enabled.
		 */
		if ( ! apply_filters( 'wp_stream_enable_auto_purge', true ) ) {
			// Tear down only once, then record the 'disabled' sentinel in the
			// backend marker. This runs on every wp_loaded, so without the
			// guard a permanently-disabled site would pay the unschedule
			// probes on every request; with it, steady state is a single
			// in-memory compare (the marker is autoloaded). The sentinel also
			// covers a site upgrading with the filter already active (no
			// marker yet, but a recurring action left by a previous version).
			// The executing path is independently gated by the same filter in
			// purge_scheduled_action(), so a stray entry that somehow survives
			// cannot purge anything anyway.
			if ( 'disabled' !== get_option( Admin::SCHEDULER_BACKEND_OPTION ) ) {
				$scheduler->unschedule_all( Admin::AUTO_PURGE_ACTION );
				wp_unschedule_hook( Admin::AUTO_PURGE_ACTION );

				// Also clear the Action Scheduler store when its API is
				// available but AS is not the active backend (e.g. the cron
				// backend is selected while WooCommerce provides AS). The
				// active-backend unschedule above cannot see AS's store, and
				// this filter promises teardown from BOTH backends. When AS
				// is entirely absent this is skipped — a stray AS entry
				// cannot execute (no AS runner), and if AS appears later the
				// action fires as a no-op thanks to the execute-path gate.
				if ( ! $scheduler instanceof AS_Scheduler && function_exists( 'as_unschedule_all_actions' ) ) {
					( new AS_Scheduler() )->unschedule_all( Admin::AUTO_PURGE_ACTION );
				}

				update_option( Admin::SCHEDULER_BACKEND_OPTION, 'disabled' );
			}
			return;
		}

		$backend = $scheduler instanceof AS_Scheduler ? 'action_scheduler' : 'wp_cron';

		// Detect a backend switch and clear the inactive backend's copy of the
		// recurring action exactly once. A site that switched schedulers (via
		// the wp_stream_use_action_scheduler filter) would otherwise keep
		// firing the purge from BOTH backends — the two stores are independent
		// and neither overlap guard can see the other. The marker is an
		// autoloaded option, so the steady-state cost on every wp_loaded is a
		// single in-memory compare; the cleanup query runs only on the first
		// page load after a switch. Idempotent and self-healing. No data is
		// affected — only the redundant schedule entry.
		if ( get_option( Admin::SCHEDULER_BACKEND_OPTION ) !== $backend ) {
			$cleanup_done = true;

			if ( 'action_scheduler' === $backend ) {
				// Drop any leftover WP-Cron recurring event.
				wp_unschedule_hook( Admin::AUTO_PURGE_ACTION );
			} elseif ( function_exists( 'as_unschedule_all_actions' ) ) {
				// Drop any leftover Action Scheduler recurring action. Routed
				// through AS_Scheduler so the as_*() call stays contained there.
				( new AS_Scheduler() )->unschedule_all( Admin::AUTO_PURGE_ACTION );
			} else {
				// Action Scheduler is not loaded (cron backend selected and no
				// other plugin provides AS), so its store cannot be cleaned
				// right now. Do NOT write the marker: if an AS-providing
				// plugin (e.g. WooCommerce) is installed later, the stray
				// Stream recurring action in the AS store would resume firing
				// alongside the cron one — and the cron overlap guard cannot
				// see it. Leaving the marker stale retries this cleanup on a
				// later request once as_unschedule_all_actions() exists.
				$cleanup_done = false;
			}

			if ( $cleanup_done ) {
				update_option( Admin::SCHEDULER_BACKEND_OPTION, $backend );
			}
		}

		// 12 hours == old `twicedaily` interval. The scheduler only schedules
		// a fresh recurring action when one is not already registered.
		$scheduler->schedule_recurring(
			time(),
			12 * HOUR_IN_SECONDS,
			Admin::AUTO_PURGE_ACTION,
			array(),
			Admin::AUTO_PURGE_GROUP
		);
	}

	/**
	 * Deletes orphaned meta records from the database.
	 *
	 * Deletes meta records from the stream meta table where the corresponding
	 * stream record no longer exists.
	 *
	 * @global wpdb $wpdb The WordPress database object.
	 */
	public function delete_orphaned_meta() {
		global $wpdb;

		$wpdb->query(
			"DELETE `meta` FROM {$wpdb->streammeta} as `meta` LEFT JOIN {$wpdb->stream} as `stream` ON `stream`.`ID`=`meta`.`record_id` WHERE `stream`.`ID` IS NULL"
		);
	}

	/**
	 * Executes a scheduled purge
	 *
	 * @return void
	 */
	public function purge_scheduled_action() {
		// Respect the auto-purge master switch on the executing path too, not
		// just at scheduling time. A recurring action already in flight when
		// the filter flips to false (or an args-specific entry the unschedule
		// missed) would otherwise still run a purge cycle the operator opted
		// out of. This filter is documented in Admin::purge_schedule_setup().
		if ( ! apply_filters( 'wp_stream_enable_auto_purge', true ) ) {
			return;
		}

		// Don't purge when in Network Admin unless Stream is network activated.
		if (
			$this->admin->plugin->is_multisite_not_network_activated()
			&&
			is_network_admin()
		) {
			return;
		}

		$defaults = $this->admin->plugin->settings->get_defaults();
		if ( $this->admin->plugin->is_multisite_network_activated() ) {
			$options = wp_parse_args( (array) get_site_option( 'wp_stream_network', array() ), $defaults );
		} else {
			$options = wp_parse_args( (array) get_option( 'wp_stream', array() ), $defaults );
		}

		// TTL fallback. Settings::get_defaults() runs every settings field
		// through the `wp_stream_settings_option_fields` filter, which
		// Network::get_network_admin_fields() uses to strip the `records_ttl`
		// field from the per-site option's defaults set. When this callback runs
		// outside any admin context (Action Scheduler, WP-CLI, system cron), the
		// per-site option_key is in effect, so the filtered defaults array does
		// not contain general_records_ttl at all. Apply the documented 30-day
		// default (classes/class-settings.php, `records_ttl` field) only when
		// the key is genuinely missing, so an operator who set the value via
		// CLI/SQL keeps their explicit choice.
		if ( ! isset( $options['general_records_ttl'] ) ) {
			$options['general_records_ttl'] = 30;
		}

		if ( ! empty( $options['general_keep_records_indefinitely'] ) ) {
			return;
		}

		// Refuse to purge with a non-positive TTL. The UI enforces min=1, but
		// CLI/SQL can set 0 or a negative integer. Honoring those would mean
		// "delete every record on every cycle", which has no legitimate use
		// case (keep_records_indefinitely covers the opposite extreme).
		// Bailing out makes operator error visible (records stop being purged)
		// instead of catastrophic (records get wiped repeatedly).
		if ( (int) $options['general_records_ttl'] < 1 ) {
			return;
		}

		// Overlap guard: if any auto-purge action (batch worker or reaper) is
		// pending or in-progress, don't stack a new chain. Reuses the same
		// probe used by the Settings UI so the two views of "running" agree.
		if ( $this->is_running_auto_purge() ) {
			return;
		}

		/**
		 * Fires once per auto-purge cycle, after all bail-out checks pass and
		 * immediately before deletion work is enqueued.
		 *
		 * Preserved for backward compatibility with consumers that hooked the
		 * legacy WP-Cron event of the same name in Stream <= 4.1.x. Note that
		 * since 4.2.0 this fires only when a purge is actually about to run —
		 * it no longer fires on every cron tick regardless of whether work
		 * happens. Hook into the recurring AS action (Admin::AUTO_PURGE_ACTION)
		 * directly if you need the older "every tick" semantics.
		 */
		do_action( 'wp_stream_auto_purge' );

		// Snapshot the UTC cutoff once per recurring tick. Each batch in this
		// chain operates against this fixed cutoff so the chain is finite.
		$days   = (int) $options['general_records_ttl'];
		$cutoff = ( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) )
			->sub( DateInterval::createFromDateString( $days . ' days' ) )
			->format( 'Y-m-d H:i:s' );

		// blog_id = 0 means "all blogs" (network-activated path).
		$blog_id = $this->admin->plugin->is_multisite_not_network_activated() ? (int) get_current_blog_id() : 0;

		global $wpdb;

		// "Is this a large table?" decision matches the manual reset path
		// (Admin::erase_stream_records()). When the table is small the cost
		// of scheduling a chain (and waiting for AS to drain it on the next
		// runner tick) exceeds the cost of a single inline DELETE. Only fall
		// through to the batched chain when the filter says "yes, large".
		if ( $blog_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$record_count = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->stream} WHERE `blog_id` = %d", $blog_id )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$record_count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->stream}" );
		}

		if ( ! $this->admin->plugin->is_large_records_table( $record_count ) ) {
			// Small-table fast path: one inline multi-table DELETE, then enqueue
			// the orphan reaper as a one-shot async action so the heal step is
			// still observable in Tools → Scheduled Actions.
			if ( $blog_id > 0 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare(
						"DELETE `stream`, `meta`
						FROM {$wpdb->stream} AS `stream`
						LEFT JOIN {$wpdb->streammeta} AS `meta`
						ON `meta`.`record_id` = `stream`.`ID`
						WHERE `stream`.`created` < %s AND `stream`.`blog_id` = %d;",
						$cutoff,
						$blog_id
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare(
						"DELETE `stream`, `meta`
						FROM {$wpdb->stream} AS `stream`
						LEFT JOIN {$wpdb->streammeta} AS `meta`
						ON `meta`.`record_id` = `stream`.`ID`
						WHERE `stream`.`created` < %s;",
						$cutoff
					)
				);
			}

			$this->admin->plugin->scheduler->enqueue_async( Admin::AUTO_PURGE_REAPER_ACTION, array(), Admin::AUTO_PURGE_GROUP );
			return;
		}

		// Large-table path: batched chain.
		$this->admin->plugin->scheduler->enqueue_async(
			Admin::AUTO_PURGE_BATCH_ACTION,
			array(
				'cutoff'  => $cutoff,
				'blog_id' => $blog_id,
			),
			Admin::AUTO_PURGE_GROUP
		);

		$this->maybe_warn_large_table_without_action_scheduler(
			$record_count,
			__( 'delete records older than the retention period', 'stream' )
		);
	}

	/**
	 * Async Action Scheduler callback: delete one batch of records eligible
	 * under the snapshotted UTC cutoff, then chain the next batch (or the
	 * orphan reaper when nothing remains).
	 *
	 * Window-based deletion mirrors {@see Admin_Purge::erase_large_records()} so the
	 * InnoDB lock footprint is bounded and predictable on bloated tables.
	 *
	 * @param string $cutoff     MySQL DATETIME string in UTC.
	 * @param int    $blog_id    Blog to scope to, or 0 for all blogs (network-activated).
	 * @param int    $last_entry The lower-bound ID of the previous batch's window; 0 on the
	 *                           first batch in a chain. The next SELECT uses `ID < last_entry`
	 *                           when non-zero, guaranteeing forward progress even on tables
	 *                           that grow rapidly during the chain. Trade-off: any eligible
	 *                           row that lands inside the already-touched ID range
	 *                           [window_low, start_from] after that batch ran is skipped
	 *                           by the current chain and picked up on the next recurring
	 *                           tick (or small-table fast path). Possible sources: dev/test
	 *                           seeders, importer/migration plugins replaying historical
	 *                           rows, or PHP/MySQL clock skew on `created`. Steady-state
	 *                           logging via Log::log() uses monotonic IDs and current UTC,
	 *                           so this is a no-op for normal production traffic.
	 * @throws \InvalidArgumentException When $cutoff is empty (signals AS to mark the action as failed).
	 * @return void
	 */
	public function auto_purge_batch( $cutoff, $blog_id = 0, $last_entry = 0 ) {
		global $wpdb;

		$cutoff     = (string) $cutoff;
		$blog_id    = (int) $blog_id;
		$last_entry = (int) $last_entry;

		// Defensive: a malformed cutoff would otherwise translate to a no-op
		// DELETE that still busies the DB. Throw so Action Scheduler marks
		// the action as failed (and visible in Tools → Scheduled Actions)
		// rather than silently completing. In practice this is unreachable
		// because purge_scheduled_action() always populates the cutoff arg
		// and AS args are immutable; the guard exists for third-party code
		// that may enqueue the action with bad input.
		if ( '' === $cutoff ) {
			throw new \InvalidArgumentException( 'auto_purge_batch requires a non-empty cutoff.' );
		}

		// Best-effort "running" marker for schedulers without a native RUNNING
		// store (cron). Bridges the gap between this batch starting and the
		// next chained event being enqueued; self-expires on a fatal. No-op
		// under Action Scheduler. Cleared when the chain reaches its terminal
		// reaper (see the empty-$start_from branch below).
		$this->admin->plugin->scheduler->mark_running( 'auto_purge' );

		/**
		 * Filters the number of records to delete per batch.
		 *
		 * Shared with the manual reset path (see {@see Admin_Purge::erase_large_records()})
		 * so site owners only need to tune one knob.
		 *
		 * @since 4.1.0
		 *
		 * @param int $batch_size Default 250000.
		 */
		$batch_size = (int) apply_filters( 'wp_stream_batch_size', 250000 );
		if ( $batch_size < 1 ) {
			$batch_size = 250000;
		}

		// Find the highest-ID record still eligible under the snapshotted cutoff
		// that lies strictly below the previous window's lower bound (when set).
		// $last_entry=0 means "first batch in chain" — search from the top.
		if ( $blog_id > 0 && $last_entry > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$start_from = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->stream} WHERE `created` < %s AND `blog_id` = %d AND `ID` < %d ORDER BY ID DESC LIMIT 1",
					$cutoff,
					$blog_id,
					$last_entry
				)
			);
		} elseif ( $blog_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$start_from = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->stream} WHERE `created` < %s AND `blog_id` = %d ORDER BY ID DESC LIMIT 1",
					$cutoff,
					$blog_id
				)
			);
		} elseif ( $last_entry > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$start_from = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->stream} WHERE `created` < %s AND `ID` < %d ORDER BY ID DESC LIMIT 1",
					$cutoff,
					$last_entry
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$start_from = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->stream} WHERE `created` < %s ORDER BY ID DESC LIMIT 1",
					$cutoff
				)
			);
		}

		if ( empty( $start_from ) ) {
			// Chain is done. Schedule the orphan reaper as the terminal step.
			// The running marker is NOT cleared here: under WP-Cron the reaper
			// event is removed from the cron array before its callback runs,
			// so clearing now would let the overlap guard read "idle" while
			// the reaper's orphan-meta DELETE is still executing. The reaper
			// clears the marker itself when it finishes.
			$this->admin->plugin->scheduler->enqueue_async( Admin::AUTO_PURGE_REAPER_ACTION, array(), Admin::AUTO_PURGE_GROUP );
			return;
		}

		$start_from = (int) $start_from;
		$window_low = max( 0, $start_from - $batch_size );

		// Multi-table DELETE: parent + meta in one statement. Mirrors
		// Admin_Purge::erase_large_records().
		if ( $blog_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE `stream`, `meta`
					FROM {$wpdb->stream} AS `stream`
					LEFT JOIN {$wpdb->streammeta} AS `meta`
					ON `meta`.`record_id` = `stream`.`ID`
					WHERE `stream`.`ID` <= %d
					  AND `stream`.`ID` >= %d
					  AND `stream`.`created` < %s
					  AND `stream`.`blog_id` = %d;",
					$start_from,
					$window_low,
					$cutoff,
					$blog_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE `stream`, `meta`
					FROM {$wpdb->stream} AS `stream`
					LEFT JOIN {$wpdb->streammeta} AS `meta`
					ON `meta`.`record_id` = `stream`.`ID`
					WHERE `stream`.`ID` <= %d
					  AND `stream`.`ID` >= %d
					  AND `stream`.`created` < %s;",
					$start_from,
					$window_low,
					$cutoff
				)
			);
		}

		// Chain the next batch. Pass $window_low as the new upper bound so the
		// next SELECT cannot pick up rows in or above the window we just touched.
		$this->admin->plugin->scheduler->enqueue_async(
			Admin::AUTO_PURGE_BATCH_ACTION,
			array(
				'cutoff'     => $cutoff,
				'blog_id'    => $blog_id,
				'last_entry' => $window_low,
			),
			Admin::AUTO_PURGE_GROUP
		);
	}

	/**
	 * Terminal Action Scheduler callback for the auto-purge chain.
	 *
	 * Runs once per chain (after the last batch) and once when the manual
	 * "Clean orphaned meta now" button is used. Cleans up meta rows whose
	 * parent stream row is already gone — i.e. residue from historical
	 * unbatched purges and from any logger races during a chain.
	 *
	 * @return void
	 */
	public function auto_purge_reaper() {
		// Keep the overlap guard reading "busy" while the orphan-meta DELETE
		// runs. Under WP-Cron the event is removed from the cron array before
		// this callback executes, so without the marker a recurring purge
		// tick or a manual "clean orphaned meta" click could stack parallel
		// work against the same rows. No-op under Action Scheduler, which
		// tracks RUNNING state natively. Self-expires on a fatal.
		$this->admin->plugin->scheduler->mark_running( 'auto_purge' );

		$this->delete_orphaned_meta();

		$this->admin->plugin->scheduler->mark_done( 'auto_purge' );
	}
}
