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
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_options = isset( $this->plugin->settings->options )
			? (array) $this->plugin->settings->options
			: array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		$this->plugin->settings->options = $this->original_options;
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
