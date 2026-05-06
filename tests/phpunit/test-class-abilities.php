<?php
/**
 * Tests for the Abilities loader.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Abilities
 */
class Test_Abilities extends WP_StreamTestCase {

	/**
	 * Snapshot of plugin settings to restore in tearDown().
	 *
	 * @var array
	 */
	private $original_options;

	/**
	 * Snapshot of the wp_abilities_api_init filter to restore in tearDown().
	 * Saved as a WP_Hook clone so all priority/callback bindings survive the
	 * intentional remove_all_actions() calls inside individual tests.
	 *
	 * @var \WP_Hook|null
	 */
	private $original_abilities_init_hook;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_options = isset( $this->plugin->settings->options )
			? (array) $this->plugin->settings->options
			: array();

		global $wp_filter;
		$this->original_abilities_init_hook = isset( $wp_filter['wp_abilities_api_init'] )
			? clone $wp_filter['wp_abilities_api_init']
			: null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		$this->plugin->settings->options = $this->original_options;

		// Restore the wp_abilities_api_init hook registry so tests that mutate
		// it (via remove_all_actions) don't bleed into subsequent tests.
		global $wp_filter;
		if ( null === $this->original_abilities_init_hook ) {
			unset( $wp_filter['wp_abilities_api_init'] );
		} else {
			$wp_filter['wp_abilities_api_init'] = $this->original_abilities_init_hook; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		parent::tearDown();
	}

	public function test_is_available_matches_wp_ability_class_presence() {
		$abilities = new Abilities( $this->plugin );
		$this->assertSame( class_exists( '\WP_Ability' ), $abilities->is_available() );
	}

	public function test_is_enabled_reflects_settings_option() {
		$abilities = new Abilities( $this->plugin );

		$this->plugin->settings->options['advanced_enable_abilities_api'] = 0;
		$this->assertFalse( $abilities->is_enabled() );

		$this->plugin->settings->options['advanced_enable_abilities_api'] = 1;
		$this->assertTrue( $abilities->is_enabled() );
	}

	/**
	 * On a network-activated multisite install, the toggle is stored in the
	 * network option (wp_stream_network) but Settings::get_options() only
	 * loads from get_site_option() inside is_network_admin(). REST and
	 * frontend contexts therefore see the (typically empty) per-site option
	 * via $plugin->settings->options. is_enabled() must read directly from
	 * the network option in that case.
	 *
	 * @group ms-required
	 */
	public function test_is_enabled_reads_network_option_when_network_activated() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$network_key = $this->plugin->settings->network_options_key;

		// Snapshot whatever's in the network option so we can restore.
		$original_network = get_site_option( $network_key, false );

		// Force network-activated state and ensure the in-memory per-site
		// options do NOT have the toggle set, so a passing assertion proves
		// is_enabled() consulted the network option (not the in-memory copy).
		add_filter( 'wp_stream_is_network_activated', '__return_true' );
		$this->plugin->settings->options['advanced_enable_abilities_api'] = 0;

		try {
			$abilities = new Abilities( $this->plugin );

			update_site_option(
				$network_key,
				array( 'advanced_enable_abilities_api' => 0 )
			);
			$this->assertFalse(
				$abilities->is_enabled(),
				'Network option disabled -> is_enabled() must be false even if in-memory options were ignored.'
			);

			update_site_option(
				$network_key,
				array( 'advanced_enable_abilities_api' => 1 )
			);
			$this->assertTrue(
				$abilities->is_enabled(),
				'Network option enabled -> is_enabled() must be true even though in-memory options say disabled.'
			);
		} finally {
			remove_filter( 'wp_stream_is_network_activated', '__return_true' );
			if ( false === $original_network ) {
				delete_site_option( $network_key );
			} else {
				update_site_option( $network_key, $original_network );
			}
		}//end try
	}

	/**
	 * Inverse of the above: on a non-network-activated install (which
	 * includes single-site and per-site activation on multisite), is_enabled()
	 * must continue to read the in-memory per-site options, not the network
	 * option.
	 */
	public function test_is_enabled_reads_per_site_options_when_not_network_activated() {
		add_filter( 'wp_stream_is_network_activated', '__return_false' );

		$network_key      = $this->plugin->settings->network_options_key;
		$original_network = get_site_option( $network_key, false );

		try {
			$abilities = new Abilities( $this->plugin );

			// Network option set to enabled, but plugin is NOT network-activated:
			// the network option must be ignored.
			update_site_option(
				$network_key,
				array( 'advanced_enable_abilities_api' => 1 )
			);
			$this->plugin->settings->options['advanced_enable_abilities_api'] = 0;
			$this->assertFalse( $abilities->is_enabled() );

			$this->plugin->settings->options['advanced_enable_abilities_api'] = 1;
			$this->assertTrue( $abilities->is_enabled() );
		} finally {
			remove_filter( 'wp_stream_is_network_activated', '__return_false' );
			if ( false === $original_network ) {
				delete_site_option( $network_key );
			} else {
				update_site_option( $network_key, $original_network );
			}
		}//end try
	}

	public function test_constructor_does_not_hook_when_setting_disabled() {
		$this->plugin->settings->options['advanced_enable_abilities_api'] = 0;

		// Clear any leftover hooks from prior tests.
		remove_all_actions( 'wp_abilities_api_init' );

		new Abilities( $this->plugin );

		$this->assertFalse( has_action( 'wp_abilities_api_init' ) );
	}

	public function test_constructor_hooks_when_available_and_enabled() {
		if ( ! class_exists( '\WP_Ability' ) ) {
			$this->markTestSkipped( 'Requires WordPress 6.9+ (Abilities API).' );
		}

		$this->plugin->settings->options['advanced_enable_abilities_api'] = 1;

		remove_all_actions( 'wp_abilities_api_init' );

		$abilities = new Abilities( $this->plugin );

		$this->assertNotFalse( has_action( 'wp_abilities_api_init', array( $abilities, 'register_abilities' ) ) );
	}

	public function test_get_ability_slugs_lists_all_eleven() {
		$abilities = new Abilities( $this->plugin );
		$slugs     = $abilities->get_ability_slugs();

		$this->assertCount( 11, $slugs );
		$this->assertContains( 'get-records', $slugs );
		$this->assertContains( 'get-record', $slugs );
		$this->assertContains( 'get-settings', $slugs );
		$this->assertContains( 'get-alerts', $slugs );
		$this->assertContains( 'get-connectors', $slugs );
		$this->assertContains( 'get-exclusion-rules', $slugs );
		$this->assertContains( 'create-alert', $slugs );
		$this->assertContains( 'update-settings', $slugs );
		$this->assertContains( 'create-exclusion-rule', $slugs );
		$this->assertContains( 'purge-records', $slugs );
		$this->assertContains( 'delete-alert', $slugs );
	}

	public function test_load_abilities_instantiates_each_slug() {
		$abilities = new Abilities( $this->plugin );
		$abilities->load_abilities();

		$this->assertCount( 11, $abilities->abilities );

		foreach ( $abilities->abilities as $name => $ability ) {
			$this->assertInstanceOf( __NAMESPACE__ . '\\Ability', $ability );
			$this->assertSame( $name, $ability->get_name() );
		}
	}

	public function test_load_abilities_populates_all_slugs() {
		$abilities = new Abilities( $this->plugin );

		$abilities->load_abilities();

		$this->assertCount( 11, $abilities->abilities );
		foreach ( $abilities->get_ability_slugs() as $slug ) {
			$this->assertArrayHasKey( 'stream/' . $slug, $abilities->abilities );
		}
	}

	public function test_settings_field_visible_when_not_network_activated() {
		if ( ! class_exists( '\WP_Ability' ) ) {
			$this->markTestSkipped( 'Requires WordPress 6.9+ (Abilities API).' );
		}
		if ( $this->plugin->is_network_activated() ) {
			$this->markTestSkipped( 'Test asserts the non-network-activated branch.' );
		}

		$fields               = $this->plugin->settings->get_fields();
		$advanced_field_names = wp_list_pluck( $fields['advanced']['fields'], 'name' );

		$this->assertContains(
			'enable_abilities_api',
			$advanced_field_names,
			'Toggle must be visible on per-site settings when Stream is not network-activated.'
		);
	}

	public function test_register_abilities_loads_and_registers_when_action_fires() {
		if ( ! class_exists( '\WP_Ability' ) ) {
			$this->markTestSkipped( 'Requires WordPress 6.9+ (Abilities API).' );
		}

		// Enable the setting so the constructor wires both category + abilities hooks.
		$this->plugin->settings->options['advanced_enable_abilities_api'] = 1;

		$abilities = new Abilities( $this->plugin );

		// The registry is a process-wide singleton; once the lazy init actions have
		// already fired in a prior test, wp_get_ability() won't refire them. Drive
		// each registration step explicitly via $wp_current_filter so this test is
		// deterministic regardless of ordering.
		global $wp_current_filter;

		if ( ! wp_has_ability_category( Abilities::CATEGORY_SLUG ) ) {
			$wp_current_filter[] = 'wp_abilities_api_categories_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$abilities->register_category();
			array_pop( $wp_current_filter );
		}

		// Always exercise register_abilities() so this loader instance's abilities
		// array gets populated regardless of whether the global registry already
		// has them registered (a prior test in the same process may have done so).
		$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$abilities->register_abilities();
		array_pop( $wp_current_filter );

		$this->assertTrue( wp_has_ability( 'stream/get-records' ) );
		$this->assertCount( 11, $abilities->abilities );
	}
}
