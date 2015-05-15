<?php
/**
 * Test: WP Stream Comments Connector.
 *
 * Contexts: Comments.
 * Actions: Created, Edited, Replied, Approved, Unapproved, Trashed, Restored, Marked as Spam, Unmarked as Spam, 
 * Deleted, Duplicate, Throttled.
 *
 * @author WP Stream
 * @author Michele Ong <michele@wpstream.com>
 */
class Test_WP_Stream_Connector_Comments extends WP_StreamTestCase {

	/**
	 * Comment Context: Action Create
	 */
	public function test_action_comment_create() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_comment_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment_id,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'created',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Comment Context: Action Edit
	 */
	public function test_action_comment_edit() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment = $this->factory->comment->create_and_get( array( 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		$original = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'edited',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Update comment
		$comment->comment_content = "TEST";

		wp_update_comment((array)$comment);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_comment_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'edited',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Comment Context: Action Reply
	 */
	public function test_action_comment_reply() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		// Create a comment reply
		$reply = $this->factory->comment->create_and_get( array( 'comment_parent' => $comment_id, 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		$original = wp_stream_query(
			array(
				'object_id' => $reply->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'replied',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_comment_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $reply->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'replied',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Comment Context: Action Unapproved
	 */
	public function test_action_comment_unapproved() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post as auto-draft
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment = $this->factory->comment->create_and_get( array( 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		$original = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'unapproved',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Unapprove the comment
		$comment->comment_approved = 0;

		wp_update_comment((array)$comment);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_comment_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'unapproved',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Comment Context: Action Approved
	 */
	public function test_action_comment_approved() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment = $this->factory->comment->create_and_get( array( 'comment_approved' => 0, 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		// Approve the comment
		$comment->comment_approved = 1;

		$original = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'approved',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		wp_update_comment((array)$comment);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_comment_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'approved',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Comment Context: Action Trashed
	 */
	public function test_action_comment_trashed() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment = $this->factory->comment->create_and_get( array( 'comment_approved' => 0, 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		$original = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'trashed',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Trash the comment
		wp_trash_comment($comment->comment_ID);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_comment_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'trashed',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Comment Context: Action Restored
	 */
	public function test_action_comment_restored() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment = $this->factory->comment->create_and_get( array( 'comment_approved' => 0, 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		$original = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'untrashed',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Trash and restore comment
		wp_trash_comment($comment->comment_ID);

		wp_untrash_comment($comment->comment_ID);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_comment_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'untrashed',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Comment Context: Action Marked as Spam
	 */
	public function test_action_comment_marked_as_spam() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		$original = wp_stream_query(
			array(
				'object_id' => $comment_id,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'spammed',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Spam comment
		wp_spam_comment($comment_id);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_comment_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment_id,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'spammed',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Comment Context: Action Unmarked as Spam
	 */
	public function test_action_comment_unmarked_as_spam() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		$original = wp_stream_query(
			array(
				'object_id' => $comment_id,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'unspammed',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Unspam comment
		wp_spam_comment($comment_id);

		wp_unspam_comment($comment_id);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_comment_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment_id,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'unspammed',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Comment Context: Action Delete
	 */
	public function test_action_comment_delete() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post as auto-draft
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		$original = wp_stream_query(
			array(
				'object_id' => $comment_id,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'deleted',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Delete the comment
		wp_delete_comment($comment_id, true);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_comment_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment_id,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'deleted',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Comment Context: Action Duplicate
	 */
	public function test_action_comment_duplicate() {
		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment = $this->factory->comment->create_and_get( array( 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		$original = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'duplicate',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Duplicate the comment
		$contents = (array)$comment;
		unset($contents['comment_ID']);
		unset($contents['comment_approved']);
		try {
			wp_new_comment( $contents );
		}
		catch(Exception $e) {}

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_comment_duplicate_trigger' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'duplicate',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) - count( $original ) );
	}

	/**
	 * Comment Context: Action Throttled
	 */
	public function test_action_comment_throttled() {
		$this->markTestSkipped('Need to set advanced flood tracking on wp_stream options programmatically');

		$time = time();
		$post_title = 'Post Title ' . $time;

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Create a comment
		$comment = $this->factory->comment->create_and_get( array( 'comment_post_ID' => $post_id, 'comment_date' => $post_date ) );

		$original = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'flood',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Flood comments
		$contents = (array)$comment;
		unset($contents['comment_ID']);
		unset($contents['comment_approved']);
		try {
			for($i=0;$i<10;$i++) {
				$contents['comment_content'] .= ' '.$i;
				wp_new_comment( $contents );
			}
		}
		catch(Exception $e) {}

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_comment_flood_trigger' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $comment->comment_ID,
				'connector' => 'comments',
				'context'   => 'post',
				'action'    => 'flood',
				'meta'      => array( 'post_title' => '"' . $post_title . '"' )
			)
		);

		// Check if the DB entry is okay
		$this->assertGreaterThanOrEqual( 1, count( $result ) - count( $original ) );
	}
}
