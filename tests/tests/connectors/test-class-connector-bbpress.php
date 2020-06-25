<?php
/**
 * WP Integration Test w/ bbPress
 *
 * Tests for bbPress Connector class callbacks.
 */
namespace WP_Stream;

class Test_WP_Stream_Connector_BbPress extends WP_StreamTestCase {
	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		$post_connector = new Connector_Posts();
		$post_connector->register();

		// Make partial of Connector_BbPress class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_BbPress::class )
			->setMethods( [ 'log' ] )
			->getMock();

		$this->mock->register();
	}

	/**
	 * Runs after each test
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Confirm that bbPress is installed and active.
	 */
	public function test_bbpress_installed_and_activated() {
		$this->assertTrue( class_exists( 'bbPress' ) );
	}

	/**
	 * Tests the "log_override" callback
	 */
	public function test_log_override() {
		// Has asserted flag.
		$asserted = false;

		// Set assertion callback.
		add_action(
			'wp_stream_log_data',
			function( $data ) use( &$asserted ) {
				$message = _x(
					'"%1$s" %2$s updated',
					'1: Post title, 2: Post type singular name',
					'stream'
				);

				$this->assertSame( 'bbpress', $data['connector'] );
				$this->assertSame( $message, $data['message'] );
				$this->assertSame( 'Test Forum', $data['args']['post_title'] );
				$this->assertSame( 'forum', $data['context'] );
				$this->assertSame( 'updated', $data['action'] );
				$asserted = true;

				return $data;
			},
			99
		);

		// Create forum to trigger Connector_Posts' transition_status callback.
		$forum_id = bbp_insert_forum( [ 'post_title' => 'Test Forum' ] );

		// Check ID.
		$this->assertGreaterThan( 0, $forum_id );

		// Confirm that assertion callback executed.
		$this->assertTrue( $asserted );
	}

	/**
	 * Test "test_callback_bbp_toggle_topic_admin" callback.
	 */
	public function test_callback_bbp_toggle_topic_admin() {
		// Create forum and topic for later use.
		$forum_id = bbp_insert_forum( [ 'post_title' => 'Test Forum' ] );
		$topic_id = bbp_insert_topic(
			[
				'post_title'  => 'Test Topic',
				'post_parent' => $forum_id,
			]
		);
		$topic = get_post( $topic_id );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( _x( '%1$s "%2$s" topic', '1: Action, 2: Topic title', 'stream' ) ),
				$this->equalTo(
					array(
						'action_title' => esc_html_x( 'Closed', 'bbpress', 'stream' ),
						'topic_title'  => $topic->post_title,
						'action'       => 'closed',
					)
				),
				$this->equalTo( $topic_id ),
				$this->equalTo( 'topic' ),
				$this->equalTo( 'closed' )
			);

		// Manually trigger callback.
		do_action(
			'bbp_toggle_topic_admin',
			bbp_close_topic( $topic_id ),
			array( 'ID' => $topic_id ),
			'bbp_toggle_topic_close',
			array(
				'bbp_topic_toggle_notice' => 'closed',
				'topic_id'                => $topic_id
			)
		);

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( 'wp_stream_test_callback_bbp_toggle_topic_admin' ) );
	}
}
