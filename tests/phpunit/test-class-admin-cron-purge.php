<?php
/**
 * Auto-purge / reset workflow tests exercised through the WP-Cron scheduler
 * fallback (issue #1907).
 *
 * The existing {@see Test_Admin} suite covers this workflow against Action
 * Scheduler. This suite swaps the plugin's scheduler for a Cron_Scheduler so
 * the same Admin code paths are asserted to chain batches, run the terminal
 * reaper, register the recurring event, and drive the overlap guard via
 * WP-Cron — with no Action Scheduler calls.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_Admin_Cron_Purge extends WP_StreamTestCase {

	/**
	 * Admin instance under test.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Scheduler that was active before this test swapped in the cron one.
	 *
	 * @var Scheduler
	 */
	protected $original_scheduler;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->plugin->admin;
		$this->assertNotEmpty( $this->admin );

		// Force the WP-Cron fallback for the duration of each test. Because
		// $this->plugin is the global instance, this also routes the static
		// is_running_* probes through the cron scheduler.
		$this->original_scheduler = $this->plugin->scheduler;
		$this->plugin->scheduler  = new Cron_Scheduler();

		$this->clear_purge_events();
	}

	public function tearDown(): void {
		$this->clear_purge_events();
		$this->plugin->scheduler = $this->original_scheduler;

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->stream}" );
		$wpdb->query( "DELETE FROM {$wpdb->streammeta}" );

		delete_option( 'wp_stream' );

		parent::tearDown();
	}

	/**
	 * Remove any scheduled purge events / markers left by a test.
	 */
	private function clear_purge_events() {
		wp_unschedule_hook( Admin::AUTO_PURGE_ACTION );
		wp_unschedule_hook( Admin::AUTO_PURGE_BATCH_ACTION );
		wp_unschedule_hook( Admin::AUTO_PURGE_REAPER_ACTION );
		wp_unschedule_hook( Admin::ASYNC_DELETION_ACTION );
		wp_clear_scheduled_hook( 'wp_stream_auto_purge' );
		delete_transient( Cron_Scheduler::RUNNING_TRANSIENT );
		delete_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );
	}

	/**
	 * Insert N stream rows aged $days_old days.
	 *
	 * @param int $count    Number of rows.
	 * @param int $days_old Age of each row's `created` column, in days.
	 * @return int[] Inserted stream IDs.
	 */
	private function seed_aged_records( int $count, int $days_old ): array {
		global $wpdb;
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$wpdb->insert(
				$wpdb->stream,
				array(
					'object_id' => null,
					'site_id'   => '1',
					'blog_id'   => get_current_blog_id(),
					'user_id'   => '1',
					'user_role' => 'administrator',
					'created'   => gmdate( 'Y-m-d H:i:s', strtotime( $days_old . ' days ago' ) ),
					'summary'   => 'cron purge test row',
					'ip'        => '192.168.0.1',
					'connector' => 'installer',
					'context'   => 'plugins',
					'action'    => 'activated',
				)
			);
			$stream_id = (int) $wpdb->insert_id;
			$ids[]     = $stream_id;
			$wpdb->insert(
				$wpdb->streammeta,
				array(
					'record_id'  => $stream_id,
					'meta_key'   => 'space_helmet',
					'meta_value' => 'false',
				)
			);
		}//end for

		return $ids;
	}

	/**
	 * Set the records TTL in whichever option applies on this install.
	 *
	 * @param int $days Retention window in days.
	 */
	private function set_records_ttl( int $days ) {
		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) ) {
			$options                        = (array) get_site_option( 'wp_stream_network', array() );
			$options['general_records_ttl'] = (string) $days;
			unset( $options['general_keep_records_indefinitely'] );
			update_site_option( 'wp_stream_network', $options );
		} else {
			$options                        = (array) get_option( 'wp_stream', array() );
			$options['general_records_ttl'] = (string) $days;
			unset( $options['general_keep_records_indefinitely'] );
			update_option( 'wp_stream', $options );
		}
	}

	/**
	 * A UTC cutoff one day in the past, matching purge_scheduled_action().
	 */
	private function cutoff_one_day_ago(): string {
		return ( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) )
			->sub( \DateInterval::createFromDateString( '1 days' ) )
			->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Purge_schedule_setup() registers the recurring event on WP-Cron using
	 * the custom 12-hour recurrence, clears the legacy event, and is idempotent.
	 */
	public function test_schedule_setup_registers_recurring_cron_event() {
		// Simulate a legacy WP-Cron event from older Stream versions.
		wp_schedule_event( time(), 'twicedaily', 'wp_stream_auto_purge' );
		$this->assertNotFalse( wp_next_scheduled( 'wp_stream_auto_purge' ) );

		$this->admin->purge_schedule_setup();

		$this->assertFalse(
			wp_next_scheduled( 'wp_stream_auto_purge' ),
			'Legacy WP-Cron event should be cleared'
		);
		$this->assertNotFalse(
			wp_next_scheduled( Admin::AUTO_PURGE_ACTION ),
			'Recurring purge event must be scheduled via WP-Cron'
		);
		$this->assertSame(
			Cron_Scheduler::RECURRENCE,
			wp_get_schedule( Admin::AUTO_PURGE_ACTION ),
			'Recurring event must use the custom 12-hour recurrence'
		);

		// Idempotent: a second call must not stack a duplicate.
		$first = wp_next_scheduled( Admin::AUTO_PURGE_ACTION );
		$this->admin->purge_schedule_setup();
		$this->assertSame( $first, wp_next_scheduled( Admin::AUTO_PURGE_ACTION ) );
	}

	/**
	 * Small-table fast path: an inline DELETE removes eligible rows, then the
	 * reaper is enqueued on WP-Cron and no batch chain is scheduled.
	 */
	public function test_small_table_fast_path_deletes_inline_and_enqueues_reaper() {
		global $wpdb;

		$ids = $this->seed_aged_records( 2, 5 );
		$this->set_records_ttl( 1 );

		$this->admin->purge_scheduled_action();

		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $wpdb->stream . ' WHERE ID IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				...$ids
			)
		);
		$this->assertSame( 0, $remaining, 'Eligible rows must be deleted inline' );

		$this->assertFalse(
			$this->plugin->scheduler->has_scheduled( Admin::AUTO_PURGE_BATCH_ACTION ),
			'Small-table path must not schedule a batch chain'
		);
		$this->assertTrue(
			$this->plugin->scheduler->has_scheduled( Admin::AUTO_PURGE_REAPER_ACTION ),
			'Small-table path must enqueue the orphan reaper on WP-Cron'
		);
	}

	/**
	 * Large-table path: a batch chain is scheduled on WP-Cron and the reaper
	 * is left to the terminal batch.
	 */
	public function test_large_table_schedules_batch_chain() {
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );

		$this->seed_aged_records( 2, 5 );
		$this->set_records_ttl( 1 );

		$this->admin->purge_scheduled_action();

		$this->assertTrue(
			$this->plugin->scheduler->has_scheduled( Admin::AUTO_PURGE_BATCH_ACTION ),
			'Large table must schedule the batched chain on WP-Cron'
		);
		$this->assertFalse(
			$this->plugin->scheduler->has_scheduled( Admin::AUTO_PURGE_REAPER_ACTION ),
			'Reaper is scheduled by the terminal batch, not the recurring callback'
		);

		remove_all_filters( 'wp_stream_is_large_records_table' );
	}

	/**
	 * The batch worker deletes a window and chains the next batch on WP-Cron
	 * while rows remain.
	 */
	public function test_batch_deletes_window_and_chains_next_via_cron() {
		global $wpdb;

		add_filter(
			'wp_stream_batch_size',
			function () {
				return 2;
			}
		);

		$this->seed_aged_records( 5, 5 );
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->stream}" );

		$this->admin->auto_purge_batch( $this->cutoff_one_day_ago(), 0 );

		$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->stream}" );
		$this->assertLessThan( $before, $remaining, 'Batch must delete at least one row' );
		$this->assertGreaterThan( 0, $remaining, 'Batch must not exceed one window' );

		$this->assertTrue(
			$this->plugin->scheduler->has_scheduled( Admin::AUTO_PURGE_BATCH_ACTION ),
			'Next batch must be chained on WP-Cron while rows remain'
		);

		remove_all_filters( 'wp_stream_batch_size' );
	}

	/**
	 * When nothing is eligible, the batch worker schedules the terminal reaper
	 * (and not another batch) and clears the running marker.
	 */
	public function test_batch_enqueues_reaper_and_clears_marker_when_done() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->stream}" );
		$wpdb->query( "DELETE FROM {$wpdb->streammeta}" );

		$this->admin->auto_purge_batch( $this->cutoff_one_day_ago(), 0 );

		$this->assertFalse(
			$this->plugin->scheduler->has_scheduled( Admin::AUTO_PURGE_BATCH_ACTION ),
			'No further batch must be chained when nothing is eligible'
		);
		$this->assertTrue(
			$this->plugin->scheduler->has_scheduled( Admin::AUTO_PURGE_REAPER_ACTION ),
			'Terminal reaper must be scheduled on WP-Cron'
		);
		$this->assertTrue(
			(bool) get_transient( Cron_Scheduler::RUNNING_TRANSIENT ),
			'Running marker must remain set until the reaper has executed'
		);

		// The reaper clears the marker when it finishes.
		$this->admin->auto_purge_reaper();
		$this->assertFalse(
			(bool) get_transient( Cron_Scheduler::RUNNING_TRANSIENT ),
			'Running marker must be cleared once the reaper completes'
		);
	}

	/**
	 * The `wp_stream_enable_auto_purge` filter, returning false, unschedules
	 * any recurring purge and skips re-registering it — for storage drivers
	 * that manage retention externally.
	 */
	public function test_enable_auto_purge_filter_disables_scheduling() {
		// Establish a recurring purge first.
		$this->admin->purge_schedule_setup();
		$this->assertNotFalse(
			wp_next_scheduled( Admin::AUTO_PURGE_ACTION ),
			'Recurring purge must be scheduled before the disable filter is applied'
		);

		add_filter( 'wp_stream_enable_auto_purge', '__return_false' );
		$this->admin->purge_schedule_setup();

		$this->assertFalse(
			wp_next_scheduled( Admin::AUTO_PURGE_ACTION ),
			'Disabling auto-purge must unschedule the recurring event'
		);
		$this->assertSame(
			'disabled',
			get_option( Admin::SCHEDULER_BACKEND_OPTION ),
			'Backend marker must record the disabled sentinel so the teardown runs only once'
		);

		// Re-enabling must recover: the sentinel differs from the active
		// backend, so the switch cleanup re-registers the recurring purge.
		remove_all_filters( 'wp_stream_enable_auto_purge' );
		$this->admin->purge_schedule_setup();
		$this->assertNotFalse(
			wp_next_scheduled( Admin::AUTO_PURGE_ACTION ),
			'Re-enabling auto-purge must re-register the recurring event'
		);
	}

	/**
	 * The executing path honors the master switch too: a purge callback that
	 * fires while `wp_stream_enable_auto_purge` is false must be a no-op, so a
	 * stale in-flight event cannot delete records the operator opted out of.
	 */
	public function test_enable_auto_purge_filter_blocks_executing_purge() {
		global $wpdb;

		$ids = $this->seed_aged_records( 2, 5 );
		$this->set_records_ttl( 1 );

		add_filter( 'wp_stream_enable_auto_purge', '__return_false' );
		$this->admin->purge_scheduled_action();
		remove_all_filters( 'wp_stream_enable_auto_purge' );

		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $wpdb->stream . ' WHERE ID IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				...$ids
			)
		);
		$this->assertSame(
			count( $ids ),
			$remaining,
			'No records may be purged while auto-purge is disabled'
		);
	}

	/**
	 * On the WP-Cron fallback, queueing a large-table batch persists a warning
	 * pointing the operator at a deterministic WP-CLI drain, and the persisted
	 * warning is rendered (once) on the next admin page load.
	 */
	public function test_large_table_on_cron_persists_and_renders_admin_notice() {
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );

		delete_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );

		$this->seed_aged_records( 2, 5 );
		$this->set_records_ttl( 1 );

		$this->admin->purge_scheduled_action();

		$stored = get_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );
		$this->assertNotEmpty(
			$stored,
			'A WP-CLI guidance warning must be persisted for large tables on the cron backend'
		);
		$this->assertStringContainsString(
			'wp cron event run',
			$stored,
			'The persisted warning must include the WP-CLI drain command'
		);

		// The persisted warning renders on the next admin page load for a
		// capable user, then clears so it does not repeat.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}

		ob_start();
		$this->admin->display_large_table_cron_notice();
		$rendered = ob_get_clean();

		$this->assertStringContainsString(
			'wp cron event run',
			$rendered,
			'The persisted warning must render as an admin notice'
		);
		$this->assertEmpty(
			get_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION ),
			'The persisted warning must be cleared after rendering'
		);

		ob_start();
		$this->admin->display_large_table_cron_notice();
		$second = ob_get_clean();
		$this->assertEmpty( $second, 'The warning must render only once' );

		remove_all_filters( 'wp_stream_is_large_records_table' );
	}

	/**
	 * The persisted warning must not render for users without the Stream
	 * settings capability.
	 */
	public function test_large_table_cron_notice_requires_capability() {
		update_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION, 'run wp cron event run --due-now', false );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		$this->admin->display_large_table_cron_notice();
		$rendered = ob_get_clean();

		$this->assertEmpty( $rendered, 'Users without the settings capability must not see the warning' );
		$this->assertNotEmpty(
			get_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION ),
			'The persisted warning must remain stored for a capable user'
		);

		delete_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );
	}

	/**
	 * The large-table notice must NOT fire when auto-purge is disabled via
	 * `wp_stream_enable_auto_purge` — there is no purge to warn about.
	 */
	public function test_large_table_notice_suppressed_when_auto_purge_disabled() {
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );
		add_filter( 'wp_stream_enable_auto_purge', '__return_false' );

		delete_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );

		$this->seed_aged_records( 2, 5 );
		$this->set_records_ttl( 1 );

		$this->admin->purge_scheduled_action();

		$this->assertEmpty(
			get_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION ),
			'No warning must be persisted when auto-purge is disabled'
		);

		remove_all_filters( 'wp_stream_enable_auto_purge' );
		remove_all_filters( 'wp_stream_is_large_records_table' );
	}

	/**
	 * The `wp_stream_enable_auto_purge` filter must NOT suppress the stall
	 * warning for the manual database reset: it governs TTL retention purging
	 * only, and an operator managing retention externally can still trigger a
	 * large reset that needs the WP-Cron stall guidance.
	 */
	public function test_reset_warning_not_suppressed_by_auto_purge_filter() {
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );
		add_filter( 'wp_stream_enable_auto_purge', '__return_false' );

		delete_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );

		$method = new \ReflectionMethod( Admin::class, 'maybe_warn_large_table_without_action_scheduler' );
		$method->setAccessible( true );
		$method->invoke( $this->admin, 2000000, 'reset the Stream database (delete all records for this site)' );

		$stored = get_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );
		$this->assertNotEmpty(
			$stored,
			'The reset stall warning must fire even when auto-purge is disabled'
		);
		$this->assertStringContainsString( 'wp cron event run', $stored );

		delete_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );
		remove_all_filters( 'wp_stream_enable_auto_purge' );
		remove_all_filters( 'wp_stream_is_large_records_table' );
	}

	/**
	 * The large-table notice must NOT fire on the Action Scheduler backend,
	 * which is built to drain long batch chains reliably.
	 */
	public function test_large_table_on_action_scheduler_does_not_warn() {
		if ( ! class_exists( AS_Scheduler::class ) ) {
			$this->markTestSkipped( 'Action Scheduler is not loaded in this environment.' );
		}

		$this->plugin->scheduler = new AS_Scheduler();
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );

		delete_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION );

		$this->seed_aged_records( 2, 5 );
		$this->set_records_ttl( 1 );

		$this->admin->purge_scheduled_action();

		$this->assertEmpty(
			get_option( Admin::LARGE_TABLE_CRON_NOTICE_OPTION ),
			'No WP-CLI guidance warning must be persisted when Action Scheduler is the backend'
		);

		remove_all_filters( 'wp_stream_is_large_records_table' );
	}

	/**
	 * The manual-reset chain sets the running marker while a batch executes
	 * (mirroring auto_purge_batch), so is_running_async_deletion() cannot
	 * momentarily read idle mid-chain, and clears it on the terminal batch.
	 */
	public function test_erase_large_records_marks_running_and_clears_when_done() {
		global $wpdb;

		add_filter(
			'wp_stream_batch_size',
			function () {
				return 2;
			}
		);

		$ids  = $this->seed_aged_records( 5, 5 );
		$last = max( $ids );

		// Non-terminal batch: marker set, next batch chained.
		$this->admin->erase_large_records( 5, 0, $last, get_current_blog_id() );

		$this->assertTrue(
			(bool) get_transient( Cron_Scheduler::RUNNING_TRANSIENT ),
			'Running marker must be set while the reset chain is mid-flight'
		);
		$this->assertTrue(
			Admin::is_running_async_deletion(),
			'is_running_async_deletion() must read busy while the chain is pending'
		);

		// Drain: run remaining batches directly until the terminal one.
		$wpdb->query( "DELETE FROM {$wpdb->stream}" );
		wp_unschedule_hook( Admin::ASYNC_DELETION_ACTION );
		$this->admin->erase_large_records( 5, 5, $last, get_current_blog_id() );

		$this->assertFalse(
			(bool) get_transient( Cron_Scheduler::RUNNING_TRANSIENT ),
			'Terminal batch must clear the running marker'
		);
		$this->assertFalse(
			Admin::is_running_async_deletion(),
			'is_running_async_deletion() must read idle after the chain completes'
		);

		remove_all_filters( 'wp_stream_batch_size' );
	}

	/**
	 * A running batch chain marks the overlap guard as busy via the cron
	 * scheduler, so is_running_auto_purge() reports true even mid-chain.
	 */
	public function test_is_running_auto_purge_reflects_cron_state() {
		$this->assertFalse(
			Admin::is_running_auto_purge(),
			'Guard must read idle when nothing is scheduled or running'
		);

		add_filter(
			'wp_stream_batch_size',
			function () {
				return 2;
			}
		);
		$this->seed_aged_records( 5, 5 );

		// First batch deletes a window and chains the next batch.
		$this->admin->auto_purge_batch( $this->cutoff_one_day_ago(), 0 );

		$this->assertTrue(
			Admin::is_running_auto_purge(),
			'Guard must read busy while a batch chain is pending on WP-Cron'
		);

		remove_all_filters( 'wp_stream_batch_size' );
	}
}
