<?php
/**
 * Tests for Ability_Get_Alerts.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Get_Alerts_Test
 */
class Ability_Get_Alerts_Test extends Abilities_TestCase {

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

	/**
	 * Alert destinations are configured behind the Stream settings capability
	 * but listed behind view_stream, so credentials in alert_meta would cross a
	 * privilege boundary. A Slack incoming webhook URL is a bearer credential:
	 * possession alone is enough to post into the channel.
	 */
	public function test_slack_webhook_is_redacted() {
		wp_set_current_user( $this->admin_user_id );

		// Deliberately not shaped like a real Slack webhook URL: the redaction
		// keys off the alert_meta key name, not the value, and a realistic
		// looking URL trips secret scanning on push.
		$webhook = 'https://example.test/redaction-fixture/not-a-real-webhook';

		$post_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'wp_stream_enabled',
				'post_title'  => 'Slack alert',
			)
		);
		update_post_meta( $post_id, 'alert_type', 'slack' );
		update_post_meta(
			$post_id,
			'alert_meta',
			array(
				'webhook'        => $webhook,
				'channel'        => '#general',
				'trigger_action' => 'any',
			)
		);

		$row = $this->find_alert_row( $this->ability->execute( array( 'status' => 'any' ) ), $post_id );

		$this->assertArrayNotHasKey( 'webhook', (array) $row['alert_meta'], 'The webhook URL must not be returned.' );
		$this->assertTrue(
			( (array) $row['alert_meta'] )['webhook_configured'],
			'Callers still need to know a webhook is configured.'
		);

		// Non-secret configuration must survive untouched.
		$this->assertSame( '#general', ( (array) $row['alert_meta'] )['channel'] );

		$this->assertStringNotContainsString(
			$webhook,
			(string) wp_json_encode( $row ),
			'The webhook URL must not appear anywhere in the serialized response.'
		);
	}

	/**
	 * Same boundary for the IFTTT Maker key, which is reusable across every
	 * applet on the owning account.
	 */
	public function test_ifttt_maker_key_is_redacted() {
		wp_set_current_user( $this->admin_user_id );

		$maker_key = 'dxxxxxxxxxxxxxxxxxxxxxx';

		$post_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'wp_stream_enabled',
				'post_title'  => 'IFTTT alert',
			)
		);
		update_post_meta( $post_id, 'alert_type', 'ifttt' );
		update_post_meta(
			$post_id,
			'alert_meta',
			array(
				'maker_key'  => $maker_key,
				'event_name' => 'stream_event',
			)
		);

		$row = $this->find_alert_row( $this->ability->execute( array( 'status' => 'any' ) ), $post_id );

		$this->assertArrayNotHasKey( 'maker_key', (array) $row['alert_meta'] );
		$this->assertTrue( ( (array) $row['alert_meta'] )['maker_key_configured'] );
		$this->assertSame( 'stream_event', ( (array) $row['alert_meta'] )['event_name'] );
		$this->assertStringNotContainsString( $maker_key, (string) wp_json_encode( $row ) );
	}

	/**
	 * An unconfigured secret reports false rather than being reported as
	 * present, so the marker is meaningful either way.
	 */
	public function test_unconfigured_secret_reports_false() {
		wp_set_current_user( $this->admin_user_id );

		$post_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'wp_stream_enabled',
				'post_title'  => 'Slack alert without webhook',
			)
		);
		update_post_meta( $post_id, 'alert_type', 'slack' );
		update_post_meta( $post_id, 'alert_meta', array( 'webhook' => '' ) );

		$row = $this->find_alert_row( $this->ability->execute( array( 'status' => 'any' ) ), $post_id );

		$this->assertFalse( ( (array) $row['alert_meta'] )['webhook_configured'] );
	}

	/**
	 * Third-party alert types can store destination secrets under names Stream
	 * does not know, so the redaction list is filterable.
	 */
	public function test_secret_alert_meta_keys_are_filterable() {
		wp_set_current_user( $this->admin_user_id );

		$post_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'wp_stream_enabled',
				'post_title'  => 'Third-party alert',
			)
		);
		update_post_meta( $post_id, 'alert_type', 'custom' );
		update_post_meta(
			$post_id,
			'alert_meta',
			array(
				'custom_api_secret' => 'super-secret-value',
				'endpoint'          => 'https://example.com/notify',
			)
		);

		$add_key = static function ( $keys ) {
			$keys[] = 'custom_api_secret';
			return $keys;
		};
		add_filter( 'wp_stream_secret_alert_meta_keys', $add_key );

		try {
			$row  = $this->find_alert_row( $this->ability->execute( array( 'status' => 'any' ) ), $post_id );
			$meta = (array) $row['alert_meta'];

			$this->assertArrayNotHasKey( 'custom_api_secret', $meta );
			$this->assertTrue( $meta['custom_api_secret_configured'] );
			$this->assertSame( 'https://example.com/notify', $meta['endpoint'] );
			$this->assertStringNotContainsString( 'super-secret-value', (string) wp_json_encode( $row ) );
		} finally {
			remove_filter( 'wp_stream_secret_alert_meta_keys', $add_key );
		}
	}

	/**
	 * Locate a single alert row in the ability output by post ID.
	 *
	 * @param array $result  Ability output.
	 * @param int   $post_id Alert post ID.
	 * @return array
	 */
	private function find_alert_row( $result, $post_id ) {
		foreach ( $result as $entry ) {
			if ( $entry['id'] === $post_id ) {
				return $entry;
			}
		}

		$this->fail( 'Seeded alert missing from get-alerts output.' );
	}
}
