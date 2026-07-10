<?php
/**
 * Tests for switching scheduler backends at runtime (issue #1907).
 *
 * When an operator flips the `wp_stream_use_action_scheduler` filter, the
 * previously-active backend's recurring auto-purge action must be removed so
 * the purge does not fire from both Action Scheduler and WP-Cron at once.
 * purge_schedule_setup() performs this cleanup on every wp_loaded, so the
 * first request after a switch converges to a single scheduler.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_Scheduler_Handoff extends WP_StreamTestCase {

	/**
	 * Admin instance under test.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Scheduler active before a test swapped it.
	 *
	 * @var Scheduler
	 */
	protected $original_scheduler;

	public function setUp(): void {
		parent::setUp();
		$this->admin              = $this->plugin->admin;
		$this->original_scheduler = $this->plugin->scheduler;
		$this->clear();
	}

	public function tearDown(): void {
		$this->clear();
		$this->plugin->scheduler = $this->original_scheduler;
		parent::tearDown();
	}

	private function clear() {
		wp_unschedule_hook( Admin::AUTO_PURGE_ACTION );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Admin::AUTO_PURGE_ACTION );
		}
		// Reset the backend marker so the next purge_schedule_setup() sees a
		// switch and performs the one-time cleanup.
		delete_option( Admin::SCHEDULER_BACKEND_OPTION );
	}

	/**
	 * Switching to WP-Cron must clear a leftover Action Scheduler recurring
	 * action and register the WP-Cron one in its place.
	 */
	public function test_cron_active_clears_stray_action_scheduler_recurring() {
		// Pre-existing AS recurring action (as if the site previously ran AS).
		as_schedule_recurring_action(
			time(),
			12 * HOUR_IN_SECONDS,
			Admin::AUTO_PURGE_ACTION,
			array(),
			Admin::AUTO_PURGE_GROUP
		);
		$this->assertNotFalse( as_next_scheduled_action( Admin::AUTO_PURGE_ACTION ) );

		$this->plugin->scheduler = new Cron_Scheduler();
		$this->admin->purge_schedule_setup();

		$this->assertFalse(
			as_next_scheduled_action( Admin::AUTO_PURGE_ACTION ),
			'Switching to WP-Cron must remove the stray Action Scheduler recurring action'
		);
		$this->assertNotFalse(
			wp_next_scheduled( Admin::AUTO_PURGE_ACTION ),
			'WP-Cron recurring event must be registered after the switch'
		);
	}

	/**
	 * Switching to Action Scheduler must clear a leftover WP-Cron recurring
	 * event and register the AS one in its place.
	 */
	public function test_action_scheduler_active_clears_stray_wp_cron_recurring() {
		// Pre-existing WP-Cron recurring event (as if the site previously ran cron).
		wp_schedule_event( time(), 'twicedaily', Admin::AUTO_PURGE_ACTION );
		$this->assertNotFalse( wp_next_scheduled( Admin::AUTO_PURGE_ACTION ) );

		$this->plugin->scheduler = new AS_Scheduler();
		$this->admin->purge_schedule_setup();

		$this->assertFalse(
			wp_next_scheduled( Admin::AUTO_PURGE_ACTION ),
			'Switching to Action Scheduler must remove the stray WP-Cron recurring event'
		);
		$this->assertNotFalse(
			as_next_scheduled_action( Admin::AUTO_PURGE_ACTION ),
			'Action Scheduler recurring action must be registered after the switch'
		);
	}

	/**
	 * Disabling auto-purge while the cron backend is active must also clear
	 * a leftover Action Scheduler recurring action when the AS API is loaded
	 * (e.g. provided by WooCommerce) — the filter promises teardown from
	 * BOTH backends, and the active-backend unschedule cannot see AS's store.
	 */
	public function test_disable_clears_action_scheduler_store_when_cron_active() {
		// Pre-existing AS recurring action (as if the site previously ran AS).
		as_schedule_recurring_action(
			time(),
			12 * HOUR_IN_SECONDS,
			Admin::AUTO_PURGE_ACTION,
			array(),
			Admin::AUTO_PURGE_GROUP
		);
		$this->assertNotFalse( as_next_scheduled_action( Admin::AUTO_PURGE_ACTION ) );

		$this->plugin->scheduler = new Cron_Scheduler();
		add_filter( 'wp_stream_enable_auto_purge', '__return_false' );
		$this->admin->purge_schedule_setup();
		remove_all_filters( 'wp_stream_enable_auto_purge' );

		$this->assertFalse(
			as_next_scheduled_action( Admin::AUTO_PURGE_ACTION ),
			'Disabling auto-purge must clear the AS store even when cron is the active backend'
		);
		$this->assertFalse(
			wp_next_scheduled( Admin::AUTO_PURGE_ACTION ),
			'Disabling auto-purge must clear the WP-Cron store'
		);
		$this->assertSame(
			'disabled',
			get_option( Admin::SCHEDULER_BACKEND_OPTION ),
			'Backend marker must record the disabled sentinel'
		);
	}
}
