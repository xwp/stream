<?php

class WP_Stream_Connector_Posts extends WP_Stream_Connector {

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
		return __( 'Posts', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'updated' => __( 'Updated', 'stream' ),
			'created' => __( 'Created', 'stream' ),
			'trashed' => __( 'Trashed', 'stream' ),
			'deleted' => __( 'Deleted', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		global $wp_post_types;
		$post_types = wp_filter_object_list( $wp_post_types, array(), null, 'label' );
		$post_types['attachment'] = __( 'Attachments' );
		return $post_types;
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
			$links[ __( 'Edit', 'stream' ) ] = get_edit_post_link( $object_id );
		}

		if ( in_array( 'updated', $actions ) ) {
			if ( $revision_id = get_post_meta( $stream_id, '_arg_4', true ) ) {
				$links[ __( 'Revision', 'stream' ) ] = get_edit_post_link( $revision_id );
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
		if ( in_array( $post->post_type, self::get_ignored_post_types() ) ) {
			return;
		}

		if ( in_array( $new, array( 'auto-draft', 'inherit' ) ) ) {
			return;
		}
		elseif ( $old == 'auto-draft' && $new == 'draft' ) {
			$message = __( '"%s" post drafted', 'stream' );
			$action  = 'created';
		}
		elseif ( $old == 'auto-draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = __( '"%s" post published', 'stream' );
			$action  = 'created';
		}
		elseif ( $old == 'draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = __( '"%s" post published', 'stream' );
		}
		elseif ( $old == 'publish' && ( in_array( $new, array( 'draft' ) ) ) ) {
			$message = __( '"%s" post unpublished', 'stream' );
		}
		elseif ( $new == 'trash' ) {
			$message = __( '"%s" post trashed', 'stream' );
			$action  = 'trashed';
		}
		else {
			$message = __( '"%s" post updated', 'stream' );
		}

		if ( empty( $action ) ) {
			$action = 'updated';
		}

		$revision_id = null;
		if ( wp_revisions_enabled( $post ) ) {
			$revision = get_children(
				array(
					'post_type'      => 'revision',
					'post_status'    => 'inherit',
					'post_parent'    => $post->ID,
					'posts_per_page' => 1,
					'order'          => 'desc',
					'fields'         => 'ids',
					)
				);
			if ( $revision ) {
				$revision_id = $revision[0];
			}
		}

		self::log(
			$message,
			array(
				'post_title'  => $post->post_title,
				'new_status'  => $new,
				'old_status'  => $old,
				'revision_id' => $revision_id,
			),
			$post->ID,
			array(
				$post->post_type => $action,
				)
		);
	}

	/**
	 * Log post deletion
	 *
	 * @action deleted_post
	 */
	public static function callback_deleted_post( $post_id ) {
		$post = get_post( $post_id );
		if ( in_array( $post->post_type, self::get_ignored_post_types() ) ) {
			return;
		}
		self::log(
			__( '"%s" post deleted from trash', 'stream' ),
			array(
				'post_title' => $post->post_title,
			),
			$post->ID,
			array(
				$post->post_type => 'deleted',
				)
		);
	}

	public static function get_ignored_post_types() {
		return apply_filters(
			'wp_stream_post_exclude_post_types',
			array(
				'nav_menu_item',
				'attachment',
				)
			);
	} 

}