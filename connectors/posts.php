<?php

class WP_Stream_Connector_Posts extends WP_Stream_Connector {

	/**
	 * Context name
	 *
	 * @var string
	 */
	public static $name = 'posts';

	/**
	 * Actions registered for this context
	 *
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
				$post_type_name = self::get_post_type_name( get_post_type( $record->object_id ) );
				$links[ sprintf( _x( 'Edit %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = $link;
			}
			if ( post_type_exists( get_post_type( $record->object_id ) ) && $link = get_permalink( $record->object_id ) ) {
				$links[ __( 'View', 'stream' ) ] = $link;
			}
			if ( 'updated' == $record->action ) {
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
		} elseif ( $old == 'auto-draft' && $new == 'draft' ) {
			$message = _x(
				'"%1$s" %2$s drafted',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'created';
		} elseif ( $old == 'auto-draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = _x(
				'"%1$s" %2$s published',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'created';
		} elseif ( $old == 'draft' && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$message = _x(
				'"%1$s" %2$s published',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( $old == 'publish' && ( in_array( $new, array( 'draft' ) ) ) ) {
			$message = _x(
				'"%1$s" %2$s unpublished',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( $new == 'trash' ) {
			$message = _x(
				'"%1$s" %2$s trashed',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'trashed';
		} else {
			$message = _x(
				'"%1$s" %2$s updated',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
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

		$post_type_name = strtolower( self::get_post_type_name( $post->post_type ) );

		self::log(
			$message,
			array(
				'post_title'    => $post->post_title,
				'singular_name' => $post_type_name,
				'new_status'    => $new,
				'old_status'    => $old,
				'revision_id'   => $revision_id,
			),
			$post->ID,
			array( $post->post_type => $action )
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

		// Ignore auto-drafts that are deleted by the system, see issue-293
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$post_type_name = strtolower( self::get_post_type_name( $post->post_type ) );

		self::log(
			_x(
				'"%1$s" %2$s deleted from trash',
				'1: Post title, 2: Post type singular name',
				'stream'
			),
			array(
				'post_title'    => $post->post_title,
				'singular_name' => $post_type_name,
			),
			$post->ID,
			array( $post->post_type => 'deleted' )
		);
	}

	/**
	 * Constructs list of ignored post types for the post connector
	 *
	 * @return  array  List of ignored post types
	 */
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

	/**
	 * Gets the singular post type label
	 *
	 * @param   string  $post_type_slug
	 * @return  string  Post type label
	 */
	private static function get_post_type_name( $post_type_slug ) {
		$name = __( 'Post', 'stream' ); // Default

		if ( post_type_exists( $post_type_slug ) ) {
			$post_type = get_post_type_object( $post_type_slug );
			$name      = $post_type->labels->singular_name;
		}

		return $name;
	}

}
