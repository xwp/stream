<?php
/**
 * Tests for Ability_Get_Records.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Get_Records
 */
class Test_Ability_Get_Records extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Get_Records
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-get-records.php';
		$this->ability = new Ability_Get_Records( $this->plugin );
	}

	public function test_get_name_returns_namespaced_slug() {
		$this->assertSame( 'stream/get-records', $this->ability->get_name() );
	}

	public function test_input_schema_is_object() {
		$schema = $this->ability->get_input_schema();
		$this->assertIsArray( $schema );
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'records_per_page', $schema['properties'] );
	}

	public function test_output_schema_is_object_with_records_and_total() {
		$schema = $this->ability->get_output_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'records', $schema['properties'] );
		$this->assertArrayHasKey( 'total', $schema['properties'] );
	}

	public function test_meta_marks_readonly_idempotent_and_rest_exposed() {
		$meta = $this->ability->get_meta();
		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertSame( 'stream', $meta['category'] );
		$this->assertTrue( $meta['annotations']['readonly'] );
		$this->assertTrue( $meta['annotations']['idempotent'] );
	}

	public function test_permission_denied_for_subscriber() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback( array() ) );
	}

	public function test_permission_granted_for_admin() {
		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback( array() ) );
	}

	public function test_execute_returns_records_and_total() {
		// Seed a record via the Stream log.
		wp_set_current_user( $this->admin_user_id );
		$this->plugin->log->log(
			'users',
			'Test summary for ability',
			array(),
			0,
			'users',
			'created'
		);

		$result = $this->ability->execute( array( 'records_per_page' => 5 ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'records', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertIsInt( $result['total'] );
		$this->assertIsArray( $result['records'] );
		$this->assertGreaterThanOrEqual( 1, $result['total'] );
	}

	public function test_execute_output_validates_against_schema() {
		wp_set_current_user( $this->admin_user_id );
		$this->plugin->log->log(
			'users',
			'Schema validation summary',
			array(),
			0,
			'users',
			'updated'
		);

		$result = $this->ability->execute( array( 'records_per_page' => 5 ) );
		$this->assert_matches_schema( $result, $this->ability->get_output_schema() );
	}

	public function test_execute_strips_unknown_input_keys() {
		wp_set_current_user( $this->admin_user_id );

		// Unknown keys should be ignored, not passed through to DB::get_records().
		$result = $this->ability->execute(
			array(
				'records_per_page' => 1,
				'totally_made_up'  => 'value',
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'records', $result );
	}
}
