<?php
/**
 * Integration tests for Alert_Type_Webhook.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Type_Webhook_Test
 *
 * @package WP_Stream
 * @group alerts
 */
class Alert_Type_Webhook_Test extends WP_StreamTestCase {

	/**
	 * Captured HTTP request from pre_http_request.
	 *
	 * @var array|null
	 */
	protected static $captured_http;

	public function set_up(): void {
		parent::set_up();
		self::$captured_http = null;
		add_filter( 'pre_http_request', array( self::class, 'capture_pre_http_request' ), 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( self::class, 'capture_pre_http_request' ), 10 );
		parent::tear_down();
	}

	/**
	 * Short-circuit HTTP and record the request.
	 *
	 * @param false|array|\WP_Error $preempt Whether to short-circuit.
	 * @param array                 $args    Request arguments.
	 * @param string                $url     Request URL.
	 * @return array
	 */
	public static function capture_pre_http_request( $preempt, $args, $url ) {
		unset( $preempt );
		self::$captured_http = array(
			'url'  => $url,
			'args' => $args,
		);

		return array(
			'headers'  => array(),
			'body'     => 'ok',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	public function test_webhook_type_is_registered_and_creatable() {
		$alerts = new Alerts( $this->plugin );

		$this->assertArrayHasKey( 'webhook', $alerts->alert_types );
		$this->assertInstanceOf( Alert_Type_Webhook::class, $alerts->alert_types['webhook'] );
		$this->assertTrue( $alerts->alert_types['webhook']->creatable );
		$this->assertFalse( $alerts->alert_types['ifttt']->creatable );
	}

	public function test_new_notification_values_include_webhook_not_ifttt() {
		$alerts = new Alerts( $this->plugin );
		$values = $alerts->admin_ui->get_notification_values();

		$this->assertArrayHasKey( 'webhook', $values );
		$this->assertSame( 'Outgoing Webhook', $values['webhook'] );
		$this->assertArrayNotHasKey( 'ifttt', $values );
		$this->assertArrayHasKey( 'ifttt', $alerts->admin_ui->get_notification_values( 'ifttt' ) );
		$this->assertArrayHasKey( 'ifttt', $alerts->admin_ui->get_notification_values( '', false ) );
	}

	public function test_alert_posts_via_pre_http_request() {
		$type              = new Alert_Type_Webhook( $this->plugin );
		$alert             = new \stdClass();
		$alert->alert_meta = array(
			'url'           => 'https://example.com/stream-hook',
			'method'        => 'POST',
			'body_template' => '{"summary":{{summary}}}',
		);

		$type->alert(
			1,
			array(
				'summary'   => 'Hello',
				'connector' => 'posts',
				'context'   => 'post',
				'action'    => 'updated',
				'user_id'   => 1,
				'user_role' => 'administrator',
				'ip'        => '127.0.0.1',
				'object_id' => 1,
				'blog_id'   => 1,
				'created'   => '2026-09-07 12:00:00',
			),
			$alert
		);

		$this->assertNotNull( self::$captured_http );
		$this->assertSame( 'https://example.com/stream-hook', self::$captured_http['url'] );
		$this->assertSame( 'POST', self::$captured_http['args']['method'] );
		$decoded = json_decode( self::$captured_http['args']['body'], true );
		$this->assertSame( 'Hello', $decoded['summary'] );
	}

	public function test_alert_skips_invalid_url_without_http() {
		$type              = new Alert_Type_Webhook( $this->plugin );
		$alert             = new \stdClass();
		$alert->alert_meta = array(
			'url' => 'ftp://example.com/nope',
		);

		$type->alert( 1, array( 'summary' => 'Hello' ), $alert );

		$this->assertNull( self::$captured_http );
	}
}
