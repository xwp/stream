<?php
/**
 * Abstract base class for Stream abilities (WordPress Abilities API integration).
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability
 *
 * Subclasses define a single Stream operation that is exposed via the
 * WordPress Abilities API. Each subclass declares a namespaced name,
 * input/output JSON Schemas, a permission callback, and an execute
 * callback. Subclasses are instantiated and registered by Abilities.
 */
abstract class Ability {

	/**
	 * Holds instance of plugin object.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Namespaced ability name (e.g. "stream/get-records").
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Short human-readable label.
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Description of what the ability does.
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * JSON Schema for ability input. Return an empty array for no input.
	 *
	 * @return array
	 */
	abstract public function get_input_schema();

	/**
	 * JSON Schema for ability output.
	 *
	 * @return array
	 */
	abstract public function get_output_schema();

	/**
	 * Execute the ability.
	 *
	 * The default value is `null` to match WP core's invoke_callback() contract:
	 * when an ability has no input_schema, core calls the callback with zero
	 * arguments. PHP's ArgumentCountError would otherwise fatal here. Subclasses
	 * that declare a non-empty input_schema can rely on $input being a parsed
	 * array (core enforces this via rest_validate_value_from_schema()).
	 *
	 * @param mixed $input Validated input matching get_input_schema(), or null
	 *                     when the ability declares no input_schema.
	 * @return mixed|\WP_Error Result conforming to get_output_schema(), or WP_Error.
	 */
	abstract public function execute( $input = null );

	/**
	 * Permission check. Defaults to the Stream settings capability so write
	 * abilities are safe by default; read-only abilities should override with
	 * the view capability (e.g. 'view_stream') to follow least-privilege.
	 *
	 * @param array $input Input that will be passed to execute().
	 * @return bool|\WP_Error
	 */
	public function permission_callback( $input = array() ) {
		unset( $input );
		return current_user_can( WP_STREAM_SETTINGS_CAPABILITY );
	}

	/**
	 * Permission check for abilities that persist Stream settings.
	 *
	 * On network-activated multisite, Settings::update_all_setting_values()
	 * writes the authoritative network option (wp_stream_network), so the write
	 * affects every site on the network. WP_STREAM_SETTINGS_CAPABILITY defaults
	 * to 'manage_options', which every single-site administrator holds -- on its
	 * own it is not sufficient authority for a network-wide change. Require a
	 * network capability whenever the write is going to be network-scoped.
	 *
	 * The network capability is required in addition to the settings
	 * capability, not instead of it: on multisite a super admin passes every
	 * capability check by definition, so returning the network check alone
	 * would silently ignore a deployment that narrowed
	 * WP_STREAM_SETTINGS_CAPABILITY.
	 *
	 * @return bool
	 */
	protected function can_write_settings() {
		$can_write_settings = current_user_can( WP_STREAM_SETTINGS_CAPABILITY );

		// Mirrors the branch in Settings::update_all_setting_values(): when the
		// write lands on the network option it additionally requires network
		// authority. This is an extra requirement, never a substitute -- the
		// settings capability is a site-overridable constant, so a deployment
		// that restricts or revokes it must keep being honoured for super
		// admins too.
		if ( is_multisite() && $this->plugin->is_network_activated() ) {
			return $can_write_settings && current_user_can( 'manage_network_options' );
		}

		return $can_write_settings;
	}

	/**
	 * Annotation flags for the ability (readonly, destructive, idempotent).
	 *
	 * @return array
	 */
	public function get_annotations() {
		return array();
	}

	/**
	 * Meta passed to wp_register_ability(). Sets REST exposure and (optionally) annotations.
	 *
	 * @return array
	 */
	public function get_meta() {
		$meta = array(
			'show_in_rest' => true,
			// Mark every Stream ability as MCP-discoverable. When the
			// WordPress MCP Adapter (wordpress/mcp-adapter) is installed,
			// the default MCP server exposes abilities with this flag via
			// its `mcp-adapter/discover-abilities` and `execute-ability`
			// tools. The flag is harmless if mcp-adapter is not present
			// (unused meta key). permission_callback still gates execution,
			// so destructive abilities aren't auto-callable by anonymous
			// MCP clients.
			'mcp'          => array(
				'public' => true,
			),
		);

		$annotations = $this->get_annotations();
		if ( ! empty( $annotations ) ) {
			$meta['annotations'] = $annotations;
		}

		return $meta;
	}

	/**
	 * Register the ability with the Abilities API.
	 *
	 * @return void
	 */
	final public function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$args = array(
			'label'               => $this->get_label(),
			'description'         => $this->get_description(),
			'category'            => Abilities::CATEGORY_SLUG,
			'output_schema'       => $this->get_output_schema(),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'permission_callback' ),
			'meta'                => $this->get_meta(),
		);

		$input_schema = $this->get_input_schema();
		if ( ! empty( $input_schema ) ) {
			$args['input_schema'] = $input_schema;
		}

		wp_register_ability( $this->get_name(), $args );
	}
}
