<?php
/**
 * Tests for bbPress connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Connector_BbPress_Test extends WP_StreamTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->mock = new Connector_BbPress();
	}

	/**
	 * Build the record data the Posts connector passes to wp_stream_log_data for a reply.
	 *
	 * @param int    $reply_id Reply post ID.
	 * @param string $action   Record action.
	 *
	 * @return array
	 */
	private function get_reply_log_data( $reply_id, $action ) {
		return array(
			'connector' => 'posts',
			'context'   => 'reply',
			'action'    => $action,
			'object_id' => $reply_id,
			'message'   => '"%1$s" %2$s',
			'args'      => array(
				'post_title' => 'Reply',
			),
		);
	}

	/**
	 * Test that a reply record is labelled with its parent topic's title.
	 */
	public function test_log_override_reply_uses_topic_title() {
		$topic_id = self::factory()->post->create( array( 'post_title' => 'Read this first' ) );
		$reply_id = self::factory()->post->create(
			array(
				'post_title'  => 'Reply',
				'post_parent' => $topic_id,
			)
		);

		$data = $this->mock->log_override( $this->get_reply_log_data( $reply_id, 'updated' ) );

		$this->assertSame( 'bbpress', $data['connector'] );
		$this->assertSame( 'Reply to: Read this first', $data['args']['post_title'] );
		$this->assertSame( esc_html__( 'Replied on "%s"', 'stream' ), $data['message'] );
	}

	/**
	 * Test that a reply whose parent topic no longer exists is still logged.
	 *
	 * The wp_scheduled_delete cron can purge a trashed topic before its replies.
	 */
	public function test_log_override_reply_with_deleted_topic() {
		$topic_id = self::factory()->post->create( array( 'post_title' => 'Read this first' ) );
		$reply_id = self::factory()->post->create(
			array(
				'post_title'  => 'Reply',
				'post_parent' => $topic_id,
			)
		);

		wp_delete_post( $topic_id, true );

		$data = $this->mock->log_override( $this->get_reply_log_data( $reply_id, 'deleted' ) );

		$this->assertSame( 'bbpress', $data['connector'] );
		$this->assertSame( 'Reply', $data['args']['post_title'] );
	}
}
