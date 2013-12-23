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
		$post_types = array_diff_key( $post_types, array_flip( self::get_ignored_post_types() ) );
		return $post_types;
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 * @param  array $links      Previous links registered
	 * @param  int   $record     Stream record
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		if ( get_post( $record->object_id ) ) {
			if ( $link = get_edit_post_link( $record->object_id ) ) {
				$post_type = get_post_type_object( get_post_type( $record->object_id ) );
				$links[ sprintf( __( 'Edit %s', 'stream' ), $post_type->labels->singular_name ) ] = $link;
			}
			if ( post_type_exists( get_post_type( $record->object_id ) ) && $link = get_permalink( $record->object_id ) ) {
				$links[ __( 'View', 'stream' ) ] = $link;
			}
			if ( $record->action == 'updated' ) {
				if ( $revision_id = get_stream_meta( $record->ID, 'revision_id', true ) ) {
					$links[ __( 'Revision', 'stream' ) ] = get_edit_post_link( $revision_id );
				}
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
			$message = __( '"%s" %s drafted', 'stream' );
			$action  = 'created';
		}
		elseif ( $old == 'auto-draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = __( '"%s" %s published', 'stream' );
			$action  = 'created';
		}
		elseif ( $old == 'draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = __( '"%s" %s published', 'stream' );
		}
		elseif ( $old == 'publish' && ( in_array( $new, array( 'draft' ) ) ) ) {
			$message = __( '"%s" %s unpublished', 'stream' );
		}
		elseif ( $new == 'trash' ) {
			$message = __( '"%s" %s trashed', 'stream' );
			$action  = 'trashed';
		}
		else {
			$message = __( '"%s" %s updated', 'stream' );
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

		$post_type = get_post_type_object( $post->post_type );

		self::log(
			$message,
			array(
				'post_title'    => $post->post_title,
				'singular_name' => strtolower( $post_type->labels->singular_name ),
				'new_status'    => $new,
				'old_status'    => $old,
				'revision_id'   => $revision_id,
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

		// We check if post is an instance of WP_Post as it doesn't always resolve in unit testing
		if ( ! ( $post instanceof WP_Post ) || in_array( $post->post_type, self::get_ignored_post_types() )  ) {
			return;
		}

		$post_type = get_post_type_object( $post->post_type );

		self::log(
			__( '"%s" %s deleted from trash', 'stream' ),
			array(
				'post_title'    => $post->post_title,
				'singular_name' => strtolower( $post_type->labels->singular_name ),
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
				'revision',
			)
		);
	}

}
