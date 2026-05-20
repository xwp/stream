<?php
/**
 * Tests for the abstract Ability base class.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

require_once __DIR__ . '/fake-ability.php';

/**
 * Class - Test_Ability
 */
class Test_Ability extends WP_StreamTestCase {

	/**
	 * Ability under test.
	 *
	 * @var Fake_Ability_For_Test
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();
		$this->ability = new Fake_Ability_For_Test( $this->plugin );
	}

	public function test_get_meta_exposes_in_rest_without_annotations_by_default() {
		$meta = $this->ability->get_meta();
		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertArrayNotHasKey( 'annotations', $meta );
	}

	public function test_get_meta_includes_annotations_when_set() {
		$this->ability->annotations = array(
			'readonly'   => true,
			'idempotent' => true,
		);

		$meta = $this->ability->get_meta();
		$this->assertSame(
			array(
				'readonly'   => true,
				'idempotent' => true,
			),
			$meta['annotations']
		);
	}

	public function test_default_permission_callback_admin_vs_subscriber() {
		$admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $subscriber_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $admin_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_register_is_noop_without_wp_register_ability() {
		if ( function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Cannot exercise the no-op branch when wp_register_ability() exists.' );
		}

		// Should silently return without raising.
		$this->ability->register();
		$this->assertTrue( true );
	}

	public function test_register_makes_ability_retrievable() {
		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_has_ability' ) ) {
			$this->markTestSkipped( 'Requires WordPress 6.9+ (Abilities API).' );
		}

		// WP 6.9 gates wp_register_ability() and wp_register_ability_category() on
		// doing_action(); manipulate $wp_current_filter directly to satisfy the guard
		// without polluting the global hook registry. Same pattern WP core uses in
		// tests/phpunit/tests/rest-api/wpRestAbilitiesV1RunController.php.
		global $wp_current_filter;

		if ( ! wp_has_ability_category( Abilities::CATEGORY_SLUG ) ) {
			$wp_current_filter[] = 'wp_abilities_api_categories_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wp_register_ability_category(
				Abilities::CATEGORY_SLUG,
				array(
					'label'       => 'Stream',
					'description' => 'Stream test category.',
				)
			);
			array_pop( $wp_current_filter );
		}

		if ( ! wp_has_ability( 'stream/fake' ) ) {
			$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$this->ability->register();
			array_pop( $wp_current_filter );
		}

		$this->assertTrue( wp_has_ability( 'stream/fake' ) );
		$this->assertInstanceOf( '\WP_Ability', wp_get_ability( 'stream/fake' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		if ( function_exists( 'wp_has_ability' ) && wp_has_ability( 'stream/fake' ) ) {
			wp_unregister_ability( 'stream/fake' );
		}
		parent::tearDown();
	}
}
