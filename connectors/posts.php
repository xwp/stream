<?php

class WP_Stream_Connector_Posts extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'posts';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'transition_post_status',
		'save_post',
		'deleted_post',
	);

	/**
	 * Holds the post transition before passing to post save
	 *
	 * @var array
	 */
	protected static $transition_post_status = array();

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return __( 'Posts', 'default' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'updated'   => __( 'Updated', 'stream' ),
			'created'   => __( 'Created', 'stream' ),
			'trashed'   => __( 'Trashed', 'stream' ),
			'untrashed' => __( 'Restored', 'stream' ),
			'deleted'   => __( 'Deleted', 'stream' ),
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
		$post = get_post( $record->object_id );

		if ( $post && $post->post_status === wp_stream_get_meta( $record->ID, 'new_status', true ) ) {
			$post_type_name = self::get_post_type_name( get_post_type( $post->ID ) );

			if ( 'trash' === $post->post_status ) {
				$untrash = wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'untrash',
							'post'   => $post->ID,
						),
						admin_url( 'post.php' )
					),
					sprintf( 'untrash-post_%d', $post->ID )
				);

				$delete = wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'delete',
							'post'   => $post->ID,
						),
						admin_url( 'post.php' )
					),
					sprintf( 'delete-post_%d', $post->ID )
				);

				$links[ sprintf( esc_html_x( 'Restore %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = $untrash;
				$links[ sprintf( esc_html_x( 'Delete %s Permenantly', 'Post type singular name', 'stream' ), $post_type_name ) ] = $delete;
			} else {
				$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = get_edit_post_link( $post->ID );

				if ( $view_link = get_permalink( $post->ID ) ) {
					$links[ esc_html__( 'View', 'default' ) ] = $view_link;
				}

				if ( $revision_id = wp_stream_get_meta( $record->ID, 'revision_id', true ) ) {
					$links[ esc_html__( 'Revision', 'default' ) ] = get_edit_post_link( $revision_id );
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
		} elseif ( 'auto-draft' === $old && 'draft' === $new ) {
			$summary = _x(
				'"%1$s" %2$s drafted',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'created';
		} elseif ( 'auto-draft' === $old && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$summary = _x(
				'"%1$s" %2$s published',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'created';
		} elseif ( 'draft' === $old && ( in_array( $new, array( 'publish', 'private' ) ) ) ) {
			$summary = _x(
				'"%1$s" %2$s published',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'publish' === $old && ( in_array( $new, array( 'draft' ) ) ) ) {
			$summary = _x(
				'"%1$s" %2$s unpublished',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'trash' === $new ) {
			$summary = _x(
				'"%1$s" %2$s trashed',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'trashed';
		} elseif ( 'trash' === $old && 'trash' !== $new ) {
			$summary = _x(
				'"%1$s" %2$s restored from trash',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'untrashed';
		} else {
			$summary = _x(
				'"%1$s" %2$s updated',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		}

		if ( empty( $action ) ) {
			$action = 'updated';
		}

		// Hold the post transition data for use in callback_save_post
		self::$transition_post_status = array(
			'new_status' => $new,
			'old_status' => $old,
			'summary'    => $summary,
			'action'     => $action,
		);
	}

	/**
	 * Log all post saves
	 *
	 * We don't log a record until after the post is saved in order to
	 * properly capture the post revision ID (if one exists).
	 *
	 * New post status, old post status, summary and action pulled from
	 * the $transition_post_status property which is defined in during
	 * the transition_post_status action which happens before save_post.
	 *
	 * @action save_post
	 */
	public static function callback_save_post( $post_ID, $post, $update ) {
		if ( in_array( $post->post_type, self::get_ignored_post_types() ) ) {
			return;
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
			self::$transition_post_status['summary'],
			array(
				'post_title'    => $post->post_title,
				'singular_name' => $post_type_name,
				'new_status'    => self::$transition_post_status['new_status'],
				'old_status'    => self::$transition_post_status['old_status'],
				'revision_id'   => $revision_id,
			),
			$post->ID,
			array( $post->post_type => self::$transition_post_status['action'] )
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
		$name = __( 'Post', 'default' ); // Default

		if ( post_type_exists( $post_type_slug ) ) {
			$post_type = get_post_type_object( $post_type_slug );
			$name      = $post_type->labels->singular_name;
		}

		return $name;
	}

}
