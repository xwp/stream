<?php

class X_Stream_Context_Post extends X_Stream_Context {

	public $actions = array(
		'wp_insert_post',
	);

	public function callback_wp_insert_post( $post_id, $post ) {
		if ( in_array( $post->post_status, array( 'revision', 'auto-draft' ) ) ) {
			return;
		}
		self::log(
			__( 'Add post #%d (%s) as %s', 'x-stream' ),
			array(
				$post_id,
				$post->post_title,
				$post->post_status,
			),
			$post_id,
			'insert-post'
		);
	}

	public function callback_transition_post_status( $new, $old, $post ) {
		self::log(
			__( 'Changed post #%d status from %s to %s', 'x-stream' ),
			array(
				$post->ID,
				$old,
				$new,
			),
			$post->ID,
			'change-post-status'
		);
	}
}