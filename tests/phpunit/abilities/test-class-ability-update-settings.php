<?php
/**
 * Tests for Ability_Update_Settings.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Update_Settings
 */
class Test_Ability_Update_Settings extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Update_Settings
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-update-settings.php';
		$this->ability = new Ability_Update_Settings( $this->plugin );
	}

	public function test_name_and_schema_shape() {
		$this->assertSame( 'stream/update-settings', $this->ability->get_name() );

		$input = $this->ability->get_input_schema();
		$this->assertSame( array( 'settings' ), $input['required'] );
		$this->assertSame( 1, $input['properties']['settings']['minProperties'] );

		$output = $this->ability->get_output_schema();
		$this->assertSame( 'object', $output['type'] );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_partial_update_preserves_other_keys() {
		wp_set_current_user( $this->admin_user_id );

		$option_key = $this->plugin->settings->option_key;

		// Seed two existing keys.
		update_option(
			$option_key,
			array(
				'general_records_ttl'         => 30,
				'advanced_delete_all_records' => 0,
			)
		);

		$result = $this->ability->execute(
			array(
				'settings' => array( 'general_records_ttl' => 90 ),
			)
		);

		$this->assertSame( 90, $result['general_records_ttl'] );
		$this->assertSame( 0, $result['advanced_delete_all_records'] );

		$stored = (array) get_option( $option_key );
		$this->assertSame( 90, $stored['general_records_ttl'] );
		$this->assertSame( 0, $stored['advanced_delete_all_records'] );
	}

	public function test_refreshes_in_memory_options() {
		wp_set_current_user( $this->admin_user_id );

		$this->ability->execute(
			array(
				'settings' => array( 'custom_marker' => 'updated' ),
			)
		);

		$this->assertSame( 'updated', $this->plugin->settings->options['custom_marker'] );
	}
}
