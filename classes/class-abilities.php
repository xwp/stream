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

		// REST requests don't load Admin, so the dynamic view_stream cap filter
		// isn't registered. Register an equivalent here so read abilities can
		// authorize editors / other allowed roles consistently with the admin UI.
		if ( ! isset( $this->plugin->admin ) ) {
			add_filter( 'user_has_cap', array( $this, 'filter_user_caps' ), 10, 4 );
		}
	}

	/**
	 * Grant the dynamic view_stream cap to users whose role appears in
	 * general_role_access. Mirrors Admin::filter_user_caps() for REST contexts
	 * where Admin isn't instantiated.
	 *
	 * @param array    $allcaps All capabilities.
	 * @param array    $caps    Required caps.
	 * @param array    $args    Unused.
	 * @param \WP_User $user    User.
	 * @return array
	 */
	public function filter_user_caps( $allcaps, $caps, $args, $user = null ) {
		unset( $args );

		if ( ! in_array( 'view_stream', (array) $caps, true ) ) {
			return $allcaps;
		}

		$role_access = isset( $this->plugin->settings->options['general_role_access'] )
			? (array) $this->plugin->settings->options['general_role_access']
			: array();

		if ( empty( $role_access ) ) {
			return $allcaps;
		}

		$user = is_a( $user, '\WP_User' ) ? $user : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return $allcaps;
		}

		global $wp_roles;
		$_wp_roles = isset( $wp_roles ) ? $wp_roles : new \WP_Roles();

		$roles = array_unique(
			array_merge(
				$user->roles,
				array_filter(
					array_keys( $user->caps ),
					array( $_wp_roles, 'is_role' )
				)
			)
		);

		foreach ( $roles as $role ) {
			if ( in_array( $role, $role_access, true ) ) {
				$allcaps['view_stream'] = true;
				break;
			}
		}

		return $allcaps;
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

		// Skip when the category is already registered. Without this guard,
		// re-running the bootstrap (e.g. multiple loader instances in tests)
		// triggers a core _doing_it_wrong notice. Mirrors the idempotency
		// pattern in register_abilities().
		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY_SLUG ) ) {
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
	 * Delegates to Settings::get_setting_value() so the multisite/network
	 * fallback logic lives next to the settings storage.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		if ( ! isset( $this->plugin->settings ) ) {
			return false;
		}

		return ! empty(
			$this->plugin->settings->get_setting_value( 'advanced_' . self::SETTING_NAME )
		);
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
