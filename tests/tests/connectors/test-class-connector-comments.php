<?php
namespace WP_Stream;

class Test_WP_Stream_Connector_Comments extends WP_StreamTestCase {
	private $date;
	private $date_gmt;

	public function setUp() {
		parent::setUp();

		$this->date     = '2007-07-04 12:30:00';
		$this->date_gmt = get_gmt_from_date( $this->date );

		// Make partial of Connector_ACF class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Comments::class )
			->setMethods( [ 'log' ] )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

	public function test_callback_wp_insert_comment() {
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'johndoe',
				'role'       => 'editor',
			]
		);
		$post_id = wp_insert_post(
			[
				'post_title'    => 'Test post',
				'post_content'  => 'Lorem ipsum dolor...',
				'post_status'   => 'publish',
			]
		);

		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' )
			->withConsecutive(
				[
					$this->equalTo(
						_x(
							'New %4$s by %1$s on %2$s %3$s',
							'1: Comment author, 2: Post title 3: Comment status, 4: Comment type',
							'stream'
						)
					),
					$this->equalTo(
						[
							'user_name'      => 'Jim Bean',
							'post_title'     => '"Test post"',
							'comment_status' => 'pending approval',
							'comment_type'   => 'comment',
							'post_id'        => $post_id,
							'is_spam'        => false,
						]
					),
					$this->greaterThan( 0 ),
					$this->equalTo( 'post' ),
					$this->equalTo( 'created' ),
					$this->equalTo( 0 )
				],
				[
					$this->equalTo(
						_x(
							'Reply to %1$s\'s %5$s by %2$s on %3$s %4$s',
							"1: Parent comment's author, 2: Comment author, 3: Post title, 4: Comment status, 5: Comment type",
							'stream'
						)
					),
					$this->equalTo(
						[
							'parent_user_name' => 'Jim Bean',
							'user_name'        => 'Jim Bean',
							'post_title'       => '"Test post"',
							'comment_status'   => 'pending approval',
							'comment_type'     => 'comment',
							'post_id'          => "$post_id",
						]
					),
					$this->greaterThan( 0 ),
					$this->equalTo( 'post' ),
					$this->equalTo( 'replied' ),
					$this->equalTo( 0 )
				],
			);

		// Do stuff.
		$comment_id = wp_insert_comment(
			[
				'comment_content'      => 'Lorem ipsum dolor...',
				'comment_author'       => 'Jim Bean',
				'comment_author_email' => 'jim_bean@example.com',
				'comment_author_IP'    => '::1',
				'comment_post_ID'      => $post_id,
			]
		);
		wp_insert_comment(
			[
				'comment_content'      => 'Lorem ipsum dolor...',
				'comment_author'       => 'Jim Bean',
				'comment_author_email' => 'jim_bean@example.com',
				'comment_author_IP'    => '::1',
				'comment_post_ID'      => $post_id,
				'comment_parent'       => $comment_id,
			]
		);

		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_wp_insert_comment' ) );
	}

}
