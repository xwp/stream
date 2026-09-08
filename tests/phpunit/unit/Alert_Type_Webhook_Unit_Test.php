<?php
namespace WP_Stream;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

class Alert_Type_Webhook_Unit_Test extends TestCase {

	/**
	 * Last arguments passed to wp_safe_remote_request.
	 *
	 * @var array|null
	 */
	protected static $last_request;

	/**
	 * Type under test.
	 *
	 * @var Alert_Type_Webhook
	 */
	protected $type;

	protected function set_up() {
		parent::set_up();
		self::$last_request = null;
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_parse_args' )->alias( array( self::class, 'wp_parse_args_stub' ) );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );

		require_once dirname( __DIR__, 3 ) . '/alerts/class-alert-type-webhook.php';

		$plugin     = Mockery::mock( Plugin::class );
		$this->type = new Alert_Type_Webhook( $plugin );
	}

	/**
	 * Merge defaults like wp_parse_args.
	 *
	 * @param array $args     Incoming args.
	 * @param array $defaults Defaults.
	 * @return array
	 */
	public static function wp_parse_args_stub( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
	}

	/**
	 * Treat non-empty http(s) URLs as valid.
	 *
	 * @param string $url URL.
	 * @return string|false
	 */
	public static function validate_url_stub( $url ) {
		if ( 0 === strpos( (string) $url, 'https://' ) || 0 === strpos( (string) $url, 'http://' ) ) {
			return $url;
		}

		return false;
	}

	/**
	 * Capture wp_safe_remote_request arguments.
	 *
	 * @param string $url  Request URL.
	 * @param array  $args Request args.
	 * @return array
	 */
	public static function capture_remote_request( $url, $args ) {
		self::$last_request = array(
			'url'  => $url,
			'args' => $args,
		);

		return array(
			'body'     => 'ok',
			'response' => array(
				'code' => 200,
			),
		);
	}

	/**
	 * Sample record used by placeholder tests.
	 *
	 * @return array
	 */
	protected function sample_record() {
		return array(
			'summary'   => 'Updated "Hello"',
			'connector' => 'posts',
			'context'   => 'post',
			'action'    => 'updated',
			'user_id'   => 7,
			'user_role' => 'administrator',
			'ip'        => '203.0.113.10',
			'object_id' => 42,
			'blog_id'   => 1,
			'created'   => '2026-09-07 12:00:00',
		);
	}

	public function test_substitute_placeholders_json_encodes_values() {
		$template = '{"summary":{{summary}},"connector":{{connector}}}';
		$body     = Alert_Type_Webhook::substitute_placeholders( $template, $this->sample_record() );
		$decoded  = json_decode( $body, true );

		$this->assertIsArray( $decoded );
		$this->assertSame( 'Updated "Hello"', $decoded['summary'] );
		$this->assertSame( 'posts', $decoded['connector'] );
	}

	public function test_missing_placeholder_fields_become_empty_strings() {
		$body    = Alert_Type_Webhook::substitute_placeholders( '{"ip":{{ip}}}', array() );
		$decoded = json_decode( $body, true );

		$this->assertSame( '', $decoded['ip'] );
	}

	/**
	 * Host and other blocked header names are dropped.
	 *
	 * @param string $name Header name.
	 *
	 * @dataProvider data_blocked_header_names
	 */
	#[DataProvider( 'data_blocked_header_names' )]
	public function test_blocked_headers_are_omitted( $name ) {
		$headers = Alert_Type_Webhook::build_request_headers(
			'application/json',
			array(
				array(
					'name'  => $name,
					'value' => 'evil.example',
				),
				array(
					'name'  => 'X-Stream-Token',
					'value' => 'abc',
				),
			)
		);

		$this->assertArrayNotHasKey( $name, $headers );
		$this->assertSame( 'abc', $headers['X-Stream-Token'] );
		$this->assertSame( 'application/json', $headers['Content-Type'] );
	}

	/**
	 * Blocked HTTP header names.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_blocked_header_names() {
		return array(
			'host'              => array( 'Host' ),
			'content_length'    => array( 'Content-Length' ),
			'transfer_encoding' => array( 'Transfer-Encoding' ),
		);
	}

	public function test_unknown_content_type_falls_back_to_json() {
		$this->assertSame( 'application/json', Alert_Type_Webhook::sanitize_content_type( 'text/html' ) );
	}

	public function test_alert_skips_request_when_url_invalid() {
		Functions\when( 'wp_http_validate_url' )->justReturn( false );
		Functions\expect( 'wp_safe_remote_request' )->never();

		$alert              = new \stdClass();
		$alert->alert_meta  = array(
			'url'    => 'not-a-url',
			'method' => 'POST',
		);

		$this->type->alert( 1, $this->sample_record(), $alert );
		$this->assertNull( self::$last_request );
	}

	public function test_alert_skips_request_when_url_empty() {
		Functions\when( 'wp_http_validate_url' )->justReturn( false );
		Functions\expect( 'wp_safe_remote_request' )->never();

		$alert             = new \stdClass();
		$alert->alert_meta = array(
			'url' => '',
		);

		$this->type->alert( 1, $this->sample_record(), $alert );
	}

	public function test_alert_sends_put_with_substituted_body() {
		Functions\when( 'wp_http_validate_url' )->alias( array( self::class, 'validate_url_stub' ) );
		Functions\expect( 'wp_safe_remote_request' )
			->once()
			->andReturnUsing( array( self::class, 'capture_remote_request' ) );

		$alert             = new \stdClass();
		$alert->alert_meta = array(
			'url'           => 'https://example.com/hooks/stream',
			'method'        => 'PUT',
			'content_type'  => 'application/json',
			'headers'       => array(
				array(
					'name'  => 'X-Stream-Token',
					'value' => 'secret',
				),
			),
			'body_template' => '{"summary":{{summary}},"action":{{action}}}',
		);

		$this->type->alert( 9, $this->sample_record(), $alert );

		$this->assertSame( 'https://example.com/hooks/stream', self::$last_request['url'] );
		$this->assertSame( 'PUT', self::$last_request['args']['method'] );
		$this->assertSame( 15, self::$last_request['args']['timeout'] );
		$this->assertSame( 'secret', self::$last_request['args']['headers']['X-Stream-Token'] );

		$decoded = json_decode( self::$last_request['args']['body'], true );
		$this->assertSame( 'Updated "Hello"', $decoded['summary'] );
		$this->assertSame( 'updated', $decoded['action'] );
	}
}
