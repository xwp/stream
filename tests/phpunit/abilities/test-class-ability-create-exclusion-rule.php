<?php
/**
 * Tests for Ability_Create_Exclusion_Rule.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Create_Exclusion_Rule
 */
class Test_Ability_Create_Exclusion_Rule extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Create_Exclusion_Rule
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-create-exclusion-rule.php';
		$this->ability = new Ability_Create_Exclusion_Rule( $this->plugin );
	}

	public function test_name_and_schema_shape() {
		$this->assertSame( 'stream/create-exclusion-rule', $this->ability->get_name() );

		$input = $this->ability->get_input_schema();
		$this->assertSame( 1, $input['minProperties'] );
		$this->assertArrayHasKey( 'author_or_role', $input['properties'] );
		$this->assertArrayHasKey( 'ip_address', $input['properties'] );

		$output = $this->ability->get_output_schema();
		$this->assertArrayHasKey( 'index', $output['properties'] );
		$this->assertArrayHasKey( 'rule', $output['properties'] );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_appends_rule_into_parallel_arrays() {
		wp_set_current_user( $this->admin_user_id );

		$option_key = $this->plugin->settings->option_key;
		// Clean baseline.
		update_option( $option_key, array() );

		$first = $this->ability->execute(
			array(
				'connector' => 'posts',
				'action'    => 'updated',
			)
		);

		$this->assertSame( 0, $first['index'] );
		$this->assertSame( 'posts', $first['rule']['connector'] );
		$this->assertSame( 'updated', $first['rule']['action'] );
		$this->assertNull( $first['rule']['author_or_role'] );

		$second = $this->ability->execute(
			array(
				'ip_address' => '127.0.0.1',
			)
		);

		$this->assertSame( 1, $second['index'] );

		$stored = (array) get_option( $option_key );
		$this->assertSame(
			array(
				0 => '',
				1 => '',
			),
			$stored['exclude_rules']['exclude_row']
		);
		$this->assertSame( 'posts', $stored['exclude_rules']['connector'][0] );
		$this->assertSame( '', $stored['exclude_rules']['connector'][1] );
		$this->assertSame( '127.0.0.1', $stored['exclude_rules']['ip_address'][1] );
	}

	public function test_schema_rejects_empty_input() {
		$result = rest_validate_value_from_schema( array(), $this->ability->get_input_schema() );
		$this->assertWPError( $result );
	}

	public function test_rejects_invalid_ip_address() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute( array( 'ip_address' => 'not-an-ip' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'stream_invalid_ip', $result->get_error_code() );
	}

	public function test_rejects_unknown_connector() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute( array( 'connector' => 'definitely-not-a-real-connector' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'stream_unknown_connector', $result->get_error_code() );
	}

	public function test_rejects_all_empty_values() {
		wp_set_current_user( $this->admin_user_id );

		// minProperties:1 in the schema is satisfied by the key being present,
		// but execute() must reject when every filter is the empty string.
		$result = $this->ability->execute( array( 'author_or_role' => '' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'stream_empty_rule', $result->get_error_code() );
	}

	public function test_sanitizes_text_input() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute(
			array(
				'author_or_role' => "evil\nmultiline<script>alert(1)</script>",
			)
		);

		$this->assertIsArray( $result );
		// sanitize_text_field strips tags and normalizes whitespace.
		$this->assertStringNotContainsString( '<script>', $result['rule']['author_or_role'] );
		$this->assertStringNotContainsString( "\n", $result['rule']['author_or_role'] );
	}
}
