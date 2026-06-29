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
		}
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
	 * purge_schedule_setup() registers the recurring event on WP-Cron using
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
		$this->assertFalse(
			(bool) get_transient( Cron_Scheduler::RUNNING_TRANSIENT ),
			'Running marker must be cleared once the chain reaches the reaper'
		);
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
