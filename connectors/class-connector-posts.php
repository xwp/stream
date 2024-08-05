<?php
/**
 * Connector for Posts
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_Posts
 */
class Connector_Posts extends Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'posts';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'post_updated',
		'save_post',
		'deleted_post',
	);

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html__( 'Posts', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'updated'   => esc_html__( 'Updated', 'stream' ),
			'created'   => esc_html__( 'Created', 'stream' ),
			'trashed'   => esc_html__( 'Trashed', 'stream' ),
			'untrashed' => esc_html__( 'Restored', 'stream' ),
			'deleted'   => esc_html__( 'Deleted', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		global $wp_post_types;

		$post_types = wp_filter_object_list( $wp_post_types, array(), null, 'label' );
		$post_types = array_diff_key( $post_types, array_flip( $this->get_excluded_post_types() ) );

		add_action( 'registered_post_type', array( $this, 'registered_post_type' ), 10, 2 );

		return $post_types;
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param array  $links   Previous links registered.
	 * @param Record $record  Stream record.
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		$post = get_post( $record->object_id );

		if ( $post && $post->post_status === $record->get_meta( 'new_status', true ) ) {
			$post_type_name = $this->get_post_type_name( get_post_type( $post->ID ) );

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

				/* translators: %s: a post type singular name (e.g. "Post") */
				$links[ sprintf( esc_html_x( 'Restore %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = $untrash;
				/* translators: %s: a post type singular name (e.g. "Post") */
				$links[ sprintf( esc_html_x( 'Delete %s Permanently', 'Post type singular name', 'stream' ), $post_type_name ) ] = $delete;
			} else {
				/* translators: %s a post type singular name (e.g. "Post") */
				$links[ sprintf( esc_html_x( 'Edit %s', 'Post type singular name', 'stream' ), $post_type_name ) ] = get_edit_post_link( $post->ID );

				$view_link = get_permalink( $post->ID );
				if ( $view_link ) {
					$links[ esc_html__( 'View', 'stream' ) ] = $view_link;
				}

				$revision_id = absint( $record->get_meta( 'revision_id', true ) );
				$revision_id = $this->get_adjacent_post_revision( $revision_id, false );

				if ( $revision_id ) {
					$links[ esc_html__( 'Revision', 'stream' ) ] = get_edit_post_link( $revision_id );
				}
			}
		}

		return $links;
	}

	/**
	 * Catch registration of post_types after initial loading, to cache its labels
	 *
	 * @action registered_post_type
	 *
	 * @param string $post_type Post type slug.
	 * @param array  $args      Arguments used to register the post type.
	 */
	public function registered_post_type( $post_type, $args ) {
		unset( $args );

		$post_type_obj = get_post_type_object( $post_type );
		$label         = $post_type_obj->label;

		wp_stream_get_instance()->connectors->term_labels['stream_context'][ $post_type ] = $label;
	}

	/**
	 * Prepare log data for new posts.
	 *
	 * @action save_post
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function callback_save_post( $post_id, $post, $update ) {
		// Post update is handled in `callback_post_updated`.
		if ( $update ) {
			return;
		}

		$this->callback_transition_post_status( $post, $post );
	}

	/**
	 * Prepare log data for updated posts.
	 *
	 * @action post_updated
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post object following the update.
	 * @param \WP_Post $post_before Post object before the update.
	 *
	 * @return void
	 */
	public function callback_post_updated( $post_id, $post_after, $post_before ) {
		$this->callback_transition_post_status( $post_after, $post_before );
	}

	/**
	 * Log post changes
	 *
	 * @param \WP_Post $post_after Post object following the update.
	 * @param \WP_Post $post_before Post object before the update.
	 */
	public function callback_transition_post_status( $post_after, $post_before ) {

		if ( in_array( $post_after->post_type, $this->get_excluded_post_types(), true ) ) {
			return;
		}

		// We don't want the meta box update request, just the post update.
		if ( ! empty( wp_stream_filter_input( INPUT_GET, 'meta-box-loader' ) ) ) {
			return;
		}

		$start_statuses = array( 'auto-draft', 'inherit', 'new' );
		if ( in_array( $post_after->post_status, $start_statuses, true ) ) {
			return;
		} elseif ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		} elseif ( 'draft' === $post_after->post_status && 'publish' === $post_before->post_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s unpublished',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'trash' === $post_before->post_status && 'trash' !== $post_after->post_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s restored from trash',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'untrashed';
		} elseif ( 'draft' === $post_after->post_status && 'draft' === $post_before->post_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s draft saved',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'publish' === $post_after->post_status && ! in_array( $post_before->post_status, array( 'future', 'publish' ), true ) ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s published',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'draft' === $post_after->post_status && 'draft' !== $post_before->post_status ) {
			/* translators: %1$s: a post title, %2$s a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s drafted',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'pending' === $post_after->post_status && 'pending' !== $post_before->post_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s pending review',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'future' === $post_after->post_status && 'future' !== $post_before->post_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s scheduled for %3$s',
				'1: Post title, 2: Post type singular name, 3: Scheduled post date',
				'stream'
			);
		} elseif ( 'future' === $post_before->post_status && 'publish' === $post_after->post_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" scheduled %2$s published',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'private' === $post_after->post_status && 'private' !== $post_before->post_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s privately published',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		} elseif ( 'trash' === $post_after->post_status && 'trash' !== $post_before->post_status ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s trashed',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
			$action  = 'trashed';
		} elseif ( $post_after->post_author !== $post_before->post_author ) {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post"), %8$s: new author name */
			$summary = _x(
				'"%1$s" %2$s author changed to "%8$s"',
				'1: Post title, 2: Post type singular name, 8: New author name',
				'stream'
			);
		} else {
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
			$summary = _x(
				'"%1$s" %2$s updated',
				'1: Post title, 2: Post type singular name',
				'stream'
			);
		}

		if ( in_array( $post_before->post_status, $start_statuses, true ) && ! in_array( $post_after->post_status, $start_statuses, true ) ) {
			$action = 'created';
		}

		if ( empty( $action ) ) {
			$action = 'updated';
		}

		$revision_id = null;

		if ( wp_revisions_enabled( $post_after ) ) {
			$revision = get_children(
				array(
					'post_type'      => 'revision',
					'post_status'    => 'inherit',
					'post_parent'    => $post_after->ID,
					'posts_per_page' => 1, // VIP safe.
					'orderby'        => 'post_date',
					'order'          => 'DESC',
				)
			);

			if ( $revision ) {
				$revision    = array_values( $revision );
				$revision_id = $revision[0]->ID;
			}
		}

		$post_type_name = strtolower( $this->get_post_type_name( $post_after->post_type ) );

		$this->log(
			$summary,
			array(
				'post_title'    => $post_after->post_title,
				'singular_name' => $post_type_name,
				'post_date'     => $post_after->post_date,
				'post_date_gmt' => $post_after->post_date_gmt,
				'new_status'    => $post_after->post_status,
				'old_status'    => $post_before->post_status,
				'revision_id'   => $revision_id,
				'post_author'   => get_the_author_meta( 'display_name', $post_after->post_author )
			),
			$post_after->ID,
			$post_after->post_type,
			$action
		);
	}

	/**
	 * Log post deletion
	 *
	 * @action deleted_post
	 *
	 * @param integer $post_id  Post ID.
	 */
	public function callback_deleted_post( $post_id ) {
		$post = get_post( $post_id );

		// We check if post is an instance of WP_Post as it doesn't always resolve in unit testing.
		if ( ! ( $post instanceof \WP_Post ) || in_array( $post->post_type, $this->get_excluded_post_types(), true ) ) {
			return;
		}

		// Ignore auto-drafts that are deleted by the system, see issue-293.
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$post_type_name = strtolower( $this->get_post_type_name( $post->post_type ) );

		$this->log(
			/* translators: %1$s: a post title, %2$s: a post type singular name (e.g. "Hello World", "Post") */
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
			$post->post_type,
			'deleted'
		);
	}

	/**
	 * Constructs list of excluded post types for the Posts connector
	 *
	 * @return array List of excluded post types
	 */
	public function get_excluded_post_types() {
		return apply_filters(
			'wp_stream_posts_exclude_post_types',
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
	 * @param string $post_type_slug  Post type slug.
	 *
	 * @return string Post type label
	 */
	public function get_post_type_name( $post_type_slug ) {
		$name = esc_html__( 'Post', 'stream' ); // Default.

		if ( post_type_exists( $post_type_slug ) ) {
			$post_type = get_post_type_object( $post_type_slug );
			$name      = $post_type->labels->singular_name;
		}

		return $name;
	}

	/**
	 * Get an adjacent post revision ID
	 *
	 * @param int  $revision_id  Revision ID.
	 * @param bool $previous     Has there been any revision before this one?.
	 *
	 * @return int $revision_id
	 */
	public function get_adjacent_post_revision( $revision_id, $previous = true ) {
		if ( empty( $revision_id ) || ! wp_is_post_revision( $revision_id ) ) {
			return false;
		}

		$revision = wp_get_post_revision( $revision_id );
		$operator = ( $previous ) ? '<' : '>';
		$order    = ( $previous ) ? 'DESC' : 'ASC';

		global $wpdb;
		// @codingStandardsIgnoreStart
		$revision_id = $wpdb->get_var( // db call okay
			$wpdb->prepare(
				"SELECT p.ID
				FROM $wpdb->posts AS p
				WHERE p.post_date {$operator} %s
					AND p.post_type = 'revision'
					AND p.post_parent = %d
				ORDER BY p.post_date {$order}
				LIMIT 1",
				$revision->post_date,
				$revision->post_parent
			)
		);
		// @codingStandardsIgnoreEnd
		// prepare okay

		$revision_id = absint( $revision_id );

		if ( ! wp_is_post_revision( $revision_id ) ) {
			return false;
		}

		return $revision_id;
	}
}
