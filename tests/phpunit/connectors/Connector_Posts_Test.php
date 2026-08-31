<?php
/**
 * Tests for Posts connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Connector_Posts_Test extends WP_StreamTestCase {
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
	public function setUp(): void {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		// Set static timestamps.
		$this->date     = '2007-07-04 12:30:00';
		$this->date_gmt = get_gmt_from_date( $this->date );

		// Make partial of Connector_Posts class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Posts::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

	/**
	 * Tests transition_post_status: drafted.
	 */
	public function test_callback_transition_post_status_drafted() {


		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'draft',
							'old_status'    => 'new',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s drafted',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'created' )
			);

		wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'draft',
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: published.
	 */
	public function test_callback_transition_post_status_published() {
$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'draft',
			)
		);

		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'publish',
							'old_status'    => 'draft',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s published',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'updated' )
			);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: unpublished.
	 */
	public function test_callback_transition_post_status_unpublished() {
$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'publish',
			)
		);

		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'draft',
							'old_status'    => 'publish',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s unpublished',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'updated' )
			);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: draft_saved.
	 */
	public function test_callback_transition_post_status_draft_saved() {
$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'draft',
			)
		);

		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'draft',
							'old_status'    => 'draft',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s draft saved',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'updated' )
			);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: pending.
	 */
	public function test_callback_transition_post_status_pending() {
$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'draft',
			)
		);

		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'pending',
							'old_status'    => 'draft',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s pending review',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'updated' )
			);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'pending',
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: scheduled.
	 */
	public function test_callback_transition_post_status_scheduled() {
$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'pending',
			)
		);

		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'future',
							'old_status'    => 'pending',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s scheduled for %3$s',
						'1: Post title, 2: Post type singular name, 3: Scheduled post date',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'updated' )
			);

		$time = strtotime( 'tomorrow' );
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'future',
				'post_date'     => date( 'Y-m-d H:i:s', $time ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: scheduled_published.
	 */
	public function test_callback_transition_post_status_scheduled_published() {
$time = strtotime( 'tomorrow' );
		$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_status'   => 'future',
				'post_date'     => date( 'Y-m-d H:i:s', $time ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
			)
		);

		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'publish',
							'old_status'    => 'future',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" scheduled %2$s published',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'updated' )
			);

		$time = strtotime( 'now' );
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'publish',
				'post_date'     => date( 'Y-m-d H:i:s', $time ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $time ),
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: private.
	 */
	public function test_callback_transition_post_status_private() {
$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'publish',
			)
		);

		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'private',
							'old_status'    => 'publish',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s privately published',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'updated' )
			);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: trashed.
	 */
	public function test_callback_transition_post_status_trashed() {
$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'private',
			)
		);

		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'trash',
							'old_status'    => 'private',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s trashed',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'trashed' )
			);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'trash',
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: untrashed.
	 */
	public function test_callback_transition_post_status_untrashed() {
$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'trash',
			)
		);

		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'publish',
							'old_status'    => 'trash',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s restored from trash',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'untrashed' )
			);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: updated.
	 */
	public function test_callback_transition_post_status_updated() {
$post_id = wp_insert_post(
			array(
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_date'     => $this->date,
				'post_date_gmt' => $this->date_gmt,
				'post_status'   => 'publish',
			)
		);

		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'publish',
							'old_status'    => 'publish',
						);

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
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'updated' )
			);

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Tests transition_post_status: created_published.
	 */
	public function test_callback_transition_post_status_created_published() {


		self::$expected_post_context = array(
							'post_title'    => 'Test post',
							'singular_name' => 'post',
							'new_status'    => 'publish',
							'old_status'    => 'new',
						);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" %2$s published',
						'1: Post title, 2: Post type singular name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_post_transition_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'post' ),
				$this->equalTo( 'created' )
			);

		wp_insert_post(
			array(
				'post_title'   => 'Test post',
				'post_content' => 'Lorem ipsum dolor...',
				'post_status'  => 'publish',
			)
		);

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );
	}

	/**
	 * Newly published attachments are excluded from transition logging.
	 */
	public function test_callback_transition_post_status_ignores_attachment() {
		$this->mock->expects( $this->never() )
			->method( 'log' );

		wp_insert_post(
			array(
				'post_title'   => 'Test attachment',
				'post_content' => 'Lorem ipsum dolor...',
				'post_status'  => 'publish',
				'post_type'    => 'attachment',
			)
		);
	}

	/**
	 * Expected post transition context subset.
	 *
	 * @var array
	 */
	private static $expected_post_context = array();

	/**
	 * Assert post transition log context contains expected keys.
	 *
	 * @param mixed $context Log context argument.
	 * @return bool
	 */
	public static function assert_post_transition_context( $context ): bool {
		if ( ! is_array( $context ) ) {
			return false;
		}
		foreach ( self::$expected_post_context as $key => $value ) {
			if ( ! array_key_exists( $key, $context ) || $context[ $key ] !== $value ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Tests "deleted_post" callback function.
	 */
	public function test_callback_deleted_post() {
		// Create post for later use.
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test post',
				'post_content' => 'Lorem ipsum dolor...',
				'post_status'  => 'publish',
			)
		);

		$auto_draft_post_id = wp_insert_post(
			array(
				'post_title'   => 'Test post',
				'post_content' => 'Lorem ipsum dolor...',
				'post_status'  => 'auto-draft',
			)
		);

		$attachment_post_id = wp_insert_post(
			array(
				'post_title'   => 'Test post',
				'post_content' => 'Lorem ipsum dolor...',
				'post_type'    => 'attachment',
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

		// Delete auto-drafted post and attachment to confirm these actions is ignored.
		wp_delete_post( $auto_draft_post_id, true );
		wp_delete_post( $attachment_post_id, true );

		// Confirm callback execution.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_deleted_post' ) );
	}
}
