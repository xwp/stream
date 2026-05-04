<?php
/**
 * Tests for Ability_Delete_Alert.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Delete_Alert
 */
class Test_Ability_Delete_Alert extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Delete_Alert
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-delete-alert.php';
		$this->ability = new Ability_Delete_Alert( $this->plugin );
	}

	public function test_name_and_schema_shape() {
		$this->assertSame( 'stream/delete-alert', $this->ability->get_name() );

		$input = $this->ability->get_input_schema();
		$this->assertSame( array( 'id' ), $input['required'] );
		$this->assertSame( 1, $input['properties']['id']['minimum'] );

		$annotations = $this->ability->get_annotations();
		$this->assertTrue( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_deletes_existing_alert() {
		wp_set_current_user( $this->admin_user_id );

		$alert_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'wp_stream_enabled',
				'post_title'  => 'To be deleted',
			)
		);

		$result = $this->ability->execute( array( 'id' => $alert_id ) );

		$this->assertSame( array( 'deleted' => true, 'id' => $alert_id ), $result );
		$this->assertNull( get_post( $alert_id ) );

		$this->assert_matches_schema( $result, $this->ability->get_output_schema() );
	}

	public function test_returns_404_for_unknown_id() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute( array( 'id' => 999999 ) );

		$this->assertWPError( $result );
		$this->assertSame( 'stream_alert_not_found', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	public function test_refuses_non_alert_post() {
		wp_set_current_user( $this->admin_user_id );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Regular post',
			)
		);

		$result = $this->ability->execute( array( 'id' => $post_id ) );

		$this->assertWPError( $result );
		$this->assertSame( 'stream_alert_not_found', $result->get_error_code() );

		// Original post must still exist.
		$this->assertNotNull( get_post( $post_id ) );
	}

	public function test_idempotent_second_call_returns_404() {
		wp_set_current_user( $this->admin_user_id );

		$alert_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'wp_stream_enabled',
			)
		);

		$first  = $this->ability->execute( array( 'id' => $alert_id ) );
		$second = $this->ability->execute( array( 'id' => $alert_id ) );

		$this->assertTrue( $first['deleted'] );
		$this->assertWPError( $second );
		$this->assertSame( 'stream_alert_not_found', $second->get_error_code() );
	}
}
