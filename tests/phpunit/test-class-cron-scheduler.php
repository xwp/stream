<?php
/**
 * Tests for the WP-Cron scheduler fallback (issue #1907).
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_Cron_Scheduler extends WP_StreamTestCase {

	/**
	 * Scheduler under test.
	 *
	 * @var Cron_Scheduler
	 */
	protected $scheduler;

	public function setUp(): void {
		parent::setUp();
		$this->scheduler = new Cron_Scheduler();
	}

	public function tearDown(): void {
		// Clear anything the scheduler may have left behind.
		wp_unschedule_hook( 'wp_stream_test_async' );
		wp_unschedule_hook( Admin::AUTO_PURGE_ACTION );
		delete_transient( Cron_Scheduler::RUNNING_TRANSIENT );
		parent::tearDown();
	}

	/**
	 * The custom 12-hour recurrence is registered with WP-Cron.
	 */
	public function test_registers_custom_recurrence() {
		$schedules = wp_get_schedules();

		$this->assertArrayHasKey( Cron_Scheduler::RECURRENCE, $schedules );
		$this->assertSame( 12 * HOUR_IN_SECONDS, $schedules[ Cron_Scheduler::RECURRENCE ]['interval'] );
	}

	/**
	 * Enqueue_async() schedules a one-off event detectable by next_scheduled().
	 */
	public function test_enqueue_async_schedules_single_event() {
		$this->scheduler->enqueue_async(
			'wp_stream_test_async',
			array(
				'a' => 1,
				'b' => 2,
			)
		);

		// Args are passed positionally (array_values), mirroring AS.
		$this->assertNotFalse(
			$this->scheduler->next_scheduled( 'wp_stream_test_async', array( 1, 2 ) )
		);
		$this->assertTrue( $this->scheduler->has_scheduled( 'wp_stream_test_async' ) );
	}

	/**
	 * Schedule_recurring() registers a recurring event and is idempotent.
	 */
	public function test_schedule_recurring_is_idempotent() {
		$this->scheduler->schedule_recurring( time(), 12 * HOUR_IN_SECONDS, Admin::AUTO_PURGE_ACTION );
		$first = $this->scheduler->next_scheduled( Admin::AUTO_PURGE_ACTION );
		$this->assertNotFalse( $first );

		// A second call must not stack a duplicate.
		$this->scheduler->schedule_recurring( time() + 100, 12 * HOUR_IN_SECONDS, Admin::AUTO_PURGE_ACTION );
		$this->assertSame( $first, $this->scheduler->next_scheduled( Admin::AUTO_PURGE_ACTION ) );

		$schedule = wp_get_schedule( Admin::AUTO_PURGE_ACTION );
		$this->assertSame( Cron_Scheduler::RECURRENCE, $schedule );
	}

	/**
	 * Any_pending_or_running() reports true for a pending hook regardless of args.
	 */
	public function test_any_pending_or_running_detects_pending_with_any_args() {
		$this->scheduler->enqueue_async(
			'wp_stream_test_async',
			array(
				'cutoff'  => '2026-01-01',
				'blog_id' => 0,
			)
		);

		$this->assertTrue(
			$this->scheduler->any_pending_or_running( array( 'wp_stream_test_async' ) )
		);
		$this->assertFalse(
			$this->scheduler->any_pending_or_running( array( 'wp_stream_some_other_hook' ) )
		);
	}

	/**
	 * The running marker bridges the gap when nothing is pending.
	 */
	public function test_running_marker_toggles_guard() {
		$this->assertFalse( $this->scheduler->any_pending_or_running( array( 'wp_stream_idle_hook' ) ) );

		$this->scheduler->mark_running( 'auto_purge' );
		$this->assertTrue( $this->scheduler->any_pending_or_running( array( 'wp_stream_idle_hook' ) ) );

		$this->scheduler->mark_done( 'auto_purge' );
		$this->assertFalse( $this->scheduler->any_pending_or_running( array( 'wp_stream_idle_hook' ) ) );
	}

	/**
	 * Unschedule_all() clears every pending instance of a hook.
	 */
	public function test_unschedule_all_clears_hook() {
		$this->scheduler->enqueue_async( 'wp_stream_test_async', array( 1 ) );
		$this->assertTrue( $this->scheduler->has_scheduled( 'wp_stream_test_async' ) );

		$this->scheduler->unschedule_all( 'wp_stream_test_async' );
		$this->assertFalse( $this->scheduler->has_scheduled( 'wp_stream_test_async' ) );
	}
}
