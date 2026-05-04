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

	public function test_orderby_schema_uses_query_allowlist() {
		$schema  = $this->ability->get_input_schema();
		$orderby = $schema['properties']['orderby'];

		$this->assertArrayHasKey( 'enum', $orderby, 'orderby must enumerate allowed columns so callers cannot silently fall back to ID.' );
		$this->assertContains( 'created', $orderby['enum'] );
		$this->assertContains( 'ID', $orderby['enum'] );
		$this->assertNotContains( 'date', $orderby['enum'], '"date" is not a real Stream column; the previous default silently fell back to ID.' );
		$this->assertSame( 'created', $orderby['default'] );
	}

	public function test_in_arrays_have_max_items_bound() {
		$schema = $this->ability->get_input_schema();

		$this->assertSame( 100, $schema['properties']['user_id__in']['maxItems'] );
		$this->assertSame( 100, $schema['properties']['connector__in']['maxItems'] );
	}

	public function test_input_schema_rejects_unknown_orderby() {
		$schema = $this->ability->get_input_schema();

		$result = rest_validate_value_from_schema( array( 'orderby' => 'date' ), $schema );
		$this->assertInstanceOf( '\WP_Error', $result, 'orderby=date used to silently fall back to ID; the schema must now reject it.' );

		$result = rest_validate_value_from_schema( array( 'orderby' => 'created' ), $schema );
		$this->assertTrue( $result, 'orderby=created must validate.' );
	}

	public function test_input_schema_rejects_in_arrays_over_max_items() {
		$schema = $this->ability->get_input_schema();

		$too_many = range( 1, 101 );
		$result   = rest_validate_value_from_schema( array( 'user_id__in' => $too_many ), $schema );
		$this->assertInstanceOf( '\WP_Error', $result );

		$at_limit = range( 1, 100 );
		$result   = rest_validate_value_from_schema( array( 'user_id__in' => $at_limit ), $schema );
		$this->assertTrue( $result );
	}

	public function test_orderby_created_actually_orders_by_created_not_id() {
		wp_set_current_user( $this->admin_user_id );

		// Insert two records with explicit, out-of-order timestamps so that
		// ordering by ID (the silent-fallback bug) and ordering by created
		// produce different results. Record A has the higher ID but the older
		// created timestamp; record B has the lower ID but the newer timestamp.
		// With order=DESC + orderby=created, B must come first.
		$older = '2020-01-01 00:00:00';
		$newer = '2024-12-31 23:59:59';

		$id_a = $this->plugin->db->insert(
			array(
				'site_id'   => 1,
				'blog_id'   => get_current_blog_id(),
				'user_id'   => $this->admin_user_id,
				'created'   => $older,
				'summary'   => 'older record (higher ID)',
				'connector' => 'users',
				'context'   => 'users',
				'action'    => 'created',
				'ip'        => '127.0.0.1',
			)
		);
		$id_b = $this->plugin->db->insert(
			array(
				'site_id'   => 1,
				'blog_id'   => get_current_blog_id(),
				'user_id'   => $this->admin_user_id,
				'created'   => $newer,
				'summary'   => 'newer record (lower ID would not happen, but timestamp is newer)',
				'connector' => 'users',
				'context'   => 'users',
				'action'    => 'updated',
			)
		);

		$this->assertNotFalse( $id_a );
		$this->assertNotFalse( $id_b );
		$this->assertGreaterThan( $id_a, $id_b, 'Sanity: B was inserted after A.' );

		$result = $this->ability->execute(
			array(
				'orderby'          => 'created',
				'order'            => 'ASC',
				'records_per_page' => 50,
				'user_id__in'      => array( $this->admin_user_id ),
			)
		);

		$created_seq = array_map(
			static function ( $r ) {
				return $r['created'];
			},
			$result['records']
		);

		// Find our two seeded records in the sequence and confirm older < newer.
		$pos_older = array_search( $older, $created_seq, true );
		$pos_newer = array_search( $newer, $created_seq, true );
		$this->assertNotFalse( $pos_older, 'Seeded older record missing from result.' );
		$this->assertNotFalse( $pos_newer, 'Seeded newer record missing from result.' );
		$this->assertLessThan( $pos_newer, $pos_older, 'orderby=created ASC must place older before newer.' );
	}
}
