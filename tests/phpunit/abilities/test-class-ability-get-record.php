<?php
/**
 * Tests for Ability_Get_Record.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Get_Record
 */
class Test_Ability_Get_Record extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Get_Record
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-get-record.php';
		$this->ability = new Ability_Get_Record( $this->plugin );
	}

	public function test_name_and_schemas() {
		$this->assertSame( 'stream/get-record', $this->ability->get_name() );

		$input = $this->ability->get_input_schema();
		$this->assertSame( array( 'id' ), $input['required'] );

		$output = $this->ability->get_output_schema();
		$this->assertSame( 'object', $output['type'] );
		$this->assertArrayHasKey( 'meta', $output['properties'] );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_returns_wp_error_when_not_found() {
		wp_set_current_user( $this->admin_user_id );
		$result = $this->ability->execute( array( 'id' => PHP_INT_MAX ) );

		$this->assertInstanceOf( '\WP_Error', $result );
		$this->assertSame( 'stream_record_not_found', $result->get_error_code() );
	}

	public function test_returns_record_with_meta_when_found() {
		wp_set_current_user( $this->admin_user_id );

		$this->plugin->log->log(
			'users',
			'Single record fetch',
			array(),
			0,
			'users',
			'created'
		);

		$records = $this->plugin->db->get_records( array( 'records_per_page' => 1 ) );
		$this->assertNotEmpty( $records );
		$id = (int) $records[0]->ID;

		$result = $this->ability->execute( array( 'id' => $id ) );

		$this->assertIsArray( $result );
		$this->assertSame( $id, (int) $result['ID'] );
		$this->assertArrayHasKey( 'meta', $result );
		$this->assertIsArray( $result['meta'] );
	}
}
