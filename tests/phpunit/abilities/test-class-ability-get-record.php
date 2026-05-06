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

	public function test_does_not_leak_records_from_other_blogs_on_multisite() {
		global $wpdb;

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		wp_set_current_user( $this->admin_user_id );

		// Seed a record under a foreign blog id directly. Whether Stream is
		// network-activated or not, a REST request from the current blog must
		// not be able to fetch it (REST is never is_network_admin()).
		$current_blog_id = (int) get_current_blog_id();
		$foreign_blog_id = $current_blog_id + 4242;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->stream,
			array(
				'site_id'   => 1,
				'blog_id'   => $foreign_blog_id,
				'user_id'   => $this->admin_user_id,
				'created'   => '2020-01-01 00:00:00',
				'summary'   => 'Foreign-blog record that must not be readable.',
				'connector' => 'users',
				'context'   => 'users',
				'action'    => 'created',
				'ip'        => '127.0.0.1',
			)
		);
		$this->assertSame( 1, $inserted );
		$foreign_id = (int) $wpdb->insert_id;

		$result = $this->ability->execute( array( 'id' => $foreign_id ) );

		$this->assertInstanceOf( '\WP_Error', $result, 'get-record must not return another blog\'s record on multisite.' );
		$this->assertSame( 'stream_record_not_found', $result->get_error_code() );
	}
}
