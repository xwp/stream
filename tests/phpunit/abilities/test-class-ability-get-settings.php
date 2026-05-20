<?php
/**
 * Tests for Ability_Get_Settings.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Get_Settings
 */
class Test_Ability_Get_Settings extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Get_Settings
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-get-settings.php';
		$this->ability = new Ability_Get_Settings( $this->plugin );
	}

	public function test_name_and_schema() {
		$this->assertSame( 'stream/get-settings', $this->ability->get_name() );
		$this->assertSame( array(), $this->ability->get_input_schema() );

		$output = $this->ability->get_output_schema();
		$this->assertSame( 'object', $output['type'] );
		$this->assertTrue( $output['additionalProperties'] );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_execute_returns_settings_array() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute( array() );

		$this->assertIsArray( $result );
		// Stream settings always include general_records_ttl with a default.
		$this->assertArrayHasKey( 'general_records_ttl', $result );
	}
}
