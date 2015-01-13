<?php

class WP_Stream_Connector_Comments extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'comments';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'comment_flood_trigger',
		'wp_insert_comment',
		'edit_comment',
		'before_delete_post',
		'deleted_post',
		'delete_comment',
		'trash_comment',
		'untrash_comment',
		'spam_comment',
		'unspam_comment',
		'transition_comment_status',
		'comment_duplicate_trigger',
	);

	/**
	 * Catch and store the post ID during post deletion
	 *
	 * @var int
	 */
	protected static $delete_post = 0;

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return __( 'Comments', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created'    => __( 'Created', 'stream' ),
			'edited'     => __( 'Edited', 'stream' ),
			'replied'    => __( 'Replied', 'stream' ),
			'approved'   => __( 'Approved', 'stream' ),
			'unapproved' => __( 'Unapproved', 'stream' ),
			'trashed'    => __( 'Trashed', 'stream' ),
			'untrashed'  => __( 'Restored', 'stream' ),
			'spammed'    => __( 'Marked as Spam', 'stream' ),
			'unspammed'  => __( 'Unmarked as Spam', 'stream' ),
			'deleted'    => __( 'Deleted', 'stream' ),
			'duplicate'  => __( 'Duplicate', 'stream' ),
			'flood'      => __( 'Throttled', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'comments' => __( 'Comments', 'stream' ),
		);
	}

	/**
	 * Return translated comment type labels
	 *
	 * @return array Comment type label translations
	 */
	public static function get_comment_type_labels() {
		return apply_filters(
			'wp_stream_comments_comment_type_labels',
			array(
				'comment'   => __( 'Comment', 'stream' ),
				'trackback' => __( 'Trackback', 'stream' ),
				'pingback'  => __( 'Pingback', 'stream' ),
			)
		);
	}

	/**
	 * Return the comment type label for a given comment ID
	 *
	 * @param  int    $comment_id  ID of the comment
	 * @return string              The comment type label
	 */
	public static function get_comment_type_label( $comment_id ) {
		$comment_type = get_comment_type( $comment_id );

		if ( empty( $comment_type ) ) {
			$comment_type = 'comment';
		}

		$comment_type_labels = self::get_comment_type_labels();

		$label = isset( $comment_type_labels[ $comment_type ] ) ? $comment_type_labels[ $comment_type ] : $comment_type;

		return $label;
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array  $links     Previous links registered
	 * @param  object $record    Stream record
	 *
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		if ( $record->object_id ) {
			if ( $comment = get_comment( $record->object_id ) ) {
				$del_nonce     = wp_create_nonce( "delete-comment_$comment->comment_ID" );
				$approve_nonce = wp_create_nonce( "approve-comment_$comment->comment_ID" );

				$links[ __( 'Edit', 'stream' ) ] = admin_url( "comment.php?action=editcomment&c=$comment->comment_ID" );

				if ( 1 === $comment->comment_approved ) {
					$links[ __( 'Unapprove', 'stream' ) ] = admin_url(
						sprintf(
							'comment.php?action=unapprovecomment&c=%s&_wpnonce=%s',
							$record->object_id,
							$approve_nonce
						)
					);
				} elseif ( empty( $comment->comment_approved ) ) {
					$links[ __( 'Approve', 'stream' ) ] = admin_url(
						sprintf(
							'comment.php?action=approvecomment&c=%s&_wpnonce=%s',
							$record->object_id,
							$approve_nonce
						)
					);
				}
			}
		}

		return $links;
	}

	/**
	 * Fetches the comment author and returns the specified field.
	 *
	 * This also takes into consideration whether or not the blog requires only
	 * name and e-mail or that users be logged in to comment. In either case it
	 * will try to see if the e-mail provided does belong to a registered user.
	 *
	 * @param  object|int  $comment  A comment object or comment ID
	 * @param  string      $field    What field you want to return
	 * @return int|string  $output   User ID or user display name
	 */
	public static function get_comment_author( $comment, $field = 'id' ) {
		$comment = is_object( $comment ) ? $comment : get_comment( absint( $comment ) );

		$req_name_email = get_option( 'require_name_email' );
		$req_user_login = get_option( 'comment_registration' );

		$user_id   = 0;
		$user_name = __( 'Guest', 'stream' );

		if ( $req_name_email && isset( $comment->comment_author_email ) && isset( $comment->comment_author ) ) {
			$user      = get_user_by( 'email', $comment->comment_author_email );
			$user_id   = isset( $user->ID ) ? $user->ID : 0;
			$user_name = isset( $user->display_name ) ? $user->display_name : $comment->comment_author;
		}

		if ( $req_user_login ) {
			$user      = wp_get_current_user();
			$user_id   = $user->ID;
			$user_name = $user->display_name;
		}

		if ( 'id' === $field ) {
			$output = $user_id;
		} elseif ( 'name' === $field ) {
			$output = $user_name;
		}

		return $output;
	}

	/**
	 * Tracks comment flood blocks
	 *
	 * @action comment_flood_trigger
	 */
	public static function callback_comment_flood_trigger( $time_lastcomment, $time_newcomment ) {
		$flood_tracking = isset( WP_Stream_Settings::$options['advanced_comment_flood_tracking'] ) ? WP_Stream_Settings::$options['advanced_comment_flood_tracking'] : false;

		if ( ! $flood_tracking ) {
			return;
		}

		$req_user_login = get_option( 'comment_registration' );

		if ( $req_user_login ) {
			$user      = wp_get_current_user();
			$user_id   = $user->ID;
			$user_name = $user->display_name;
		} else {
			$user_name = __( 'a logged out user', 'stream' );
		}

		self::log(
			__( 'Comment flooding by %s detected and prevented', 'stream' ),
			compact( 'user_name', 'user_id', 'time_lastcomment', 'time_newcomment' ),
			null,
			'comments',
			'flood'
		);
	}

	/**
	 * Tracks comment creation
	 *
	 * @action wp_insert_comment
	 */
	public static function callback_wp_insert_comment( $comment_id, $comment ) {
		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id        = self::get_comment_author( $comment, 'id' );
		$user_name      = self::get_comment_author( $comment, 'name' );
		$post_id        = $comment->comment_post_ID;
		$post_type      = get_post_type( $post_id );
		$post_title     = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );
		$comment_status = ( 1 == $comment->comment_approved ) ? __( 'approved automatically', 'stream' ) : __( 'pending approval', 'stream' );
		$is_spam        = false;

		// Auto-marked spam comments
		$ak_tracking = isset( WP_Stream_Settings::$options['advanced_akismet_tracking'] ) ? WP_Stream_Settings::$options['advanced_akismet_tracking'] : false;
		if ( class_exists( 'Akismet' ) && $ak_tracking && Akismet::matches_last_comment( $comment ) ) {
			$ak_last_comment = Akismet::get_last_comment();
			if ( 'true' == $ak_last_comment['akismet_result'] ) {
				$is_spam        = true;
				$comment_status = __( 'automatically marked as spam by Akismet', 'stream' );
			}
		}
		$comment_type   = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		if ( $comment->comment_parent ) {
			$parent_user_id   = get_comment_author( $comment->comment_parent, 'id' );
			$parent_user_name = get_comment_author( $comment->comment_parent, 'name' );

			self::log(
				_x(
					'Reply to %1$s\'s %5$s by %2$s on %3$s %4$s',
					"1: Parent comment's author, 2: Comment author, 3: Post title, 4: Comment status, 5: Comment type",
					'stream'
				),
				compact( 'parent_user_name', 'user_name', 'post_title', 'comment_status', 'comment_type', 'post_id', 'parent_user_id' ),
				$comment_id,
				$post_type,
				'replied',
				$user_id
			);
		} else {
			self::log(
				_x(
					'New %4$s by %1$s on %2$s %3$s',
					'1: Comment author, 2: Post title 3: Comment status, 4: Comment type',
					'stream'
				),
				compact( 'user_name', 'post_title', 'comment_status', 'comment_type', 'post_id', 'is_spam' ),
				$comment_id,
				$post_type,
				$is_spam ? 'spammed' : 'created',
				$user_id
			);
		}
	}

	/**
	 * Tracks comment updates
	 *
	 * @action edit_comment
	 */
	public static function callback_edit_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s edited',
				'1: Comment author, 2: Post title, 3: Comment type',
				'stream'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			$post_type,
			'edited'
		);
	}

	/**
	 * Catch the post ID during deletion
	 *
	 * @action before_delete_post
	 */
	public static function callback_before_delete_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::$delete_post = $post_id;
	}

	/**
	 * Reset the post ID after deletion
	 *
	 * @action deleted_post
	 */
	public static function callback_deleted_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::$delete_post = 0;
	}

	/**
	 * Tracks comment delete
	 *
	 * @action delete_comment
	 */
	public static function callback_delete_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = absint( $comment->comment_post_ID );
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		if ( self::$delete_post === $post_id ) {
			return;
		}

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s deleted permanently',
				'1: Comment author, 2: Post title, 3: Comment type',
				'stream'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			$post_type,
			'deleted'
		);
	}

	/**
	 * Tracks comment trashing
	 *
	 * @action trash_comment
	 */
	public static function callback_trash_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s trashed',
				'1: Comment author, 2: Post title, 3: Comment type',
				'stream'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			$post_type,
			'trashed'
		);
	}

	/**
	 * Tracks comment trashing
	 *
	 * @action untrash_comment
	 */
	public static function callback_untrash_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s restored',
				'1: Comment author, 2: Post title, 3: Comment type',
				'stream'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			$post_type,
			'untrashed'
		);
	}

	/**
	 * Tracks comment marking as spam
	 *
	 * @action spam_comment
	 */
	public static function callback_spam_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s marked as spam',
				'1: Comment author, 2: Post title, 3: Comment type',
				'stream'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			$post_type,
			'spammed'
		);
	}

	/**
	 * Tracks comment unmarking as spam
	 *
	 * @action unspam_comment
	 */
	public static function callback_unspam_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'%1$s\'s %3$s on %2$s unmarked as spam',
				'1: Comment author, 2: Post title, 3: Comment type',
				'stream'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			$post_type,
			'unspammed'
		);
	}

	/**
	* Track comment status transition
	*
	* @action transition_comment_status
	*/
	public static function callback_transition_comment_status( $new_status, $old_status, $comment ) {
		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		if ( 'approved' !== $new_status && 'unapproved' !== $new_status || 'trash' === $old_status || 'spam' === $old_status ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );
		$comment_type = get_comment_type( $comment->comment_ID );

		self::log(
			_x(
				'%1$s\'s %3$s %2$s',
				'Comment status transition. 1: Comment author, 2: Post title, 3: Comment type',
				'stream'
			),
			compact( 'user_name', 'new_status', 'comment_type', 'old_status', 'post_title', 'post_id', 'user_id' ),
			$comment->comment_ID,
			$post_type,
			$new_status
		);
	}

	/**
	 * Track attempts to add duplicate comments
	 *
	 * @action comment_duplicate_trigger
	 */
	public static function callback_comment_duplicate_trigger( $comment_data ) {
		global $wpdb;

		$comment_id = $wpdb->last_result[0]->comment_ID;
		$comment    = get_comment( $comment_id );

		if ( in_array( $comment->comment_type, self::get_ignored_comment_types() ) ) {
			return;
		}

		$user_id      = self::get_comment_author( $comment, 'id' );
		$user_name    = self::get_comment_author( $comment, 'name' );
		$post_id      = $comment->comment_post_ID;
		$post_type    = get_post_type( $post_id );
		$post_title   = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );
		$comment_type = mb_strtolower( self::get_comment_type_label( $comment_id ) );

		self::log(
			_x(
				'Duplicate %3$s by %1$s prevented on %2$s',
				'1: Comment author, 2: Post title, 3: Comment type',
				'stream'
			),
			compact( 'user_name', 'post_title', 'comment_type', 'post_id', 'user_id' ),
			$comment_id,
			$post_type,
			'duplicate'
		);
	}

	/**
	 * Constructs list of ignored comment types for the comments connector
	 *
	 * @return  array  List of ignored comment types
	 */
	public static function get_ignored_comment_types() {
		return apply_filters(
			'wp_stream_comments_exclude_comment_types',
			array()
		);
	}

}
