<?php
namespace WP_Stream;

class WP_StreamTestCase extends \WP_Ajax_UnitTestCase {
	/**
	 * Holds the plugin base class
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Custom action prefix for test custom triggered actions
	 *
	 * @var string
	 */
	protected $action_prefix = 'wp_stream_test_';

	/**
	 * Holds the mocked class.
	 *
	 * @var MockBuilder
	 */
	protected $mock;

	/**
	 * PHP unit setup function
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->plugin = $GLOBALS['wp_stream'];
		$this->assertNotEmpty( $this->plugin );
	}

	/**
	 * Helper function to check validity of action
	 *
	 * @param array  $tests
	 * @param string $function_call
	 */
	protected function do_action_validation( array $tests = array(), $function_call = 'has_action' ) {
		foreach ( $tests as $test ) {
			list( $action, $class, $function ) = $test;

			// Default WP priority
			$priority = isset( $test[3] ) ? $test[3] : 10;

			// Default function call
			$function_call = ( in_array( $function_call, array( 'has_action', 'has_filter' ), true ) ) ? $function_call : 'has_action';

			// Run assertion here
			$this->assertEquals(
				$priority,
				$function_call( $action, array( $class, $function ) ),
				"$action $function_call is not attached to $class::$function. It might also have the wrong priority (validated priority: $priority)"
			);
			$this->assertTrue(
				method_exists( $class, $function ),
				"Class '$class' doesn't implement the '$function' function"
			);
		}
	}

	/**
	 * Helper function to check validity of filters
	 *
	 * @param array $tests
	 */
	protected function do_filter_validation( array $tests = array() ) {
		$this->do_action_validation( $tests, 'has_filter' );
	}
}
