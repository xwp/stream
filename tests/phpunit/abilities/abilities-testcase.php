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
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WP_Ability' ) ) {
			$this->markTestSkipped( 'Requires WordPress 6.9+ (Abilities API).' );
		}

		$this->admin_user_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
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
