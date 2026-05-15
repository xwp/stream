<?php
/**
 * Ability: stream/get-settings — return the current Stream plugin settings.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Get_Settings
 *
 * Unlike the other read abilities (get-records, get-record, get-alerts,
 * get-connectors, get-exclusion-rules) which gate on `view_stream`, this
 * one inherits the base permission_callback that requires
 * `WP_STREAM_SETTINGS_CAPABILITY` (defaults to `manage_options`). The
 * settings payload can include role allowlists and other configuration
 * that's intentionally restricted to administrators, so the same cap that
 * gates the wp-admin settings screen guards this ability too.
 */
class Ability_Get_Settings extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/get-settings';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Get Stream Settings', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Return the current Stream plugin settings (role access, record TTL, exclusion rules, advanced flags).', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => true,
			'idempotent'   => true,
			'instructions' => __( 'Call before stream/update-settings to read the current configuration so you can present a diff to the user. Setting keys follow the {section}_{field} convention (e.g. general_records_ttl).', 'stream' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_output_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'description'          => 'Settings keyed by {section}_{field} (e.g. general_records_ttl). Available keys depend on which settings fields are registered for the current context: e.g. advanced_enable_abilities_api is only present on WordPress 6.9+ and, on network-activated multisite, only when read from network admin.',
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $input Validated input matching get_input_schema(), or null.
	 */
	public function execute( $input = null ) {
		unset( $input );

		// Route through Settings::get_all_setting_values() so the network
		// option is consulted on network-activated multisite. A direct read
		// of $plugin->settings->options would return the (typically empty)
		// per-site option in REST contexts (is_network_admin() is always
		// false there), making get-settings disagree with update-settings,
		// which routes writes to the network option via
		// Settings::update_all_setting_values().
		return $this->plugin->settings->get_all_setting_values();
	}
}
