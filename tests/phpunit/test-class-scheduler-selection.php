<?php
/**
 * Tests for scheduler backend selection (issue #1907).
 *
 * Exercises Plugin::create_scheduler() and the `wp_stream_use_action_scheduler`
 * filter that drives it.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_Scheduler_Selection extends WP_StreamTestCase {

	public function tearDown(): void {
		remove_all_filters( 'wp_stream_use_action_scheduler' );
		parent::tearDown();
	}

	/**
	 * The live plugin instance always has a scheduler implementing the
	 * interface.
	 */
	public function test_plugin_exposes_a_scheduler() {
		$this->assertInstanceOf( Scheduler::class, $this->plugin->scheduler );
	}

	/**
	 * With Action Scheduler loaded (as it is in the test bootstrap) and no
	 * filter override, the default selection is the AS-backed scheduler.
	 */
	public function test_default_selects_action_scheduler_when_available() {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->markTestSkipped( 'Action Scheduler is not loaded in this environment.' );
		}

		$this->assertInstanceOf( AS_Scheduler::class, $this->plugin->create_scheduler() );
	}

	/**
	 * Returning false from the filter forces the WP-Cron fallback even when
	 * Action Scheduler is present — the Altis / Cavalcade use case.
	 */
	public function test_filter_false_forces_cron_scheduler() {
		add_filter( 'wp_stream_use_action_scheduler', '__return_false' );

		$this->assertInstanceOf( Cron_Scheduler::class, $this->plugin->create_scheduler() );
	}

	/**
	 * Returning true from the filter forces the AS scheduler.
	 */
	public function test_filter_true_forces_action_scheduler() {
		add_filter( 'wp_stream_use_action_scheduler', '__return_true' );

		$this->assertInstanceOf( AS_Scheduler::class, $this->plugin->create_scheduler() );
	}

	/**
	 * A forced `__return_true` override must NOT return the AS scheduler when
	 * the bundled AS library is absent — AS_Scheduler's unguarded as_*()
	 * calls would fatal. The guard falls back to the cron scheduler instead.
	 */
	public function test_filter_true_with_as_unavailable_falls_back_to_cron() {
		add_filter( 'wp_stream_use_action_scheduler', '__return_true' );

		// Simulate an environment that omits the bundled AS library.
		$property = new \ReflectionProperty( Plugin::class, 'action_scheduler_available' );
		$property->setAccessible( true );
		$original = $property->getValue( $this->plugin );
		$property->setValue( $this->plugin, false );

		try {
			$this->assertInstanceOf( Cron_Scheduler::class, $this->plugin->create_scheduler() );
		} finally {
			$property->setValue( $this->plugin, $original );
		}
	}
}
