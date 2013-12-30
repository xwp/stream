<?php
/**
 * Tests stream main class
 *
 * @author X-Team
 * @author Jonathan Bardo <jonathan.bardo@x-team.com>
 */
class Test_WP_Stream_Connector_Posts extends WP_StreamTestCase {

	/**
	 * Check if post status log action is triggered correcltly
	 */
	public function test_transition_post_status() {
		//Create a post
		$post_id = $this->factory->post->create();

		//Transition the post to draft
		$post = get_post( $post_id );

		//Change post status to private
		$post->post_status = 'private';

		//Save post
		wp_update_post( $post );

		//Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_transition_post_status' ) );

		//Check if the entry is in the database
		$result = stream_query(
			array(
				'object_id'  => $post_id,
				'context'    => 'post',
				'action'     => 'updated',
				'meta_key'   => 'new_status',
				'meta_value' => 'private',
			)
		);

		//Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Check if a log if send to the DB when we delete a post
	 *
	 * @return void
	 */
	public function test_delete_post_log() {
		//Create a post
		$post_id = $this->factory->post->create();

		//Delete the post
		wp_delete_post( $post_id, true );

		//Test the
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_deleted_post' ) );

		//Check if the entry is in the database
		$result = stream_query(
			array(
				'object_id' => $post_id,
				'context'   => 'post',
				'action'    => 'deleted',
			)
		);

		//Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

}
