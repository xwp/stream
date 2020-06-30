<?php
namespace WP_Stream;

class Test_WP_Stream_Connector_Posts extends WP_StreamTestCase {
	/**
	 * Holds local timestamp in "Y-m-d H:i:s" format
	 *
	 * @var string
	 */
	private $date;

	/**
	 * Holds GMT timestamp in "Y-m-d H:i:s" format
	 *
	 * @var string
	 */
	private $date_gmt;

	/**
	 * Runs before each test.
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		// Set static timestamps.
		$this->date     = '2007-07-04 12:30:00';
		$this->date_gmt = get_gmt_from_date( $this->date );

		// Make partial of Connector_Posts class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Posts::class )
			->setMethods( [ 'log' ] )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

	/**
	 * Tests "transition_post_status" callback function.
	 */
	public function test_callback_transition_post_status() {
		// Set expected calls for the Mock.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s updated',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'post_title'    => 'Test post',
						'singular_name' => 'post',
						'post_date'     => $this->date,
						'post_date_gmt' => $this->date_gmt,
						'new_status'    => 'publish',
						'old_status'    => 'new',
						'revision_id'   => null,
					)
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'updated' )
			);

		// Create post and trigger mock.
		wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'publish'
			)
		);

		// Confirm callback execution.
		$this->assertGreaterThan( 0, did_action( 'wp_stream_test_callback_transition_post_status' ) );
	}

	/**
	 * Tests "deleted_post" callback function.
	 */
	public function test_callback_deleted_post() {
		// Create post for later use.
		$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_status'   => 'publish'
			)
		);

		// Set expected calls for the Mock.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s deleted from trash',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'post_title'    => 'Test post',
						'singular_name' => 'post',
					)
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'deleted' )
			);

		// Delete post and trigger mock.
		wp_delete_post( $post_id, true );

		// Confirm callback execution.
		$this->assertGreaterThan( 0, did_action( 'wp_stream_test_callback_deleted_post' ) );
	}

}
