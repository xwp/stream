<?php
/**
 * Tests for Ability_Create_Alert.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Create_Alert
 */
class Test_Ability_Create_Alert extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Create_Alert
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-create-alert.php';
		$this->ability = new Ability_Create_Alert( $this->plugin );
	}

	public function test_name_and_schema_shape() {
		$this->assertSame( 'stream/create-alert', $this->ability->get_name() );

		$input = $this->ability->get_input_schema();
		$this->assertSame( 'object', $input['type'] );
		$this->assertSame(
			array( 'alert_type', 'trigger_author', 'trigger_context', 'trigger_action' ),
			$input['required']
		);

		$output = $this->ability->get_output_schema();
		$this->assertSame( 'object', $output['type'] );
		$this->assertArrayHasKey( 'id', $output['properties'] );
		$this->assertArrayHasKey( 'alert_meta', $output['properties'] );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_creates_alert_post_and_meta() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute(
			array(
				'alert_type'      => 'highlight',
				'trigger_author'  => 'any',
				'trigger_context' => 'posts',
				'trigger_action'  => 'updated',
				'alert_meta'      => array( 'color' => 'yellow' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertGreaterThan( 0, $result['id'] );
		$this->assertSame( 'wp_stream_enabled', $result['status'] );
		$this->assertSame( 'highlight', $result['alert_type'] );

		$alert_meta = (array) get_post_meta( $result['id'], 'alert_meta', true );
		$this->assertSame( 'any', $alert_meta['trigger_author'] );
		$this->assertSame( 'posts', $alert_meta['trigger_context'] );
		$this->assertSame( 'updated', $alert_meta['trigger_action'] );
		$this->assertSame( 'yellow', $alert_meta['color'] );

		$this->assertSame( Alerts::POST_TYPE, get_post_type( $result['id'] ) );

		$this->assert_matches_schema( $result, $this->ability->get_output_schema() );
	}

	public function test_respects_disabled_status() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute(
			array(
				'alert_type'      => 'highlight',
				'trigger_author'  => 'any',
				'trigger_context' => 'any',
				'trigger_action'  => 'any',
				'status'          => 'wp_stream_disabled',
			)
		);

		$this->assertSame( 'wp_stream_disabled', $result['status'] );
	}
}
