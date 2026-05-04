<?php
/**
 * Loads and registers Stream abilities with the WordPress Abilities API.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Abilities
 *
 * Gates the Abilities API integration on:
 *   1. WordPress 6.9+ (presence of WP_Ability class).
 *   2. The "Enable Abilities API" setting in the Advanced section.
 *
 * When both gates pass, hooks wp_abilities_api_init to register all
 * Stream abilities under the `stream/` namespace.
 */
class Abilities {

	/**
	 * Category slug used in ability meta.
	 *
	 * @const string
	 */
	const CATEGORY_SLUG = 'stream';

	/**
	 * Settings field name (under the Advanced section).
	 *
	 * @const string
	 */
	const SETTING_NAME = 'enable_abilities_api';

	/**
	 * Holds instance of plugin object.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Registered ability objects keyed by namespaced name.
	 *
	 * @var array<string, Ability>
	 */
	public $abilities = array();

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		if ( ! $this->is_available() ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Hooked to wp_abilities_api_categories_init. Registers the "stream" ability category.
	 *
	 * @return void
	 */
	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			array(
				'label'       => __( 'Stream', 'stream' ),
				'description' => __( 'Abilities that read or modify Stream activity logs and configuration.', 'stream' ),
			)
		);
	}

	/**
	 * Whether the WordPress Abilities API is available (WP 6.9+).
	 *
	 * @return bool
	 */
	public function is_available() {
		return class_exists( '\WP_Ability' );
	}

	/**
	 * Whether the integration is enabled in Stream settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$key     = 'advanced_' . self::SETTING_NAME;
		$options = isset( $this->plugin->settings ) ? (array) $this->plugin->settings->options : array();

		return ! empty( $options[ $key ] );
	}

	/**
	 * List of ability slugs to load. Each maps to abilities/class-ability-{slug}.php
	 * and class WP_Stream\Ability_{Slug_With_Underscores}.
	 *
	 * @return array
	 */
	public function get_ability_slugs() {
		return array(
			// Read-only.
			'get-records',
			'get-record',
			'get-settings',
			'get-alerts',
			'get-connectors',
			'get-exclusion-rules',

			// Write.
			'create-alert',
			'update-settings',
			'create-exclusion-rule',

			// Destructive.
			'purge-records',
			'delete-alert',
		);
	}

	/**
	 * Require ability files and instantiate their classes.
	 *
	 * @return void
	 */
	public function load_abilities() {
		$dir = trailingslashit( $this->plugin->locations['dir'] ) . 'abilities/';

		foreach ( $this->get_ability_slugs() as $slug ) {
			$file = $dir . 'class-ability-' . $slug . '.php';
			if ( ! is_readable( $file ) ) {
				continue;
			}
			include_once $file;

			$class_part = implode( '_', array_map( 'ucfirst', explode( '-', $slug ) ) );
			$class      = '\WP_Stream\Ability_' . $class_part;
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$ability = new $class( $this->plugin );
			if ( ! $ability instanceof Ability ) {
				continue;
			}

			$this->abilities[ $ability->get_name() ] = $ability;
		}
	}

	/**
	 * Hooked to wp_abilities_api_init. Loads and registers all abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( empty( $this->abilities ) ) {
			$this->load_abilities();
		}

		foreach ( $this->abilities as $ability ) {
			// Defensive: skip if another loader instance already registered this
			// ability (e.g. duplicate plugin instances in tests, multisite hooks).
			// Re-registering would emit a _doing_it_wrong notice from core.
			if ( function_exists( 'wp_has_ability' ) && wp_has_ability( $ability->get_name() ) ) {
				continue;
			}
			$ability->register();
		}
	}
}
