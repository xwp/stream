<?php
namespace WP_Stream;

/**
 * Class Test_Alert_Type_Email
 *
 * @package WP_Stream
 * @group alerts
 */
class Test_Alert_Type_Email extends WP_StreamTestCase {

	/**
	 * Holds the alert type object under test.
	 *
	 * @var Alert_Type_Email
	 */
	protected $alert_type;

	/**
	 * Holds an alert post ID used by the tests.
	 *
	 * @var int
	 */
	protected static $alert_post_id;

	public function setUp(): void {
		parent::setUp();

		$this->alert_type = new Alert_Type_Email( $this->plugin );

		if ( empty( self::$alert_post_id ) || ! get_post( self::$alert_post_id ) ) {
			self::$alert_post_id = self::factory()->post->create(
				array(
					'post_type'   => Alerts::POST_TYPE,
					'post_status' => Alerts::STATUS_ENABLED,
				)
			);
		}
	}

	public function tearDown(): void {
		remove_all_filters( 'wp_stream_alert_email_throttle' );
		delete_post_meta( self::$alert_post_id, Alert_Type_Email::LAST_SENT_META_KEY );

		parent::tearDown();
	}

	public function test_send_allowed_when_no_email_was_sent_before() {
		$alert = $this->plugin->alerts->get_alert( self::$alert_post_id );

		$this->assertTrue( $this->alert_type->is_send_allowed( $alert ) );
	}

	public function test_send_blocked_within_throttle_interval() {
		$alert = $this->plugin->alerts->get_alert( self::$alert_post_id );

		$this->alert_type->update_last_sent( $alert );

		$this->assertFalse( $this->alert_type->is_send_allowed( $alert ) );
	}

	public function test_send_allowed_after_throttle_interval() {
		$alert = $this->plugin->alerts->get_alert( self::$alert_post_id );

		update_post_meta( self::$alert_post_id, Alert_Type_Email::LAST_SENT_META_KEY, time() - 301 );

		$this->assertTrue( $this->alert_type->is_send_allowed( $alert ) );
	}

	public function test_send_allowed_with_zero_throttle() {
		add_filter( 'wp_stream_alert_email_throttle', '__return_zero' );

		$alert = $this->plugin->alerts->get_alert( self::$alert_post_id );

		$this->alert_type->update_last_sent( $alert );

		$this->assertTrue( $this->alert_type->is_send_allowed( $alert ) );
	}

	public function test_alert_sends_one_email_and_throttles_the_second() {
		reset_phpmailer_instance();
		$mailer = tests_retrieve_phpmailer_instance();

		$alert             = $this->plugin->alerts->get_alert( self::$alert_post_id );
		$alert->alert_meta = array_merge(
			(array) $alert->alert_meta,
			array(
				'email_recipient' => 'admin@example.org',
				'email_subject'   => 'Stream alert',
			)
		);

		$this->alert_type->alert( 0, $this->dummy_record_data(), $alert );

		$this->assertCount( 1, $mailer->mock_sent );

		// A second trigger inside the throttle window sends no further email.
		$this->alert_type->alert( 0, $this->dummy_record_data(), $alert );

		$this->assertCount( 1, $mailer->mock_sent );
	}

	public function test_alert_sends_every_email_with_zero_throttle() {
		add_filter( 'wp_stream_alert_email_throttle', '__return_zero' );

		reset_phpmailer_instance();
		$mailer = tests_retrieve_phpmailer_instance();

		$alert             = $this->plugin->alerts->get_alert( self::$alert_post_id );
		$alert->alert_meta = array_merge(
			(array) $alert->alert_meta,
			array(
				'email_recipient' => 'admin@example.org',
				'email_subject'   => 'Stream alert',
			)
		);

		$this->alert_type->alert( 0, $this->dummy_record_data(), $alert );
		$this->alert_type->alert( 0, $this->dummy_record_data(), $alert );

		$this->assertCount( 2, $mailer->mock_sent );
	}

	private function dummy_record_data() {
		return array(
			'object_id' => null,
			'site_id'   => '1',
			'blog_id'   => get_current_blog_id(),
			'user_id'   => '1',
			'user_role' => 'administrator',
			'created'   => gmdate( 'Y-m-d H:i:s' ),
			'summary'   => '"Hello Dave" plugin activated',
			'ip'        => '192.168.0.1',
			'connector' => 'installer',
			'context'   => 'plugins',
			'action'    => 'activated',
		);
	}
}
