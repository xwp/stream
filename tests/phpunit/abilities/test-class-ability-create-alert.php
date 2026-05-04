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

		// Connector-only trigger (no dash) -- stored with empty context, matching
		// how the admin form handles "posts" vs "posts-post".
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
		$this->assertSame( 'posts', $alert_meta['trigger_connector'] );
		$this->assertSame( '', $alert_meta['trigger_context'] );
		$this->assertSame( 'updated', $alert_meta['trigger_action'] );
		$this->assertSame( 'yellow', $alert_meta['color'] );

		$this->assertSame( Alerts::POST_TYPE, get_post_type( $result['id'] ) );

		$this->assert_matches_schema( $result, $this->ability->get_output_schema() );
	}

	public function test_splits_connector_dash_context_input() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute(
			array(
				'alert_type'      => 'highlight',
				'trigger_author'  => 'any',
				'trigger_context' => 'posts-post',
				'trigger_action'  => 'updated',
			)
		);

		$alert_meta = (array) get_post_meta( $result['id'], 'alert_meta', true );
		$this->assertSame( 'posts', $alert_meta['trigger_connector'] );
		$this->assertSame( 'post', $alert_meta['trigger_context'] );
	}

	public function test_post_title_is_not_auto_draft() {
		wp_set_current_user( $this->admin_user_id );

		$result = $this->ability->execute(
			array(
				'alert_type'      => 'highlight',
				'trigger_author'  => 'any',
				'trigger_context' => 'posts',
				'trigger_action'  => 'updated',
			)
		);

		$this->assertNotEmpty( $result['title'], 'Alert title must not be empty (would render as "Auto Draft" in admin).' );
		$this->assertNotSame( 'Auto Draft', $result['title'] );
	}

	public function test_rejects_unknown_alert_type() {
		wp_set_current_user( $this->admin_user_id );

		// 'sms' is not a registered notifier; the ability should reject before insert.
		$result = $this->ability->execute(
			array(
				'alert_type'      => 'sms',
				'trigger_author'  => 'any',
				'trigger_context' => 'posts',
				'trigger_action'  => 'updated',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'stream_unknown_alert_type', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );

		// No post should have been inserted before the validation error.
		$alerts = get_posts(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$this->assertEmpty( $alerts );
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
