<?php

class X_Stream_Context_Post extends X_Stream_Context {

	/**
	 * Context name
	 * @var string
	 */
	public static $name = 'posts';

	/**
	 * Actions registered for this context
	 * @var array
	 */
	public static $actions = array(
		'transition_post_status',
		'deleted_post',
	);

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Posts', 'wp_stream' );
	}

	/**
	 * Return translated action term labels
	 *
	 * @return array Action terms label translation
	 */
	public static function get_action_term_labels() {
		return array(
			'updated' => __( 'Updated', 'wp_stream' ),
			'created' => __( 'Created', 'wp_stream' ),
			'trashed' => __( 'Trashed', 'wp_stream' ),
			'deleted' => __( 'Deleted', 'wp_stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_posts
	 * @param  array $links      Previous links registered
	 * @param  int   $stream_id  Stream drop id
	 * @param  int   $object_id  Object ( post ) id
	 * @return array             Action links
	 */
	public static function action_links( $links, $stream_id, $object_id ) {
		$actions = wp_get_post_terms( $stream_id, 'stream_action', 'fields=names' );
		if (
			( ! in_array( 'deleted', $actions ) )
			&&
			( ! in_array( 'trashed', $actions ) )
			) {
			$links[ __( 'Edit', 'wp_stream' ) ] = get_edit_post_link( $object_id );
		}

		if ( in_array( 'updated', $actions ) ) {
			if ( $revision_id = get_post_meta( $stream_id, '_arg_4', true ) ) {
				$links[ __( 'Revision', 'wp_stream' ) ] = get_edit_post_link( $revision_id );
			}
		}
		return $links;
	}

	/**
	 * Log all post status changes ( creating / updating / trashing )
	 *
	 * @action transition_post_status
	 */
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
			$message = __( '"%s" post drafted', 'wp_stream' );
			$action  = 'created';
		}
		elseif ( $old == 'auto-draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = __( '"%s" post published', 'wp_stream' );
			$action  = 'created';
		}
		elseif ( $old == 'draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = __( '"%s" post published', 'wp_stream' );
		}
		elseif ( $old == 'publish' && ( in_array( $new, array( 'draft' ) ) ) ) {
			$message = __( '"%s" post unpublished', 'wp_stream' );
		}
		elseif ( $new == 'trash' ) {
			$message = __( '"%s" post trashed', 'wp_stream' );
			$action  = 'trashed';
		}
		else {
			$message = __( '"%s" post updated', 'wp_stream' );
		}

		if ( empty( $action ) ) {
			$action = 'updated';
		}

		$revision_id = null;
		if ( wp_revisions_enabled( $post ) ) {
			$revision = get_children(
				array(
					'post_type' => 'revision',
					'post_status' => 'inherit',
					'post_parent' => $post->ID,
					'posts_per_page' => 1,
					'order' => 'desc',
					'fields' => 'ids',
					)
				);
			if ( $revision ) {
				$revision_id = $revision[0];
			}
		}

		self::log(
			$message,
			array(
				'post_id'     => $post->ID,
				'post_title'  => $post->post_title,
				'new_status'  => $new,
				'old_status'  => $old,
				'revision_id' => $revision_id,
			),
			$post->ID,
			$action
		);
	}

	/**
	 * Log post deletion
	 *
	 * @action deleted_post
	 */
	public static function callback_deleted_post( $post_id ) {
		$post = get_post( $post_id );
		self::log(
			__( '"%s" post deleted from trash', 'wp_stream' ),
			array(
				'post_id'     => $post->ID,
				'post_title'  => $post->post_title,
			),
			$post->ID,
			'deleted'
		);
	}

}