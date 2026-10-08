<?php
/**
 * Tests for Settings option autoload behaviour (issue #1482).
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Settings
 */
class Test_Settings extends WP_StreamTestCase {

	/**
	 * Autoload column values that mean "load on every request".
	 *
	 * Modern WordPress may store on / auto-on instead of the legacy yes.
	 *
	 * @var string[]
	 */
	const AUTOLOAD_YES_FAMILY = array( 'yes', 'on', 'auto-on' );

	/**
	 * Autoload column values that mean "do not autoload".
	 *
	 * @var string[]
	 */
	const AUTOLOAD_NO_FAMILY = array( 'no', 'off', 'auto-off' );

	/**
	 * Read the autoload column for a blog option.
	 *
	 * @param string $option_name Option name.
	 * @return string|null Autoload value, or null if the row is missing.
	 */
	private function get_option_autoload( $option_name ) {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$option_name
			)
		);

		return null === $value ? null : (string) $value;
	}

	/**
	 * Whether the current run is a network-activated multisite install.
	 *
	 * The update_all_setting_values() method writes to sitemeta in that case,
	 * which has no autoload column to assert against.
	 *
	 * @return bool
	 */
	private function is_network_settings_store() {
		return is_multisite() && $this->plugin->is_network_activated();
	}

	public function test_update_all_setting_values_passes_autoload_true() {
		if ( $this->is_network_settings_store() ) {
			$this->markTestSkipped( 'Network-activated installs write sitemeta, not wp_options.autoload.' );
		}

		$option_key = $this->plugin->settings->option_key;

		$result = $this->plugin->settings->update_all_setting_values(
			array(
				'general_records_ttl' => 42,
			)
		);

		$this->assertTrue( $result );

		$stored = get_option( $option_key );
		$this->assertTrue( is_array( $stored ) );
		$this->assertSame( 42, (int) $stored['general_records_ttl'] );

		$autoload = $this->get_option_autoload( $option_key );
		$this->assertNotNull( $autoload );
		$this->assertContains(
			$autoload,
			self::AUTOLOAD_YES_FAMILY,
			'wp_stream must stay autoloaded after update_all_setting_values().'
		);
	}

	public function test_ensure_option_autoload_sets_yes_when_no() {
		if ( ! function_exists( 'wp_set_option_autoload' ) ) {
			$this->markTestSkipped( 'wp_set_option_autoload() requires WordPress 6.4+.' );
		}

		if ( $this->is_network_settings_store() ) {
			$this->markTestSkipped( 'Network-activated installs write sitemeta, not wp_options.autoload.' );
		}

		$option_key = $this->plugin->settings->option_key;

		delete_option( $option_key );
		add_option( $option_key, array( 'general_records_ttl' => 30 ), '', 'no' );

		$before = $this->get_option_autoload( $option_key );
		$this->assertContains(
			$before,
			self::AUTOLOAD_NO_FAMILY,
			'Precondition: option must start as not-autoloaded.'
		);

		$this->plugin->settings->ensure_option_autoload();

		$after = $this->get_option_autoload( $option_key );
		$this->assertContains(
			$after,
			self::AUTOLOAD_YES_FAMILY,
			'ensure_option_autoload() must flip a no-autoload row to yes-family.'
		);
	}

	public function test_ensure_option_autoload_is_idempotent() {
		if ( ! function_exists( 'wp_set_option_autoload' ) ) {
			$this->markTestSkipped( 'wp_set_option_autoload() requires WordPress 6.4+.' );
		}

		if ( $this->is_network_settings_store() ) {
			$this->markTestSkipped( 'Network-activated installs write sitemeta, not wp_options.autoload.' );
		}

		$option_key = $this->plugin->settings->option_key;

		// Ensure a yes-family row exists.
		delete_option( $option_key );
		add_option( $option_key, array( 'general_records_ttl' => 30 ), '', 'yes' );
		$this->plugin->settings->ensure_option_autoload();

		$first = $this->get_option_autoload( $option_key );
		$this->assertContains( $first, self::AUTOLOAD_YES_FAMILY );

		$this->plugin->settings->ensure_option_autoload();

		$second = $this->get_option_autoload( $option_key );
		$this->assertSame( $first, $second );
		$this->assertContains( $second, self::AUTOLOAD_YES_FAMILY );
	}

	public function test_ensure_option_autoload_skips_network_option_key() {
		if ( ! function_exists( 'wp_set_option_autoload' ) ) {
			$this->markTestSkipped( 'wp_set_option_autoload() requires WordPress 6.4+.' );
		}

		if ( $this->is_network_settings_store() ) {
			$this->markTestSkipped( 'Network-activated installs write sitemeta, not wp_options.autoload.' );
		}

		$settings     = $this->plugin->settings;
		$original_key = $settings->option_key;
		$network_key  = $settings->network_options_key;

		// Seed a blog option under the network key name with autoload=no.
		// ensure_option_autoload() must leave it alone when option_key points
		// at the network settings key (sitemeta has no autoload flag).
		delete_option( $network_key );
		add_option( $network_key, array( 'general_records_ttl' => 30 ), '', 'no' );

		$before               = $this->get_option_autoload( $network_key );
		$settings->option_key = $network_key;
		$settings->ensure_option_autoload();
		$after                = $this->get_option_autoload( $network_key );
		$settings->option_key = $original_key;

		$this->assertSame( $before, $after );
		$this->assertContains( $after, self::AUTOLOAD_NO_FAMILY );

		delete_option( $network_key );
	}
}
