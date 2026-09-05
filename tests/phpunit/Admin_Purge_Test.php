<?php
/**
 * Tests for Admin_Purge (Action Scheduler path).
 *
 * WP-Cron fallback coverage lives in {@see Admin_Cron_Purge_Test}.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Admin_Purge_Test extends WP_StreamTestCase {

	/**
	 * Holds the admin base class.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Purge collaborator under test.
	 *
	 * @var Admin_Purge
	 */
	protected $purge;

	/**
	 * Hit counter for BC action listeners.
	 *
	 * @var int
	 */
	private static $bc_action_hits = 0;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->plugin->admin;
		$this->assertNotEmpty( $this->admin );
		$this->purge          = $this->get_admin_collaborator( $this->admin, 'purge' );
		self::$bc_action_hits = 0;
	}

	/**
	 * Named callable for wp_stream_auto_purge hit counting.
	 *
	 * @return void
	 */
	public static function count_bc_action_hit(): void {
		++self::$bc_action_hits;
	}

	/**
	 * Named filter returning batch size 2.
	 *
	 * @return int
	 */
	public static function filter_batch_size_two(): int {
		return 2;
	}

	/**
	 * Named filter returning batch size 3.
	 *
	 * @return int
	 */
	public static function filter_batch_size_three(): int {
		return 3;
	}

	private function dummy_stream_data() {
		return array(
			'object_id' => null,
			'site_id'   => '1',
			'blog_id'   => get_current_blog_id(),
			'user_id'   => '1',
			'user_role' => 'administrator',
			'created'   => gmdate( 'Y-m-d H:i:s' ),
			'summary'   => '"Hello Dave" plugin activated',
			'ip'        => '192.168.0.1',
			'connector' => 'installer',
			'context'   => 'plugins',
			'action'    => 'activated',
		);
	}

	private function dummy_stream_data_other_blog() {
		return array(
			'object_id' => null,
			'site_id'   => '1',
			'blog_id'   => (int) get_current_blog_id() + 1,
			'user_id'   => '1',
			'user_role' => 'administrator',
			'created'   => gmdate( 'Y-m-d H:i:s' ),
			'summary'   => '"Hello Dave" plugin activated',
			'ip'        => '192.168.0.1',
			'connector' => 'installer',
			'context'   => 'plugins',
			'action'    => 'activated',
		);
	}

	private function dummy_meta_data( $stream_id ) {
		return array(
			'record_id'  => $stream_id,
			'meta_key'   => 'space_helmet',
			'meta_value' => 'false',
		);
	}

	/**
	 * Insert N stream rows aged $days_old days, optionally pinned to a blog id.
	 *
	 * @param int      $count    Number of rows to insert.
	 * @param int      $days_old How many days ago `created` should be set to.
	 * @param int|null $blog_id  Optional blog id override.
	 * @return int[] Inserted stream IDs.
	 */
	private function seed_aged_records( int $count, int $days_old, $blog_id = null ): array {
		global $wpdb;
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$row            = $this->dummy_stream_data();
			$row['created'] = gmdate( 'Y-m-d H:i:s', strtotime( $days_old . ' days ago' ) );
			if ( null !== $blog_id ) {
				$row['blog_id'] = $blog_id;
			}
			$wpdb->insert( $wpdb->stream, $row );
			$stream_id = (int) $wpdb->insert_id;
			$ids[]     = $stream_id;
			$wpdb->insert( $wpdb->streammeta, $this->dummy_meta_data( $stream_id ) );
		}
		return $ids;
	}

	/**
	 * Set the records TTL in whichever option applies on this install.
	 *
	 * @param int $days Number of days to retain records for.
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

	public function test_purge_schedule_setup_uses_action_scheduler_and_unschedules_wp_cron() {
		// Simulate a pre-existing legacy WP-Cron event from older Stream versions.
		wp_clear_scheduled_hook( 'wp_stream_auto_purge' );
		wp_schedule_event( time(), 'twicedaily', 'wp_stream_auto_purge' );
		$this->assertNotFalse( wp_next_scheduled( 'wp_stream_auto_purge' ) );

		// Make sure AS has no purge actions queued.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_ACTION );
		}

		$this->purge->purge_schedule_setup();

		// Legacy WP-Cron event is gone.
		$this->assertFalse(
			wp_next_scheduled( 'wp_stream_auto_purge' ),
			'Legacy wp_stream_auto_purge WP-Cron event should be cleared'
		);

		// Recurring AS action is scheduled.
		$this->assertNotFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_ACTION ),
			'Recurring AS auto-purge action should be scheduled'
		);

		// Idempotent: calling it again must not schedule a second recurring action.
		$this->purge->purge_schedule_setup();
		$ids = as_get_scheduled_actions(
			array(
				'hook'   => \WP_Stream\Admin::AUTO_PURGE_ACTION,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);
		$this->assertCount( 1, $ids, 'purge_schedule_setup() must be idempotent' );
	}

	public function test_purge_scheduled_action_fires_bc_action_once_when_work_runs() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		self::$bc_action_hits = 0;
		add_action( 'wp_stream_auto_purge', array( self::class, 'count_bc_action_hit' ) );

		// Make sure something is eligible so we exercise the full code path.
		$this->seed_aged_records( 1, 5 );
		$this->set_records_ttl( 1 );

		$this->purge->purge_scheduled_action();

		remove_action( 'wp_stream_auto_purge', array( self::class, 'count_bc_action_hit' ) );
		$this->assertSame( 1, self::$bc_action_hits, 'wp_stream_auto_purge action must fire exactly once per recurring tick when work runs' );
	}

	public function test_purge_scheduled_action_does_not_fire_bc_action_when_cycle_bails() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		// keep_records_indefinitely=1 is one of the bail-out conditions.
		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) ) {
			update_site_option( 'wp_stream_network', array( 'general_keep_records_indefinitely' => 1 ) );
		} else {
			update_option( 'wp_stream', array( 'general_keep_records_indefinitely' => 1 ) );
		}

		self::$bc_action_hits = 0;
		add_action( 'wp_stream_auto_purge', array( self::class, 'count_bc_action_hit' ) );

		$this->purge->purge_scheduled_action();

		remove_action( 'wp_stream_auto_purge', array( self::class, 'count_bc_action_hit' ) );
		$this->assertSame(
			0,
			self::$bc_action_hits,
			'wp_stream_auto_purge BC action must not fire when the cycle bails out (keep_records_indefinitely)'
		);
	}

	public function test_purge_scheduled_action_small_table_fast_path() {
		// Default: table is "small" (filter returns false for record_count <= 1M).
		global $wpdb;
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}
		$ids = $this->seed_aged_records( 2, 5 );
		$this->set_records_ttl( 1 );

		$this->purge->purge_scheduled_action();

		// Inline DELETE must have run — rows are gone.
		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->stream} WHERE ID IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				...$ids
			)
		);
		$this->assertSame( 0, $remaining, 'Small-table fast path must delete eligible rows inline' );

		// No batched chain was enqueued.
		$this->assertFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION ),
			'Small-table fast path must not enqueue a batched chain'
		);

		// Reaper still runs so the heal step is observable in Scheduled Actions.
		$this->assertNotFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION ),
			'Small-table fast path must still enqueue the orphan reaper'
		);
	}

	public function test_purge_scheduled_action_large_table_uses_batched_chain() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}
		// Force the "large table" branch without seeding 1M rows.
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );

		$this->seed_aged_records( 2, 5 );
		$this->set_records_ttl( 1 );

		$this->purge->purge_scheduled_action();

		$this->assertNotFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION ),
			'Large table must enqueue the batched chain'
		);
		$this->assertFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION ),
			'Reaper is enqueued by the terminal batch worker, not by the recurring callback'
		);

		remove_filter( 'wp_stream_is_large_records_table', '__return_true' );
	}

	public function test_purge_scheduled_action_enqueues_first_batch_with_snapshotted_cutoff() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
		}

		// Force the batched path so we can assert batch args.
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );

		$this->seed_aged_records( 1, 5 );
		$this->set_records_ttl( 1 );

		$this->purge->purge_scheduled_action();

		$scheduled = as_get_scheduled_actions(
			array(
				'hook'   => \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			)
		);
		$this->assertNotEmpty( $scheduled, 'A first batch must be enqueued when records are eligible' );

		$action = array_shift( $scheduled );
		$args   = $action->get_args();
		$this->assertArrayHasKey( 'cutoff', $args );
		$this->assertArrayHasKey( 'blog_id', $args );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$args['cutoff'],
			'Cutoff must be a MySQL DATETIME string'
		);

		remove_filter( 'wp_stream_is_large_records_table', '__return_true' );
	}

	public function test_purge_scheduled_action_respects_keep_indefinitely() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
		}
		$this->seed_aged_records( 1, 5 );

		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) ) {
			update_site_option( 'wp_stream_network', array( 'general_keep_records_indefinitely' => 1 ) );
		} else {
			update_option( 'wp_stream', array( 'general_keep_records_indefinitely' => 1 ) );
		}

		$this->purge->purge_scheduled_action();

		$this->assertFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION ),
			'No batch must be enqueued when keep-records-indefinitely is on'
		);
	}

	public function test_purge_scheduled_action_applies_defaults_when_option_missing() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
		}
		// Drop the option entirely.
		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) ) {
			delete_site_option( 'wp_stream_network' );
		} else {
			delete_option( 'wp_stream' );
		}

		// Force the batched path so the assertion targets a batch enqueue.
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );

		// Seed records older than the default 30-day TTL.
		$this->seed_aged_records( 1, 31 );

		$this->purge->purge_scheduled_action();

		$this->assertNotFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION ),
			'Defaults (30-day TTL) must apply when the settings option is missing'
		);

		remove_filter( 'wp_stream_is_large_records_table', '__return_true' );
	}

	public function test_purge_scheduled_action_overlap_guard_skips_when_batch_already_pending() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
		}
		// Overlap guard only applies to the batched chain path.
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );

		$this->seed_aged_records( 1, 5 );
		$this->set_records_ttl( 1 );

		// First call enqueues a batch.
		$this->purge->purge_scheduled_action();
		$first = as_get_scheduled_actions(
			array(
				'hook'   => \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);
		$this->assertCount( 1, $first );

		// Second call must be a no-op.
		$this->purge->purge_scheduled_action();
		$second = as_get_scheduled_actions(
			array(
				'hook'   => \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);
		$this->assertCount( 1, $second, 'Overlap guard must prevent stacking a second batch chain' );

		remove_filter( 'wp_stream_is_large_records_table', '__return_true' );
	}

	public function test_purge_scheduled_action_overlap_guard_skips_when_reaper_pending() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );

		// Simulate the post-chain state: only the reaper is left pending.
		as_enqueue_async_action(
			\WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION,
			array(),
			\WP_Stream\Admin::AUTO_PURGE_GROUP
		);

		$this->seed_aged_records( 1, 5 );
		$this->set_records_ttl( 1 );

		$this->purge->purge_scheduled_action();

		$this->assertFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION ),
			'Overlap guard must skip when only the reaper is pending'
		);

		remove_filter( 'wp_stream_is_large_records_table', '__return_true' );
	}

	public function test_purge_scheduled_action_bails_when_ttl_is_zero_or_negative() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
		}
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );

		$this->seed_aged_records( 1, 5 );

		// TTL=0 (operator error via CLI/SQL). Must not delete anything.
		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) ) {
			update_site_option( 'wp_stream_network', array( 'general_records_ttl' => '0' ) );
		} else {
			update_option( 'wp_stream', array( 'general_records_ttl' => '0' ) );
		}

		$this->purge->purge_scheduled_action();

		$this->assertFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION ),
			'Non-positive TTL must short-circuit the recurring callback'
		);

		remove_filter( 'wp_stream_is_large_records_table', '__return_true' );
	}

	public function test_settings_ttl_shortened_triggers_immediate_purge() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		$this->seed_aged_records( 1, 5 );

		// Simulate the option-changed event: TTL shortened from 30 to 7.
		$this->plugin->settings->updated_option_ttl_remove_records(
			array( 'general_records_ttl' => 30 ),
			array( 'general_records_ttl' => 7 )
		);

		// The TTL-shortened path enqueues the recurring AS action as a
		// one-shot async action so work serializes through AS rather than
		// running inline (which would bypass the overlap guard).
		$async = as_get_scheduled_actions(
			array(
				'hook'   => \WP_Stream\Admin::AUTO_PURGE_ACTION,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);
		$this->assertNotEmpty(
			$async,
			'Shortening TTL must enqueue an immediate auto-purge action via Action Scheduler'
		);
	}

	/**
	 * Exercises the full hook wiring for the TTL-shortened path: writes the
	 * option via update_option() / update_site_option() and asserts the AS
	 * enqueue happened. The unit test above invokes the handler directly,
	 * which would still pass if the underlying hook registration regressed
	 * (e.g. someone removed Network::updated_option_ttl_remove_records()
	 * that bridges update_site_option_wp_stream_network → Settings).
	 *
	 * Branches by CI lane: single-site lane fires update_option('wp_stream');
	 * multisite (network-activated) lane fires update_site_option('wp_stream_network').
	 * Both must end up enqueuing AUTO_PURGE_ACTION.
	 */
	public function test_settings_ttl_shortened_via_option_update_enqueues_purge() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		// Seed a baseline value of 30 days, then shorten to 7. Both writes
		// go through the real WP hook chain (update_option_* or
		// update_site_option_*), which is the wiring under test.
		$this->set_records_ttl( 30 );

		// Clear anything the baseline write may have enqueued so the
		// assertion below targets the shortening event only.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_ACTION );
		}

		$this->set_records_ttl( 7 );

		$async = as_get_scheduled_actions(
			array(
				'hook'   => \WP_Stream\Admin::AUTO_PURGE_ACTION,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);
		$this->assertNotEmpty(
			$async,
			'Updating the TTL via the option API must enqueue AUTO_PURGE_ACTION through the registered hooks'
		);
	}

	public function test_auto_purge_reaper_deletes_orphaned_meta_only() {
		global $wpdb;

		// Seed a real record with meta, then a free-floating meta row pointing at
		// a non-existent record_id.
		$stream_data            = $this->dummy_stream_data();
		$stream_data['created'] = gmdate( 'Y-m-d H:i:s', strtotime( '5 days ago' ) );
		$wpdb->insert( $wpdb->stream, $stream_data );
		$real_id = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->streammeta, $this->dummy_meta_data( $real_id ) );

		// Orphan meta: record_id points nowhere.
		$orphan_record_id = $real_id + 999999;
		$wpdb->insert( $wpdb->streammeta, $this->dummy_meta_data( $orphan_record_id ) );

		$before_orphans = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->streammeta} WHERE record_id = %d", $orphan_record_id )
		);
		$this->assertSame( 1, $before_orphans );

		$this->purge->auto_purge_reaper();

		$after_orphans = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->streammeta} WHERE record_id = %d", $orphan_record_id )
		);
		$linked_meta   = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->streammeta} WHERE record_id = %d", $real_id )
		);

		$this->assertSame( 0, $after_orphans, 'Reaper must delete meta rows whose parent stream row is absent' );
		$this->assertSame( 1, $linked_meta, 'Reaper must not touch meta rows whose parent still exists' );
	}

	public function test_auto_purge_batch_deletes_window_and_chains_next_batch() {
		global $wpdb;

		// Force a small batch size so we can chain twice without seeding huge data.
		add_filter( 'wp_stream_batch_size', array( self::class, 'filter_batch_size_two' ) );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		// Seed 5 aged rows. With batch_size=2 the chain runs 3 batches + reaper.
		$this->seed_aged_records( 5, 5 );

		$cutoff = ( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) )
			->sub( \DateInterval::createFromDateString( '1 days' ) )
			->format( 'Y-m-d H:i:s' );

		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->stream}" );

		$this->purge->auto_purge_batch( $cutoff, 0 );

		$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->stream}" );
		$this->assertLessThan( $before, $remaining, 'Batch must delete at least one row' );
		$this->assertGreaterThan( 0, $remaining, 'Batch must not delete more than one window of rows' );

		$this->assertNotFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION ),
			'Next batch must be chained when more eligible rows remain'
		);

		remove_all_filters( 'wp_stream_batch_size' );
	}

	public function test_auto_purge_batch_throws_on_empty_cutoff() {
		$this->expectException( \InvalidArgumentException::class );
		$this->purge->auto_purge_batch( '', 0, 0 );
	}

	public function test_auto_purge_batch_enqueues_reaper_when_no_rows_remain() {
		global $wpdb;
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}
		// Wipe any leftover rows from earlier tests so nothing is eligible.
		$wpdb->query( "DELETE FROM {$wpdb->stream}" );
		$wpdb->query( "DELETE FROM {$wpdb->streammeta}" );

		$cutoff = ( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) )
			->sub( \DateInterval::createFromDateString( '1 days' ) )
			->format( 'Y-m-d H:i:s' );

		$this->purge->auto_purge_batch( $cutoff, 0 );

		$this->assertFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION ),
			'No further batch must be chained when nothing is eligible'
		);
		$this->assertNotFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION ),
			'Reaper must be enqueued as the terminal step of the chain'
		);
	}

	public function test_auto_purge_batch_chain_strides_down_by_window() {
		global $wpdb;

		// Force a small batch size so we can chain multiple times.
		add_filter( 'wp_stream_batch_size', array( self::class, 'filter_batch_size_three' ) );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		$ids = $this->seed_aged_records( 4, 5 );
		sort( $ids );
		$top_id = end( $ids );

		$cutoff = ( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) )
			->sub( \DateInterval::createFromDateString( '1 days' ) )
			->format( 'Y-m-d H:i:s' );

		// First batch (last_entry=0) should pick the highest ID and pass
		// last_entry = top_id - batch_size to the next batch.
		$this->purge->auto_purge_batch( $cutoff, 0, 0 );

		$pending = as_get_scheduled_actions(
			array(
				'hook'   => \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			)
		);
		$this->assertNotEmpty( $pending );
		$next_args = array_shift( $pending )->get_args();

		$this->assertArrayHasKey( 'last_entry', $next_args );
		$this->assertSame(
			max( 0, $top_id - 3 ),
			(int) $next_args['last_entry'],
			'Next batch must receive last_entry = top_id - batch_size'
		);

		remove_all_filters( 'wp_stream_batch_size' );
	}

	/**
	 * Acceptance criterion: "Per-site activations only purge the current blog."
	 *
	 * The batch worker scoping is covered above
	 * ({@see test_auto_purge_batch_scopes_to_blog_id_when_non_zero}); this
	 * test closes the gap on the routing decision in
	 * {@see Admin_Purge::purge_scheduled_action()}:
	 *
	 *     $blog_id = $this->plugin->is_multisite_not_network_activated()
	 *         ? (int) get_current_blog_id()
	 *         : 0;
	 *
	 * Forces is_multisite_not_network_activated() to return true via a Plugin
	 * stub (CI's multisite lane runs with network-activated = true), then
	 * asserts the enqueued batch carries the current blog_id rather than 0.
	 */
	public function test_purge_scheduled_action_scopes_to_current_blog_when_not_network_activated() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Per-site scoping is multisite-only' );
		}
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
		}

		// Force the batched path so the assertion can read $args['blog_id'].
		add_filter( 'wp_stream_is_large_records_table', '__return_true' );

		$current_blog = (int) get_current_blog_id();
		$this->seed_aged_records( 1, 5, $current_blog );
		$this->set_records_ttl( 1 );

		// Swap in a Plugin stub that reports per-site activation.
		$real_plugin         = $this->admin->plugin;
		$stub                = new class( $real_plugin ) {
			public $settings;
			public $db;
			public $locations;
			public $admin;
			public $connectors;
			public $scheduler;
			public function __construct( $real ) {
				$this->settings   = $real->settings;
				$this->db         = $real->db;
				$this->locations  = $real->locations;
				$this->admin      = $real->admin;
				$this->connectors = $real->connectors;
				$this->scheduler  = $real->scheduler;
			}
			public function is_multisite_not_network_activated() {
				return true;
			}
			public function is_multisite_network_activated() {
				return false;
			}
			public function is_large_records_table( int $n ): bool {
				return apply_filters( 'wp_stream_is_large_records_table', $n > 1000000, $n );
			}
			public function __call( $name, $args ) {
				return call_user_func_array( array( $this->settings->plugin ?? null, $name ), $args );
			}
		};
		$this->admin->plugin = $stub;

		try {
			$this->purge->purge_scheduled_action();

			$scheduled = as_get_scheduled_actions(
				array(
					'hook'   => \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION,
					'status' => \ActionScheduler_Store::STATUS_PENDING,
				)
			);
			$this->assertNotEmpty(
				$scheduled,
				'Per-site activation must still enqueue a batch when records are eligible'
			);

			$action = array_shift( $scheduled );
			$args   = $action->get_args();
			$this->assertSame(
				$current_blog,
				(int) $args['blog_id'],
				'Per-site activation must scope the batch to the current blog (not 0 / all blogs)'
			);
		} finally {
			$this->admin->plugin = $real_plugin;
			remove_filter( 'wp_stream_is_large_records_table', '__return_true' );
		}//end try
	}

	public function test_auto_purge_batch_scopes_to_blog_id_when_non_zero() {
		global $wpdb;
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite scoping test' );
		}

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		$current_blog = (int) get_current_blog_id();
		$other_blog   = $current_blog + 1000;
		// arbitrary distinct id, no real blog required for SQL scoping.

		$this->seed_aged_records( 1, 5, $current_blog );
		$this->seed_aged_records( 1, 5, $other_blog );

		$cutoff = ( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) )
			->sub( \DateInterval::createFromDateString( '1 days' ) )
			->format( 'Y-m-d H:i:s' );

		$this->purge->auto_purge_batch( $cutoff, $current_blog );

		$remaining_other = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->stream} WHERE blog_id = %d", $other_blog )
		);
		$this->assertSame( 1, $remaining_other, 'Per-blog scoping must leave sibling blogs untouched' );
	}

	public function test_register_hooks_auto_purge_action_scheduler_callbacks() {
		// The Admin instance is constructed by the test bootstrap, so register()
		// has already run. Just assert the actions are wired up.
		$this->assertNotFalse(
			has_action( \WP_Stream\Admin::AUTO_PURGE_ACTION, array( $this->purge, 'purge_scheduled_action' ) ),
			'Recurring auto-purge AS callback should be registered'
		);
		$this->assertNotFalse(
			has_action( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION, array( $this->purge, 'auto_purge_batch' ) ),
			'Auto-purge batch worker should be registered'
		);
		$this->assertNotFalse(
			has_action( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION, array( $this->purge, 'auto_purge_reaper' ) ),
			'Auto-purge reaper should be registered'
		);
		$this->assertFalse(
			has_action( 'wp_stream_auto_purge', array( $this->purge, 'purge_scheduled_action' ) ),
			'Legacy wp_stream_auto_purge hook should no longer dispatch to purge_scheduled_action directly'
		);
	}

	public function test_is_running_auto_purge_reflects_chain_state() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( Admin::AUTO_PURGE_REAPER_ACTION );
		}
		$this->assertFalse(
			$this->purge->is_running_auto_purge(),
			'No scheduled actions means not running'
		);

		as_enqueue_async_action(
			Admin::AUTO_PURGE_BATCH_ACTION,
			array(
				'cutoff'     => '2020-01-01 00:00:00',
				'blog_id'    => 0,
				'last_entry' => 0,
			),
			Admin::AUTO_PURGE_GROUP
		);
		$this->assertTrue(
			$this->purge->is_running_auto_purge(),
			'A pending batch action means running'
		);

		as_unschedule_all_actions( Admin::AUTO_PURGE_BATCH_ACTION );
		as_enqueue_async_action(
			Admin::AUTO_PURGE_REAPER_ACTION,
			array(),
			Admin::AUTO_PURGE_GROUP
		);
		$this->assertTrue(
			$this->purge->is_running_auto_purge(),
			'A pending reaper action means running'
		);

		as_unschedule_all_actions( Admin::AUTO_PURGE_REAPER_ACTION );
		$this->assertFalse(
			$this->purge->is_running_auto_purge(),
			'Chain drained: not running'
		);
	}

	public function test_is_running_auto_purge_includes_in_progress_actions() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( Admin::AUTO_PURGE_REAPER_ACTION );
		}

		// Enqueue and then flip the action's status to IN-PROGRESS to simulate
		// the runner having dequeued an action and started executing it.
		// Without RUNNING-aware filtering, is_running_auto_purge() would
		// return false here and the overlap guard would let a second chain
		// stack against the same rows.
		$action_id = as_enqueue_async_action(
			Admin::AUTO_PURGE_BATCH_ACTION,
			array(
				'cutoff'     => '2020-01-01 00:00:00',
				'blog_id'    => 0,
				'last_entry' => 0,
			),
			Admin::AUTO_PURGE_GROUP
		);
		\ActionScheduler::store()->log_execution( $action_id );

		$this->assertTrue(
			$this->purge->is_running_auto_purge(),
			'In-progress (RUNNING) actions must count as running to prevent overlap'
		);

		as_unschedule_all_actions( Admin::AUTO_PURGE_BATCH_ACTION );
	}

	public function test_is_running_async_deletion_reflects_scheduled_state() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Admin::ASYNC_DELETION_ACTION );
		}

		$this->assertFalse(
			$this->purge->is_running_async_deletion(),
			'No scheduled action means not running'
		);

		as_enqueue_async_action(
			Admin::ASYNC_DELETION_ACTION,
			array(
				'total'      => 1,
				'done'       => 0,
				'last_entry' => 1,
				'blog_id'    => (int) get_current_blog_id(),
			)
		);
		$this->assertTrue(
			$this->purge->is_running_async_deletion(),
			'A pending async-deletion action means running'
		);

		as_unschedule_all_actions( Admin::ASYNC_DELETION_ACTION );
		$this->assertFalse(
			$this->purge->is_running_async_deletion(),
			'After unscheduling: not running'
		);
	}
}
