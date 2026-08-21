<?php
/**
 * Tests for the Action Scheduler backend (XWPENG-22 AS conflict defense).
 *
 * Happy-path coverage when the bundled AS API is present. The missing-function
 * path (outdated in-memory AS copy) cannot undefine `as_*` in this suite;
 * that is verified via WP-CLI in `.ai/tickets/XWPENG-22/sub-issues/01-action-scheduler/`.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_AS_Scheduler extends WP_StreamTestCase {

	/**
	 * Scheduler under test.
	 *
	 * @var AS_Scheduler
	 */
	protected $scheduler;

	/**
	 * Hook used only by these tests.
	 *
	 * @var string
	 */
	protected $test_hook = 'wp_stream_test_as_async';

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler is not loaded in this environment.' );
		}

		$this->scheduler = new AS_Scheduler();
		$this->clear_test_hooks();
	}

	public function tearDown(): void {
		$this->clear_test_hooks();
		parent::tearDown();
	}

	/**
	 * Drop any actions these tests may have scheduled.
	 */
	protected function clear_test_hooks() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $this->test_hook );
			as_unschedule_all_actions( Admin::AUTO_PURGE_ACTION );
		}
	}

	/**
	 * Enqueue_async() schedules a one-off action detectable by has_scheduled().
	 */
	public function test_enqueue_async_schedules_action() {
		$this->assertFalse( $this->scheduler->has_scheduled( $this->test_hook ) );

		$this->scheduler->enqueue_async(
			$this->test_hook,
			array(
				'a' => 1,
				'b' => 2,
			)
		);

		$this->assertTrue( $this->scheduler->has_scheduled( $this->test_hook ) );
		$this->assertNotFalse(
			$this->scheduler->next_scheduled(
				$this->test_hook,
				array(
					'a' => 1,
					'b' => 2,
				)
			)
		);
	}

	/**
	 * Schedule_recurring() registers a recurring action and is idempotent.
	 */
	public function test_schedule_recurring_is_idempotent() {
		$this->scheduler->schedule_recurring( time(), 12 * HOUR_IN_SECONDS, Admin::AUTO_PURGE_ACTION );
		$first = $this->scheduler->next_scheduled( Admin::AUTO_PURGE_ACTION );
		$this->assertNotFalse( $first );
		$this->assertTrue( $this->scheduler->has_scheduled( Admin::AUTO_PURGE_ACTION ) );

		$this->scheduler->schedule_recurring( time() + 100, 12 * HOUR_IN_SECONDS, Admin::AUTO_PURGE_ACTION );
		$this->assertSame( $first, $this->scheduler->next_scheduled( Admin::AUTO_PURGE_ACTION ) );
	}

	/**
	 * Next_scheduled() returns false when nothing is queued for the hook.
	 */
	public function test_next_scheduled_returns_false_when_nothing_queued() {
		$this->assertFalse( $this->scheduler->next_scheduled( $this->test_hook ) );
		$this->assertFalse( $this->scheduler->has_scheduled( $this->test_hook ) );
	}

	/**
	 * Unschedule_all() clears every pending instance of a hook.
	 */
	public function test_unschedule_all_clears_hook() {
		$this->scheduler->enqueue_async( $this->test_hook, array( 1 ) );
		$this->assertTrue( $this->scheduler->has_scheduled( $this->test_hook ) );

		$this->scheduler->unschedule_all( $this->test_hook );
		$this->assertFalse( $this->scheduler->has_scheduled( $this->test_hook ) );
	}
}
