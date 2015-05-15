<?php
/**
 * Test: WP Stream Posts Connector.
 *
 * Contexts: Post, Page and CPT.
 * Actions: Created, Updated, Trashed, Untrashed, Deleted.
 *
 * @author WP Stream
 * @author Michele Ong <michele@wpstream.com>
 */
class Test_WP_Stream_Connector_Posts extends WP_StreamTestCase {

	/**
	 * Post Context: Action Create
	 */
	public function test_action_post_create() {

		// Create a post as auto-draft
		$post_date = current_time( 'mysql' );
		$post = $this->factory->post->create_and_get( array( 'post_status' => 'auto-draft', 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Update the status. Simulates saving an auto-saved post.
		$post->post_status = 'draft';

		// Save post
		wp_update_post( $post );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $post->ID,
				'context'   => 'post',
				'action'    => 'created',
				'meta'      => array( 'post_date_gmt' => get_gmt_from_date($post_date)  )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Post Context: Action Update
	 */
	public function test_action_post_update() {

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Transition the post to draft
		$post = get_post( $post_id );

		// Change post status to private
		$post->post_status = 'private';

		// Save post
		wp_update_post( $post );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $post_id,
				'context'   => 'post',
				'action'    => 'updated',
				'meta'      => array( 'new_status' => 'private', 'post_date_gmt' => get_gmt_from_date($post_date) )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Post Context: Action Trashed
	 */
	public function test_action_post_trashed() {

		// Create a post
		$post_date = current_time( 'mysql' );
		$post = $this->factory->post->create_and_get( array( 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Trash post
		wp_trash_post( $post->ID );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $post->ID,
				'context'   => 'post',
				'action'    => 'trashed',
				'meta'      => array( 'post_date_gmt' => get_gmt_from_date($post_date) )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Post Context: Action Untrashed
	 */
	public function test_action_post_untrashed() {

		// Create a post
		$post_date = current_time( 'mysql' );
		$post = $this->factory->post->create_and_get( array( 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Trash post
		wp_trash_post( $post->ID );

		//Untrash post
		wp_untrash_post( $post->ID );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $post->ID,
				'context'   => 'post',
				'action'    => 'untrashed',
				'meta'      => array( 'post_date_gmt' => get_gmt_from_date($post_date) )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Post Context: Action Delete
	 */
	public function test_action_post_delete() {

		// Create a post
		$post_date = current_time( 'mysql' );
		$post = $this->factory->post->create_and_get( array( 'post_title' => $post_date, 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		// Delete the post
		wp_delete_post( $post->ID, true );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_deleted_post' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $post->ID,
				'context'   => 'post',
				'action'    => 'deleted',
				'meta'      => array( 'post_title' => $post_date )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}
}
