<?php
namespace WP_Stream;

class Test_Admin extends WP_StreamTestCase {
	/**
	 * Holds the admin base class
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Holds the administrator id.
	 *
	 * @var int
	 */
	protected $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->plugin->admin;
		$this->assertNotEmpty( $this->admin );

		// Add admin user to test caps
		// We need to change user to verify editing option as admin or editor
		$this->admin_user_id = \WP_UnitTestCase_Base::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_admin',
				'email'      => 'test@land.com',
			)
		);
		wp_set_current_user( $this->admin_user_id );
	}

	/**
	 * Tear down after each test. Delete the admin user and start afresh.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tear_down();

		if ( is_multisite() ) {
			wpmu_delete_user( $this->admin_user_id );
		} else {
			wp_delete_user( $this->admin_user_id );
		}
	}

	public function test_construct() {
		$this->assertNotEmpty( $this->admin->plugin );
		$this->assertInstanceOf( '\WP_Stream\Plugin', $this->admin->plugin );

		$this->assertTrue( function_exists( 'is_plugin_active_for_network' ) );

		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) && ! is_network_admin() ) {
			$this->assertTrue( $this->admin->disable_access );
		} else {
			$this->assertFalse( $this->admin->disable_access );
		}
	}

	public function test_init() {
		$this->admin->init();
		$this->assertNotEmpty( $this->admin->network );
		$this->assertNotEmpty( $this->admin->live_update );
		$this->assertNotEmpty( $this->admin->export );

		$this->assertInstanceOf( '\WP_Stream\Network', $this->admin->network );
		$this->assertInstanceOf( '\WP_Stream\Live_Update', $this->admin->live_update );
		$this->assertInstanceOf( '\WP_Stream\Export', $this->admin->export );
	}

	public function test_prepare_admin_notices() {
		// Test no notices
		$this->admin->notices = array();
		$this->admin->prepare_admin_notices();
		$this->assertEmpty( $this->admin->notices );

		// Test settings reset notice
		$_GET['message'] = 'settings_reset';
		$this->admin->prepare_admin_notices();
		$this->assertNotEmpty( $this->admin->notices );

		// Prevent output
		$this->admin->notices = array();
	}

	public function test_notice() {
		// Start with nothing
		$this->admin->notices = array();
		$this->assertEmpty( $this->admin->notices );

		$message  = 'Affirmative, Dave. I read you.';
		$is_error = false;

		$this->admin->notice( $message, $is_error );
		$this->assertNotEmpty( $this->admin->notices );
		ob_start();
		$this->admin->admin_notices();
		$notice = ob_get_clean();

		$this->assertStringContainsString( $message, $notice );
		$this->assertStringContainsString( 'updated', $notice );
		$this->assertStringNotContainsString( 'error', $notice );

		// Clear notices and start again
		$this->admin->notices = array();
		$this->assertEmpty( $this->admin->notices );

		$is_error = true;

		$this->admin->notice( $message, $is_error );
		$this->assertNotEmpty( $this->admin->notices );
		ob_start();
		$this->admin->admin_notices();
		$notice = ob_get_clean();

		$this->assertStringContainsString( $message, $notice );
		$this->assertStringContainsString( 'error', $notice );
		$this->assertStringNotContainsString( 'updated', $notice );

		// Prevent output
		$this->admin->notices = array();
	}

	public function test_admin_notices() {
		$allowed_html         = '<progress class="migration" max="100"></progress>';
		$disallowed_html      = '<iframe></iframe>';
		$this->admin->notices = array(
			array(
				'message'  => "I'm sorry, Dave. I'm afraid I can't do that. $disallowed_html",
				'is_error' => false,
			),
			array(
				'message'  => "This mission is too important for me to allow you to jeopardize it. $allowed_html",
				'is_error' => false,
			),
		);

		ob_start();
		$this->admin->admin_notices();
		$notices = ob_get_clean();

		$this->assertStringContainsString( $allowed_html, $notices );
		$this->assertStringNotContainsString( $disallowed_html, $notices );
		$this->assertStringContainsString( str_replace( $disallowed_html, '', $this->admin->notices[0]['message'] ), $notices );
		$this->assertStringContainsString( wpautop( $this->admin->notices[1]['message'] ), $notices );

		// Prevent output
		$this->admin->notices = array();
	}

	public function test_register_menu() {
		global $menu;
		$menu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		do_action( 'admin_menu' );

		$this->assertNotEmpty( $this->admin->screen_id );
		$this->assertNotEmpty( $this->admin->screen_id['main'] );
		$this->assertNotEmpty( $this->admin->screen_id['settings'] );
	}

	public function test_admin_enqueue_scripts() {
		global $wp_scripts;

		// Non-Stream screen
		$this->admin->admin_enqueue_scripts( 'edit.php' );

		$this->assertFalse( wp_script_is( 'wp-stream-admin' ), 'wp-stream-admin script is not enqueued' );
		$this->assertFalse( wp_style_is( 'wp-stream-admin' ), 'wp-stream-admin style is not enqueued' );

		$this->assertTrue( wp_script_is( 'wp-stream-global' ), 'wp-stream-global script is enqueued' );

		$this->assertStringContainsString(
			'bulk_actions',
			$wp_scripts->get_inline_script_data( 'wp-stream-global', 'before' ),
		);

		// Stream screen
		$this->admin->admin_enqueue_scripts( $this->plugin->admin->screen_id['main'] );

		$this->assertTrue( wp_style_is( 'wp-stream-admin' ), 'wp-stream-admin style is enqueued' );

		$this->assertTrue( wp_script_is( 'wp-stream-select2' ), 'wp-stream-select2 script is enqueued' );
		$this->assertTrue( wp_script_is( 'wp-stream-select2-en' ), 'wp-stream-select2-en script is enqueued' );
		$this->assertTrue( wp_script_is( 'wp-stream-jquery-timeago' ), 'wp-stream-jquery-timeago script is enqueued' );
		$this->assertTrue( wp_script_is( 'wp-stream-jquery-timeago-en' ), 'wp-stream-jquery-timeago-en script is enqueued' );

		$this->assertTrue( wp_script_is( 'wp-stream-admin' ), 'wp-stream-admin script is enqueued' );
		$this->assertTrue( wp_script_is( 'wp-stream-live-updates' ), 'wp-stream-live-updates script is enqueued' );

		$this->assertStringContainsString(
			'i18n',
			$wp_scripts->get_inline_script_data( 'wp-stream-admin', 'before' ),
		);

		$this->assertStringContainsString(
			'current_screen',
			$wp_scripts->get_inline_script_data( 'wp-stream-live-updates', 'before' ),
		);
		$this->assertStringContainsString(
			$this->plugin->admin->screen_id['main'],
			$wp_scripts->get_inline_script_data( 'wp-stream-live-updates', 'before' ),
		);
	}

	public function test_is_stream_screen() {
		$this->assertFalse( $this->admin->is_stream_screen() );

		if ( ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}
		$_GET['page'] = $this->admin->records_page_slug;

		$this->assertTrue( $this->admin->is_stream_screen() );
	}

	public function test_admin_body_class() {
		// Make this the Stream screen
		if ( ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}
		$_GET['page'] = $this->admin->records_page_slug;

		$classes            = 'sit-down-calmy take-a-stress-pill think-things-over';
		$admin_body_classes = $this->admin->admin_body_class( $classes );

		$this->assertStringContainsString( 'think-things-over ', $admin_body_classes );
		$this->assertStringContainsString( $this->admin->admin_body_class . ' ', $admin_body_classes );
		$this->assertStringContainsString( $this->admin->records_page_slug . ' ', $admin_body_classes );
	}

	public function test_admin_menu_css() {
		global $wp_styles;

		$this->admin->admin_menu_css();

		$dependency = $wp_styles->registered['wp-admin'];
		$this->assertArrayHasKey( 'after', $dependency->extra );
		$this->assertNotEmpty( $dependency->extra['after'] );
		$this->assertStringContainsString( "body.{$this->admin->admin_body_class}", $dependency->extra['after'][0] );
	}

	/**
	 * Also tests private method erase_stream_records
	 */
	public function test_wp_ajax_reset() {
		$_REQUEST['wp_stream_nonce']       = wp_create_nonce( 'stream_nonce' );
		$_REQUEST['wp_stream_nonce_reset'] = wp_create_nonce( 'stream_nonce_reset' );

		global $wpdb;

		// Create dummy records
		$stream_data = $this->dummy_stream_data();
		$wpdb->insert( $wpdb->stream, $stream_data );
		$stream_id = $wpdb->insert_id;
		$this->assertNotFalse( $stream_id );

		// Create dummy meta
		$meta_data = $this->dummy_meta_data( $stream_id );
		$wpdb->insert( $wpdb->streammeta, $meta_data );
		$meta_id = $wpdb->insert_id;
		$this->assertNotFalse( $meta_id );

		// Check that records exist
		$stream_result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->stream} WHERE ID = %d", $stream_id ) );
		$this->assertNotEmpty( $stream_result );

		// Check that meta exists
		$meta_result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->streammeta} WHERE meta_id = %d", $meta_id ) );
		$this->assertNotEmpty( $meta_result );

		// Clear records and meta
		$reset = $this->admin->wp_ajax_reset();
		$this->assertTrue( $reset );

		// Check that records have been cleared
		$stream_results = $wpdb->get_results( "SELECT * FROM {$wpdb->stream}" );
		$this->assertEmpty( $stream_results );

		// Check that meta has been cleared
		$meta_results = $wpdb->get_results( "SELECT * FROM {$wpdb->streammeta}" );
		$this->assertEmpty( $meta_results );
	}

	/**
	 * Also tests private method erase_stream_records
	 */
	public function test_wp_ajax_reset_large_records_blog() {

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		global $wpdb;

		$_REQUEST['wp_stream_nonce']       = wp_create_nonce( 'stream_nonce' );
		$_REQUEST['wp_stream_nonce_reset'] = wp_create_nonce( 'stream_nonce_reset' );

		add_filter( 'wp_stream_is_large_records_table', '__return_true' );
		add_filter( 'wp_stream_is_network_activated', '__return_false' );

		$stream_data = $this->dummy_stream_data();
		$wpdb->insert( $wpdb->stream, $stream_data );
		$stream_id = $wpdb->insert_id;
		$this->assertNotFalse( $stream_id );

		$meta_data = $this->dummy_meta_data( $stream_id );
		$wpdb->insert( $wpdb->streammeta, $meta_data );
		$meta_id = $wpdb->insert_id;
		$this->assertNotFalse( $meta_id );

		$stream_data_2 = $this->dummy_stream_data_other_blog();
		$wpdb->insert( $wpdb->stream, $stream_data_2 );
		$stream_id_2 = $wpdb->insert_id;
		$this->assertNotFalse( $stream_id_2 );

		$meta_data = $this->dummy_meta_data( $stream_id_2 );
		$wpdb->insert( $wpdb->streammeta, $meta_data );
		$meta_id_2 = $wpdb->insert_id;
		$this->assertNotFalse( $meta_id_2 );

		// Clear records and meta
		$reset = $this->admin->wp_ajax_reset();
		$this->assertTrue( $reset );

		$current_blog = (int) get_current_blog_id();

		// Assert the scheduled action has been set.
		$this->assertTrue(
			as_has_scheduled_action(
				Admin::ASYNC_DELETION_ACTION
			)
		);

		// Check that records have not been cleared yet.
		$stream_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->stream} WHERE blog_id=%d",
				$current_blog
			)
		);
		$this->assertNotEmpty( $stream_results );

		$this->admin->erase_large_records( 1, 0, $meta_id, $current_blog );

		// Check that records have been cleared.
		$stream_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->stream} WHERE blog_id=%d",
				$current_blog
			)
		);
		$this->assertEmpty( $stream_results );

		// Check that records of the other blog have not been cleared.
		$stream_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->stream} WHERE blog_id=%d",
				$current_blog + 1
			)
		);
		$this->assertNotEmpty( $stream_results );

		// Check that one meta has been cleared
		$meta_results = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->streammeta}" );
		$this->assertEquals( 1, $meta_results );

		remove_filter( 'wp_stream_is_large_records_table', '__return_true' );
		remove_filter( 'wp_stream_is_network_activated', '__return_false' );
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

		$this->admin->purge_schedule_setup();

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
		$this->admin->purge_schedule_setup();
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

		$hits     = 0;
		$listener = function () use ( &$hits ) {
			++$hits;
		};
		add_action( 'wp_stream_auto_purge', $listener );

		// Make sure something is eligible so we exercise the full code path.
		$this->seed_aged_records( 1, 5 );
		$this->set_records_ttl( 1 );

		$this->admin->purge_scheduled_action();

		remove_action( 'wp_stream_auto_purge', $listener );
		$this->assertSame( 1, $hits, 'wp_stream_auto_purge action must fire exactly once per recurring tick when work runs' );
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

		$hits     = 0;
		$listener = function () use ( &$hits ) {
			++$hits;
		};
		add_action( 'wp_stream_auto_purge', $listener );

		$this->admin->purge_scheduled_action();

		remove_action( 'wp_stream_auto_purge', $listener );
		$this->assertSame(
			0,
			$hits,
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

		$this->admin->purge_scheduled_action();

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

		$this->admin->purge_scheduled_action();

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

		$this->admin->purge_scheduled_action();

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

		$this->admin->purge_scheduled_action();

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

		$this->admin->purge_scheduled_action();

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
		$this->admin->purge_scheduled_action();
		$first = as_get_scheduled_actions(
			array(
				'hook'   => \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);
		$this->assertCount( 1, $first );

		// Second call must be a no-op.
		$this->admin->purge_scheduled_action();
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

		$this->admin->purge_scheduled_action();

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

		$this->admin->purge_scheduled_action();

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

	public function test_plugin_action_links() {
		$links = array( '<a href="javascript:void(0);">Disconnect</a>' );
		$file  = plugin_basename( $this->plugin->locations['dir'] . 'stream.php' );

		$action_links = $this->admin->plugin_action_links( $links, $file );

		$this->assertStringContainsString( 'Disconnect', $action_links[0] );
		$this->assertStringContainsString( 'Settings', $action_links[1] );
	}

	public function test_render_list_table() {
		$this->admin->register_list_table();

		ob_start();
		$this->admin->render_list_table();
		$html = ob_get_clean();

		$this->assertStringContainsString( '<div class="wrap">', $html );
		$this->assertStringContainsString( 'record-filter-form', $html );
	}

	public function test_render_settings_page() {
		ob_start();
		$this->admin->render_settings_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( '<div class="wrap">', $html );

		global $wp_scripts;

		$this->assertArrayHasKey( 'wp-stream-settings', $wp_scripts->registered );
	}

	public function test_register_list_table() {
		$this->admin->register_list_table();

		$this->assertNotEmpty( $this->admin->list_table );
		$this->assertInstanceOf( '\WP_Stream\List_Table', $this->admin->list_table );
	}

	/**
	 * Also tests private method role_can_view
	 */
	public function test_filter_user_caps() {
		$user = new \WP_User( $this->admin_user_id );

		$this->plugin->settings->options['general_role_access'] = array( 'administrator' );
		$this->assertTrue( $user->has_cap( $this->admin->view_cap ) );

		$this->plugin->settings->options['general_role_access'] = array( 'editor' );
		$this->assertFalse( $user->has_cap( $this->admin->view_cap ) );
	}

	/**
	 * Also tests private method role_can_view
	 */
	public function test_filter_role_caps() {
		$role = get_role( 'administrator' );

		$this->plugin->settings->options['general_role_access'] = array( 'administrator' );
		$this->assertTrue( $role->has_cap( $this->admin->view_cap ) );

		$this->plugin->settings->options['general_role_access'] = array( 'editor' );
		$this->assertFalse( $role->has_cap( $this->admin->view_cap ) );
	}

	/**
	 * Test Ajax Filters
	 *
	 * @group ajax
	 * @requires PHPUnit 5.7
	 */
	public function test_ajax_filters() {
		$user = new \WP_User( $this->admin_user_id );

		$this->_setRole( 'subscriber' );

		$_POST['filter'] = 'user_id';
		$_POST['q']      = $user->display_name;
		$_POST['nonce']  = wp_create_nonce( 'stream_filters_user_search_nonce' );

		$this->expectException( 'WPAjaxDieStopException' );

		try {
			$this->_handleAjax( 'wp_stream_filters' );
		} catch ( WPAjaxDieStopException $e ) {
			// Do nothing.
		}

		// Check that the exception was thrown.
		$this->assertTrue( isset( $e ) );

		// The output should be a -1 for failure.
		$this->assertEquals( '-1', $e->getMessage() );
		unset( $e );

		$this->_setRole( 'administrator' );

		$this->_handleAjax( 'wp_stream_filters' );
		$json = $this->_last_response;

		$this->assertNotEmpty( $json );
		$data = json_decode( $json );
		$this->assertNotFalse( $data );
		$this->assertNotEmpty( $data );
		$this->assertIsArray( $data );
	}

	public function test_get_users_record_meta() {
		$user_id = $this->admin_user_id;
		$authors = array(
			$user_id => get_user_by( 'id', $user_id ),
		);

		$records = $this->admin->get_users_record_meta( $authors );

		$this->assertArrayHasKey( $user_id, $records );
		$this->assertArrayHasKey( 'text', $records[ $user_id ] );
		$this->assertEquals( 'test_admin', $records[ $user_id ]['text'] );
	}

	public function test_get_user_meta() {
		$key   = 'message_1';
		$value = 'It is dangerous to remain here. You must leave within two days.';
		update_user_meta( $this->admin_user_id, $key, $value );
		$this->assertEquals( $this->admin->get_user_meta( $this->admin_user_id, $key, true ), $value );
	}

	public function test_update_user_meta() {
		$key   = 'message_2';
		$value = 'I understand. It is important that you believe me. Look behind you.';
		$this->admin->update_user_meta( $this->admin_user_id, $key, $value );
		$this->assertEquals( get_user_meta( $this->admin_user_id, $key, true ), $value );
	}

	public function test_delete_user_meta() {
		$key   = 'message_3';
		$value = 'I was David Bowman.';

		update_user_meta( $this->admin_user_id, $key, $value );
		$this->assertEquals( get_user_meta( $this->admin_user_id, $key, true ), $value );

		$this->admin->delete_user_meta( $this->admin_user_id, $key );

		$this->assertEmpty( get_user_meta( $this->admin_user_id, $key, true ) );
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

	public function test_ajax_clean_orphan_meta_schedules_reaper() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['wp_stream_nonce_clean_orphan_meta'] = wp_create_nonce( 'stream_nonce_clean_orphan_meta' );

		$result = $this->admin->wp_ajax_clean_orphan_meta();
		$this->assertTrue( $result );

		$this->assertNotFalse(
			as_next_scheduled_action( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION ),
			'Ajax handler must enqueue the reaper action'
		);

		unset( $_REQUEST['wp_stream_nonce_clean_orphan_meta'] );
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

		$this->admin->auto_purge_reaper();

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
		add_filter(
			'wp_stream_batch_size',
			function () {
				return 2;
			}
		);

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

		$this->admin->auto_purge_batch( $cutoff, 0 );

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
		$this->admin->auto_purge_batch( '', 0, 0 );
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

		$this->admin->auto_purge_batch( $cutoff, 0 );

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
		add_filter(
			'wp_stream_batch_size',
			function () {
				return 3;
			}
		);

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
		$this->admin->auto_purge_batch( $cutoff, 0, 0 );

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

		$this->admin->auto_purge_batch( $cutoff, $current_blog );

		$remaining_other = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->stream} WHERE blog_id = %d", $other_blog )
		);
		$this->assertSame( 1, $remaining_other, 'Per-blog scoping must leave sibling blogs untouched' );
	}

	public function test_is_running_auto_purge_reflects_chain_state() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}
		$this->assertFalse(
			\WP_Stream\Admin::is_running_auto_purge(),
			'No scheduled actions means not running'
		);

		as_enqueue_async_action(
			\WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION,
			array(
				'cutoff'     => '2020-01-01 00:00:00',
				'blog_id'    => 0,
				'last_entry' => 0,
			),
			\WP_Stream\Admin::AUTO_PURGE_GROUP
		);
		$this->assertTrue(
			\WP_Stream\Admin::is_running_auto_purge(),
			'A pending batch action means running'
		);

		as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
		as_enqueue_async_action(
			\WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION,
			array(),
			\WP_Stream\Admin::AUTO_PURGE_GROUP
		);
		$this->assertTrue(
			\WP_Stream\Admin::is_running_auto_purge(),
			'A pending reaper action means running'
		);

		as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		$this->assertFalse(
			\WP_Stream\Admin::is_running_auto_purge(),
			'Chain drained: not running'
		);
	}

	public function test_is_running_auto_purge_includes_in_progress_actions() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		// Enqueue and then flip the action's status to IN-PROGRESS to simulate
		// the runner having dequeued an action and started executing it.
		// Without RUNNING-aware filtering, is_running_auto_purge() would
		// return false here and the overlap guard would let a second chain
		// stack against the same rows.
		$action_id = as_enqueue_async_action(
			\WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION,
			array(
				'cutoff'     => '2020-01-01 00:00:00',
				'blog_id'    => 0,
				'last_entry' => 0,
			),
			\WP_Stream\Admin::AUTO_PURGE_GROUP
		);
		\ActionScheduler::store()->log_execution( $action_id );

		$this->assertTrue(
			\WP_Stream\Admin::is_running_auto_purge(),
			'In-progress (RUNNING) actions must count as running to prevent overlap'
		);

		as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
	}

	public function test_register_hooks_auto_purge_action_scheduler_callbacks() {
		// The Admin instance is constructed by the test bootstrap, so register()
		// has already run. Just assert the actions are wired up.
		$this->assertNotFalse(
			has_action( \WP_Stream\Admin::AUTO_PURGE_ACTION, array( $this->admin, 'purge_scheduled_action' ) ),
			'Recurring auto-purge AS callback should be registered'
		);
		$this->assertNotFalse(
			has_action( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION, array( $this->admin, 'auto_purge_batch' ) ),
			'Auto-purge batch worker should be registered'
		);
		$this->assertNotFalse(
			has_action( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION, array( $this->admin, 'auto_purge_reaper' ) ),
			'Auto-purge reaper should be registered'
		);
		$this->assertFalse(
			has_action( 'wp_stream_auto_purge', array( $this->admin, 'purge_scheduled_action' ) ),
			'Legacy wp_stream_auto_purge hook should no longer dispatch to purge_scheduled_action directly'
		);
	}
}
