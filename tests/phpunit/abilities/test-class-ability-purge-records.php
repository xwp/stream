<?php
/**
 * Tests for Ability_Purge_Records.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Purge_Records
 */
class Test_Ability_Purge_Records extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Purge_Records
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-purge-records.php';
		$this->ability = new Ability_Purge_Records( $this->plugin );
	}

	public function test_name_and_schema_shape() {
		$this->assertSame( 'stream/purge-records', $this->ability->get_name() );

		$input = $this->ability->get_input_schema();
		$this->assertSame( array( 'confirm' ), $input['required'] );
		$this->assertSame( array( true ), $input['properties']['confirm']['enum'] );

		$annotations = $this->ability->get_annotations();
		$this->assertTrue( $annotations['destructive'] );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_refuses_when_no_filter_supplied() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute( array( 'confirm' => true ) );

		$this->assertWPError( $result );
		$this->assertSame( 'stream_purge_no_filter', $result->get_error_code() );
	}

	public function test_refuses_when_not_confirmed() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute( array( 'connector' => 'users' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'stream_purge_not_confirmed', $result->get_error_code() );
	}

	public function test_deletes_only_matching_rows_and_cascades_meta() {
		global $wpdb;

		wp_set_current_user( $this->admin_user_id );

		// Seed: one users record (target) and one posts record (preserved).
		$this->plugin->log->log(
			'users',
			'Target record',
			array( 'meta_one' => 'value' ),
			0,
			'users',
			'created'
		);
		$this->plugin->log->log(
			'posts',
			'Preserved record',
			array( 'meta_two' => 'value' ),
			0,
			'posts',
			'updated'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->stream}" );
		$this->assertGreaterThanOrEqual( 2, $total_before );

		$result = $this->ability->execute(
			array(
				'confirm'   => true,
				'connector' => 'users',
			)
		);

		$this->assertIsArray( $result );
		$this->assertGreaterThanOrEqual( 1, $result['deleted'] );

		// Users records gone.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$users_left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->stream} WHERE connector = 'users'" );
		$this->assertSame( 0, $users_left );

		// Posts records untouched.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$posts_left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->stream} WHERE connector = 'posts'" );
		$this->assertGreaterThanOrEqual( 1, $posts_left );

		// No orphaned meta for users connector — the cascade DELETE should have
		// removed any meta rows whose record_id was deleted.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$orphans = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->streammeta} meta
			 LEFT JOIN {$wpdb->stream} stream ON stream.ID = meta.record_id
			 WHERE stream.ID IS NULL"
		);
		$this->assertSame( 0, $orphans );

		$this->assert_matches_schema( $result, $this->ability->get_output_schema() );
	}

	public function test_zero_match_returns_zero_count() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute(
			array(
				'confirm'   => true,
				'connector' => 'definitely-not-a-real-connector',
			)
		);

		$this->assertSame( array( 'deleted' => 0 ), $result );
	}
}
