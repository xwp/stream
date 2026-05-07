<?php
/**
 * Tests for Ability_Get_Alerts.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Get_Alerts
 */
class Test_Ability_Get_Alerts extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Get_Alerts
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-get-alerts.php';
		$this->ability = new Ability_Get_Alerts( $this->plugin );
	}

	public function test_name_and_schema() {
		$this->assertSame( 'stream/get-alerts', $this->ability->get_name() );

		$input = $this->ability->get_input_schema();
		$this->assertSame( array( 'enabled', 'disabled', 'any' ), $input['properties']['status']['enum'] );

		$output = $this->ability->get_output_schema();
		$this->assertSame( 'array', $output['type'] );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_returns_seeded_alerts() {
		wp_set_current_user( $this->admin_user_id );

		$enabled_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'wp_stream_enabled',
				'post_title'  => 'Enabled alert',
			)
		);
		update_post_meta( $enabled_id, 'alert_type', 'highlight' );
		update_post_meta(
			$enabled_id,
			'alert_meta',
			array(
				'trigger_author'  => 'any',
				'trigger_context' => 'any',
				'trigger_action'  => 'any',
			)
		);

		$disabled_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'wp_stream_disabled',
				'post_title'  => 'Disabled alert',
			)
		);
		update_post_meta( $disabled_id, 'alert_type', 'email' );

		$all      = $this->ability->execute( array( 'status' => 'any' ) );
		$enabled  = $this->ability->execute( array( 'status' => 'enabled' ) );
		$disabled = $this->ability->execute( array( 'status' => 'disabled' ) );

		$this->assertCount( 2, $all );
		$this->assertCount( 1, $enabled );
		$this->assertCount( 1, $disabled );
		$this->assertSame( $enabled_id, $enabled[0]['id'] );
		$this->assertSame( 'highlight', $enabled[0]['alert_type'] );
		$this->assertSame( $disabled_id, $disabled[0]['id'] );
	}

	public function test_alert_meta_is_normalized_to_object_when_missing() {
		wp_set_current_user( $this->admin_user_id );

		// Alert with no alert_meta post meta at all. get_post_meta() returns ''
		// in that case; the ability must coerce that to {} rather than [""], or
		// the response will violate the declared object output schema.
		$post_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'wp_stream_enabled',
				'post_title'  => 'Alert without meta',
			)
		);

		$result = $this->ability->execute( array( 'status' => 'any' ) );
		$row    = null;
		foreach ( $result as $entry ) {
			if ( $entry['id'] === $post_id ) {
				$row = $entry;
				break;
			}
		}

		$this->assertNotNull( $row, 'Seeded alert missing from get-alerts output.' );

		// Must be a real object so wp_json_encode() emits {}. An empty PHP
		// array() would JSON-encode as [] and violate the declared object
		// output schema.
		$this->assertInstanceOf( \stdClass::class, $row['alert_meta'] );

		$encoded = wp_json_encode( $row );
		$this->assertNotFalse( $encoded );
		$this->assertStringContainsString( '"alert_meta":{}', $encoded, 'Missing alert_meta must serialize as {}, not [].' );

		// Schema validates as well — exercises the live contract.
		$this->assert_matches_schema( $result, $this->ability->get_output_schema() );
	}
}
