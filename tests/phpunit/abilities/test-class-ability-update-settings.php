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
				'settings' => array( 'general_records_ttl' => 60 ),
			)
		);

		$this->assertSame( 60, $this->plugin->settings->options['general_records_ttl'] );
	}

	public function test_rejects_unknown_setting_keys() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute(
			array(
				'settings' => array( 'totally_made_up_key' => 'value' ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'stream_no_valid_settings', $result->get_error_code() );
	}

	public function test_boolean_values_for_checkbox_keys_are_normalized_to_one_zero() {
		wp_set_current_user( $this->admin_user_id );

		$option_key = $this->plugin->settings->option_key;

		// JSON-native boolean true must round-trip to 1, not '' (which is what
		// Settings::sanitize_setting_by_field_type() would produce for a
		// raw bool because it gates on is_numeric()).
		$result = $this->ability->execute(
			array(
				'settings' => array( 'advanced_wp_cron_tracking' => true ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['advanced_wp_cron_tracking'] );
		$this->assertSame( 1, (int) get_option( $option_key )['advanced_wp_cron_tracking'] );

		// And boolean false must round-trip to 0.
		$result = $this->ability->execute(
			array(
				'settings' => array( 'advanced_wp_cron_tracking' => false ),
			)
		);

		$this->assertSame( 0, $result['advanced_wp_cron_tracking'] );
		$this->assertSame( 0, (int) get_option( $option_key )['advanced_wp_cron_tracking'] );
	}

	public function test_unknown_keys_are_dropped_when_mixed_with_valid() {
		wp_set_current_user( $this->admin_user_id );

		$option_key = $this->plugin->settings->option_key;

		$result = $this->ability->execute(
			array(
				'settings' => array(
					'general_records_ttl' => 45,
					'malicious_key'       => '<script>alert(1)</script>',
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 45, $result['general_records_ttl'] );
		$this->assertArrayNotHasKey( 'malicious_key', $result );

		$stored = (array) get_option( $option_key );
		$this->assertArrayNotHasKey( 'malicious_key', $stored );
	}
}
