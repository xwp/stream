<?php
/**
 * Tests for Ability_Get_Connectors.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Get_Connectors
 */
class Test_Ability_Get_Connectors extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Get_Connectors
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-get-connectors.php';
		$this->ability = new Ability_Get_Connectors( $this->plugin );
	}

	public function test_name_and_schema() {
		$this->assertSame( 'stream/get-connectors', $this->ability->get_name() );
		$this->assertSame( array(), $this->ability->get_input_schema() );

		$output = $this->ability->get_output_schema();
		$this->assertSame( 'array', $output['type'] );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_returns_registered_connectors() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute( array() );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result, 'Expected at least one registered connector.' );

		$slugs = array_column( $result, 'slug' );
		$this->assertContains( 'posts', $slugs, 'The built-in posts connector should be registered.' );

		// Each entry has the expected shape.
		foreach ( $result as $entry ) {
			$this->assertArrayHasKey( 'slug', $entry );
			$this->assertArrayHasKey( 'label', $entry );
			$this->assertArrayHasKey( 'contexts', $entry );
			$this->assertArrayHasKey( 'actions', $entry );
			$this->assertIsString( $entry['slug'] );
			$this->assertIsString( $entry['label'] );
			$this->assertIsArray( $entry['contexts'] );
			$this->assertIsArray( $entry['actions'] );
		}
	}

	/**
	 * Admin-only connectors (register_frontend = false) must appear in the
	 * abilities response even when the request isn't wp-admin. Regression
	 * guard for the bug where REST callers got a frontend-only subset of
	 * connectors because Connectors::load_connectors() filtered against
	 * is_admin().
	 */
	public function test_returns_admin_only_connectors_outside_admin_context() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute( array() );
		$slugs  = array_column( $result, 'slug' );

		foreach ( array( 'settings', 'editor', 'menus' ) as $admin_only_slug ) {
			$this->assertContains(
				$admin_only_slug,
				$slugs,
				sprintf( 'Admin-only connector "%s" should be exposed to abilities even outside wp-admin.', $admin_only_slug )
			);
		}
	}
}
