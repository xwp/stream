<?php
/**
 * Tests for the abstract Ability base class.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Concrete fake used to exercise the abstract base.
 *
 * Defined at file scope (not inside a method) so PHP can autoload it.
 */
class Fake_Ability_For_Test extends Ability {

	/**
	 * Annotations for the fake ability. Toggled by tests.
	 *
	 * @var array
	 */
	public $annotations = array();

	/**
	 * Last input received by execute(), for test inspection.
	 *
	 * @var array|null
	 */
	public $last_input;

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/fake';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Fake';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return 'Fake ability used in unit tests.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'foo' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_output_schema() {
		return array( 'type' => 'string' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return $this->annotations;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( $input ) {
		$this->last_input = $input;
		return 'ok';
	}
}

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

	public function test_get_meta_includes_category_and_rest_exposure() {
		$meta = $this->ability->get_meta();
		$this->assertSame( Abilities::CATEGORY_SLUG, $meta['category'] );
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
		if ( ! function_exists( 'wp_register_ability' ) || ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Requires WordPress 6.9+ (Abilities API).' );
		}

		$this->ability->register();

		$registered = wp_get_ability( 'stream/fake' );
		$this->assertNotNull( $registered );
	}
}
