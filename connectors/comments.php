<?php

class WP_Stream_Connector_Comments extends WP_Stream_Connector {

	/**
	 * Context name
	 * @var string
	 */
	public static $name = 'comments';

	/**
	 * Actions registered for this context
	 * @var array
	 */
	public static $actions = array(
		'comment_flood_trigger',
		'wp_insert_comment',
		'edit_comment',
		'delete_comment',
		'trash_comment',
		'untrash_comment',
		'spam_comment',
		'unspam_comment',
		'transition_comment_status',
		'comment_duplicate_trigger',
	);

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
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
			'throttled'  => __( 'Throttled', 'stream' ),
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
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 * @param  array $links      Previous links registered
	 * @param  int   $record     Stream record
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {

		if ( $record->object_id ) {
			if ( $comment = get_comment( $record->object_id ) ) {
				$del_nonce = wp_create_nonce( "delete-comment_$comment->comment_ID" );
				$approve_nonce = wp_create_nonce( "approve-comment_$comment->comment_ID" );
				$links[ __( 'Edit', 'stream' ) ] = admin_url( "comment.php?action=editcomment&c=$comment->comment_ID" );
				if ( 1 == $comment->comment_approved ) {
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
			array( 'comments' => 'flood' )
		);
	}

	/**
	 * Tracks comment creation
	 *
	 * @action wp_insert_comment
	 */
	public static function callback_wp_insert_comment( $comment_id, $comment ) {
		$user_id        = self::get_comment_author( $comment, 'id' );
		$user_name      = self::get_comment_author( $comment, 'name' );
		$post_id        = $comment->comment_post_ID;
		$post_title     = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );
		$comment_status = ( 1 == $comment->comment_approved ) ? __( 'approved automatically', 'stream' ) : __( 'pending approval', 'stream' );

		if ( $comment->comment_parent ) {
			$parent_user_id   = get_comment_author( $comment->comment_parent, 'id' );
			$parent_user_name = get_comment_author( $comment->comment_parent, 'name' );

			self::log(
				__( 'Reply to %s\'s comment by %s on %s %s' , 'stream' ),
				compact( 'parent_user_name', 'user_name', 'post_title', 'comment_status', 'post_id', 'parent_user_id' ),
				$comment_id,
				array( 'comments' => 'replied' ),
				$user_id
			);
		} else {
			self::log(
				__( 'New comment by %s on %s %s' , 'stream' ),
				compact( 'user_name', 'post_title', 'comment_status', 'post_id' ),
				$comment_id,
				array( 'comments' => 'created' ),
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
		$comment    = get_comment( $comment_id );
		$user_id    = self::get_comment_author( $comment, 'id' );
		$user_name  = self::get_comment_author( $comment, 'name' );
		$post_id    = $comment->comment_post_ID;
		$post_title = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );

		self::log(
			__( '%s\'s comment on %s edited', 'stream' ),
			compact( 'user_name', 'post_title', 'post_id', 'user_id' ),
			$comment_id,
			array( 'comments' => 'edited' )
		);
	}

	/**
	 * Tracks comment delete
	 *
	 * @action delete_comment
	 */
	public static function callback_delete_comment( $comment_id ) {
		$comment    = get_comment( $comment_id );
		$user_id    = self::get_comment_author( $comment, 'id' );
		$user_name  = self::get_comment_author( $comment, 'name' );
		$post_id    = $comment->comment_post_ID;
		$post_title = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );

		self::log(
			__( '%s\'s comment on %s deleted permanently', 'stream' ),
			compact( 'user_name', 'post_title', 'post_id', 'user_id' ),
			$comment_id,
			array( 'comments' => 'deleted' )
		);
	}

	/**
	 * Tracks comment trashing
	 *
	 * @action trash_comment
	 */
	public static function callback_trash_comment( $comment_id ) {
		$comment    = get_comment( $comment_id );
		$user_id    = self::get_comment_author( $comment, 'id' );
		$user_name  = self::get_comment_author( $comment, 'name' );
		$post_id    = $comment->comment_post_ID;
		$post_title = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );

		self::log(
			__( '%s\'s comment on %s trashed', 'stream' ),
			compact( 'user_name', 'post_title', 'post_id', 'user_id' ),
			$comment_id,
			array( 'comments' => 'trashed' )
		);
	}

	/**
	 * Tracks comment trashing
	 *
	 * @action untrash_comment
	 */
	public static function callback_untrash_comment( $comment_id ) {
		$comment    = get_comment( $comment_id );
		$user_id    = self::get_comment_author( $comment, 'id' );
		$user_name  = self::get_comment_author( $comment, 'name' );
		$post_id    = $comment->comment_post_ID;
		$post_title = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );

		self::log(
			__( '%s\'s comment on %s restored', 'stream' ),
			compact( 'user_name', 'post_title', 'post_id', 'user_id' ),
			$comment_id,
			array( 'comments' => 'untrashed' )
		);
	}

	/**
	 * Tracks comment marking as spam
	 *
	 * @action spam_comment
	 */
	public static function callback_spam_comment( $comment_id ) {
		$comment    = get_comment( $comment_id );
		$user_id    = self::get_comment_author( $comment, 'id' );
		$user_name  = self::get_comment_author( $comment, 'name' );
		$post_id    = $comment->comment_post_ID;
		$post_title = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );

		self::log(
			__( '%s\'s comment on %s marked as spam', 'stream' ),
			compact( 'user_name', 'post_title', 'post_id', 'user_id' ),
			$comment_id,
			array( 'comments' => 'spammed' )
		);
	}

	/**
	 * Tracks comment unmarking as spam
	 *
	 * @action unspam_comment
	 */
	public static function callback_unspam_comment( $comment_id ) {
		$comment    = get_comment( $comment_id );
		$user_id    = self::get_comment_author( $comment, 'id' );
		$user_name  = self::get_comment_author( $comment, 'name' );
		$post_id    = $comment->comment_post_ID;
		$post_title = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );

		self::log(
			__( '%s\'s comment on %s unmarked as spam', 'stream' ),
			compact( 'user_name', 'post_title', 'post_id', 'user_id' ),
			$comment_id,
			array( 'comments' => 'unspammed' )
		);
	}

	/**
	* Track comment status transition
	*
	* @action transition_comment_status
	*/
	public static function callback_transition_comment_status( $new_status, $old_status, $comment ) {
		if ( 'approved' !== $new_status && 'unapproved' !== $new_status || 'trash' === $old_status || 'spam' === $old_status ) {
			return;
		}

		$user_id    = self::get_comment_author( $comment, 'id' );
		$user_name  = self::get_comment_author( $comment, 'name' );
		$post_id    = $comment->comment_post_ID;
		$post_title = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );

		self::log(
			__( '%s\'s comment %s', 'stream' ),
			compact( 'user_name', 'new_status', 'old_status', 'post_title', 'post_id', 'user_id' ),
			$comment->comment_id,
			array( 'comments' => $new_status )
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
		$user_id    = self::get_comment_author( $comment, 'id' );
		$user_name  = self::get_comment_author( $comment, 'name' );
		$post_id    = $comment->comment_post_ID;
		$post_title = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'a post', 'stream' );

		self::log(
			__( 'Duplicate comment by %s prevented on %s', 'stream' ),
			compact( 'user_name', 'post_title', 'post_id', 'user_id' ),
			$comment_id,
			array( 'comments' => 'duplicate' )
		);
	}

}
