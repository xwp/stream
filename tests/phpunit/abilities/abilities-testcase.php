<?php
/**
 * Shared base test case for Stream ability classes.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Abilities_TestCase
 *
 * Provides:
 * - Skip when WordPress < 6.9 (Abilities API absent).
 * - Helpers for switching to admin / subscriber users for permission tests.
 * - Output schema validation helper.
 */
abstract class Abilities_TestCase extends WP_StreamTestCase {

	/**
	 * Admin user ID used in permission tests.
	 *
	 * @var int
	 */
	protected $admin_user_id;

	/**
	 * Subscriber user ID used in permission tests.
	 *
	 * @var int
	 */
	protected $subscriber_user_id;

	/**
	 * Snapshot of $plugin->settings->options at setUp() time, restored in tearDown().
	 *
	 * @var array
	 */
	private $options_snapshot = array();

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WP_Ability' ) ) {
			$this->markTestSkipped( 'Requires WordPress 6.9+ (Abilities API).' );
		}

		$this->admin_user_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Snapshot the in-memory options so write abilities can mutate them in a test
		// without leaking state into the next test (the underlying wp_options DB row is
		// rolled back by the WP test framework, but the singleton $plugin->settings->options
		// array survives between tests in the same process).
		$this->options_snapshot = isset( $this->plugin->settings->options )
			? (array) $this->plugin->settings->options
			: array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		if ( isset( $this->plugin->settings ) ) {
			$this->plugin->settings->options = $this->options_snapshot;
		}
		parent::tearDown();
	}

	/**
	 * Run a callback with the given action name pushed onto $wp_current_filter.
	 *
	 * WordPress 6.9 gates wp_register_ability() and wp_register_ability_category()
	 * behind a doing_action() check. Outside the natural wp_abilities_api_init /
	 * wp_abilities_api_categories_init action callbacks, registration triggers
	 * _doing_it_wrong. WP core's own tests work around this by manipulating
	 * $wp_current_filter directly; that's both faster and less polluting than
	 * round-tripping through add_action() + do_action().
	 *
	 * @param string   $action_name Action name to fake doing.
	 * @param callable $callback    Callable to run inside the faked action.
	 * @return mixed Whatever $callback returns.
	 */
	protected function with_doing_action( $action_name, callable $callback ) {
		global $wp_current_filter;
		$wp_current_filter[] = $action_name; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		try {
			return $callback();
		} finally {
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * Ensure the "stream" ability category is registered without going through
	 * the lazy registry-init action (which fires only once per process).
	 *
	 * @return void
	 */
	protected function ensure_stream_category_registered() {
		if ( ! function_exists( 'wp_has_ability_category' ) || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		if ( wp_has_ability_category( Abilities::CATEGORY_SLUG ) ) {
			return;
		}
		$this->with_doing_action(
			'wp_abilities_api_categories_init',
			static function () {
				wp_register_ability_category(
					Abilities::CATEGORY_SLUG,
					array(
						'label'       => 'Stream',
						'description' => 'Stream test category.',
					)
				);
			}
		);
	}

	/**
	 * Register a Stream ability instance via wp_register_ability(), faking the
	 * required action context. Idempotent: returns silently if the ability is
	 * already registered (so tests can run in any order within a process).
	 *
	 * @param Ability $ability Ability instance to register.
	 * @return void
	 */
	protected function register_ability_in_test( Ability $ability ) {
		$this->ensure_stream_category_registered();

		if ( ! function_exists( 'wp_has_ability' ) ) {
			return;
		}

		if ( wp_has_ability( $ability->get_name() ) ) {
			return;
		}

		$this->with_doing_action(
			'wp_abilities_api_init',
			static function () use ( $ability ) {
				$ability->register();
			}
		);
	}

	/**
	 * Validate a value against a JSON Schema using WP's REST validator.
	 *
	 * Asserts no validation errors. Returns the validation result (true on success).
	 *
	 * @param mixed  $value  Value to validate.
	 * @param array  $schema JSON Schema.
	 * @param string $msg    Optional message for assertion failure.
	 * @return mixed
	 */
	protected function assert_matches_schema( $value, $schema, $msg = '' ) {
		if ( empty( $schema ) ) {
			return true;
		}

		$result = rest_validate_value_from_schema( $value, $schema );

		if ( is_wp_error( $result ) ) {
			$this->fail(
				( $msg ? $msg . ': ' : '' )
				. 'Value did not match schema: '
				. $result->get_error_message()
			);
		}

		$this->assertNotInstanceOf( '\WP_Error', $result );
		return $result;
	}
}
