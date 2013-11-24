<?php

class X_Stream_Context_Post extends X_Stream_Context {

	public static $name;

	public static $actions = array(
		'transition_post_status',
		'deleted_post',
	);

	public static function get_name() {
		return __( 'Posts', 'wp_stream' );
	}

	public static function callback_transition_post_status( $new, $old, $post ) {
		if ( in_array(
			$post->post_type,
			apply_filters(
				'wp_stream_post_exclude_post_types',
				array(
					'stream',
					)
				)
			)
			) {
			return;
		}
		if ( in_array( $new, array( 'auto-draft', 'inherit' ) ) ) {
			return;
		}
		elseif ( $old == 'auto-draft' && $new == 'draft' ) {
			$message = __( 'Drafted a new post "#%d - %s"', 'wp_stream' );
			$action = __( 'Created', 'wp_stream' );
		}
		elseif ( $old == 'auto-draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = __( 'Published a new post "#%d - %s"', 'wp_stream' );
			$action = __( 'Created', 'wp_stream' );
		}
		elseif ( $old == 'draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = __( 'Published a post "#%d - %s"', 'wp_stream' );
		}
		elseif ( $old == 'publish' && ( in_array( $new, array( 'draft' ) ) ) ) {
			$message = __( 'Unpublished a post "#%d - %s"', 'wp_stream' );
		}
		elseif ( $new == 'trash' ) {
			$message = __( 'Trashed a post "#%d - %s"', 'wp_stream' );
			$action = __( 'Trashed', 'wp_stream' );
		}
		else {
			$message = __( 'Updated post "#%d - %s"', 'wp_stream' );
		}

		if ( empty( $action ) ) {
			$action = __( 'Updated', 'wp_stream' );
		}

		$revision_id = null;
		if ( wp_revisions_enabled( $post ) ) {
			$revision = get_children( array(
				'post_type' => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $post->ID,
				'posts_per_page' => 1,
				'order' => 'desc',
				'fields' => 'ids',
				) );
			if ( $revision ) {
				$revision_id = $revision[0];
			}
		}

		self::log(
			$message,
			array(
				$post->ID,
				$post->post_title,
				$new,
				$old,
				$revision_id,
			),
			$post->ID,
			$action
		);
	}

	public static function callback_deleted_post( $post_id ) {
		$post = get_post( $post_id );
		self::log(
			__( 'Deleted a post "#%d - %s"', 'wp_stream' ),
			array(
				$post->ID,
				$post->post_title,
			),
			$post->ID,
			__( 'Deleted', 'wp_stream' )
		);
	}

	public static function action_links( $links, $stream_id, $object_id ) {
		$actions = wp_get_post_terms( $stream_id, 'stream_action', 'fields=names' );
		if (
			( ! in_array( __( 'Deleted', 'wp_stream' ), $actions ) )
			&&
			( ! in_array( __( 'Trashed', 'wp_stream' ), $actions ) )
			) {
			$links[ __( 'Edit', 'wp_stream' ) ] = get_edit_post_link( $object_id );
		}

		if ( in_array( __( 'Updated', 'wp_stream' ), $actions ) ) {
			if ( $revision_id = get_post_meta( $stream_id, '_arg_4', true ) ) {
				$links[ __( 'Revision', 'wp_stream' ) ] = get_edit_post_link( $revision_id );
			}
		}
		return $links;
	}

}