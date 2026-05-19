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

	/**
	 * On network-activated multisite, writes must land in wp_stream_network
	 * (the authoritative store that is_enabled() and the admin UI read from),
	 * NOT in the per-site wp_stream option. The ability runs in REST where
	 * is_network_admin() is always false, so without Settings::update_all_setting_values()
	 * routing the write would silently target the per-site option and ghost-
	 * save. Mirrors the read-side coverage in
	 * Test_Abilities::test_is_enabled_reads_network_option_when_network_activated.
	 *
	 * @group ms-required
	 */
	public function test_write_targets_network_option_when_network_activated() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		wp_set_current_user( $this->admin_user_id );

		$network_key      = $this->plugin->settings->network_options_key;
		$per_site_key     = $this->plugin->settings->option_key;
		$original_network = get_site_option( $network_key, false );
		$original_site    = get_option( $per_site_key, false );

		// Force network-activated so update_all_setting_values() chooses the
		// site-option write path.
		add_filter( 'wp_stream_is_network_activated', '__return_true' );

		// Reset both stores to a known baseline.
		update_site_option( $network_key, array( 'general_records_ttl' => 30 ) );
		update_option( $per_site_key, array( 'general_records_ttl' => 30 ) );

		try {
			$result = $this->ability->execute(
				array(
					'settings' => array( 'general_records_ttl' => 90 ),
				)
			);

			$this->assertIsArray( $result, 'Update must succeed.' );

			$stored_network  = (array) get_site_option( $network_key, array() );
			$stored_per_site = (array) get_option( $per_site_key, array() );

			$this->assertSame(
				90,
				(int) ( $stored_network['general_records_ttl'] ?? 0 ),
				'Write must land in the network option on network-activated multisite.'
			);
			$this->assertSame(
				30,
				(int) ( $stored_per_site['general_records_ttl'] ?? 0 ),
				'Per-site option must NOT be touched on network-activated multisite.'
			);
		} finally {
			remove_filter( 'wp_stream_is_network_activated', '__return_true' );
			if ( false === $original_network ) {
				delete_site_option( $network_key );
			} else {
				update_site_option( $network_key, $original_network );
			}
			if ( false === $original_site ) {
				delete_option( $per_site_key );
			} else {
				update_option( $per_site_key, $original_site );
			}
		}//end try
	}
}
