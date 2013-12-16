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
			'updated'    => __( 'Updated', 'stream' ),
			'replied'    => __( 'Replied', 'stream' ),
			'approved'   => __( 'Approved', 'stream' ),
			'unapproved' => __( 'Unapproved', 'stream' ),
			'trashed'    => __( 'Trashed', 'stream' ),
			'untrashed'  => __( 'Trashed', 'stream' ),
			'spammed'    => __( 'Spammed', 'stream' ),
			'unspammed'  => __( 'Spammed', 'stream' ),
			'deleted'    => __( 'Deleted', 'stream' ),
			'duplicate'  => __( 'Deleted', 'stream' ),
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
				if ( $comment->comment_approved == 1 ) {
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
	 * Tracks comment flood blocks
	 *
	 * @action comment_flood_trigger
	 */
	public static function callback_comment_flood_trigger( $time_lastcomment, $time_newcomment ) {
		$user = wp_get_current_user();
		$user_name = $user ? $user->display_name : __( 'Guest', 'stream' );
		self::log(
			__( '%s was prevented from posting a new comment', 'stream' ),
			compact( 'user_name', 'time_lastcomment', 'time_newcomment' ),
			null,
			array( 'comments' => 'created' )
		);
	}

	/**
	 * Tracks comment creation
	 *
	 * @action wp_insert_comment
	 */
	public static function callback_wp_insert_comment( $comment_id, $comment ) {
		$user      = wp_get_current_user();
		$user_name = $user ? $user->display_name : __( 'Guest', 'stream' );
		$post_id   = $comment->comment_post_ID;
		self::log(
			__( '%s just added a new comment', 'stream' ),
			compact( 'user_name', 'post_id' ),
			$comment_id,
			array( 'comments' => 'created' )
		);

		// Adding a second record for 'approved' action if the comment was approved
		if ( $comment->comment_approved ) {
			self::log(
				__( '%s\'s comment has just been approved automatically' , 'stream' ),
				compact( 'user_name', 'post_id' ),
				$comment_id,
				array( 'comments' => 'approved' )
			);
		}

		// Adding a possibly-third record for 'replied' action if the comment has a parent
		if ( $comment->comment_parent ) {
			$parent = get_comment( $comment->comment_parent );
			$parent_author = $parent->user_id;
			$parent_author_name = ( $user = get_userdata( $parent_author ) )
				? $user->display_name
				: __( 'Guest', 'stream' );
			$post_title = ( $post = get_post( $post_id ) ) ? "\"$post->post_title\"" : __( 'some post', 'stream' );
			self::log(
				__( '%s posted a reply to %s at %s' , 'stream' ),
				compact( 'user_name', 'parent_author_name', 'post_title', 'parent_author', 'post_id' ),
				$comment_id,
				array( 'comments' => 'approved' )
			);
		}

	}

	/**
	 * Tracks comment updates
	 *
	 * @action edit_comment
	 */
	public static function callback_edit_comment( $comment_id ) {
		$user      = wp_get_current_user();
		$user_name = $user->display_name;
		$comment   = get_comment( $comment_id );
		$his_or    = ( $comment->comment_author == $user->ID ) ? __( 'his', 'stream' ) : __( 'a', 'stream' );
		$post_id   = $comment->comment_post_ID;

		self::log(
			__( '%s just updated %s comment', 'stream' ),
			compact( 'user_name', 'his_or', 'post_id' ),
			$comment_id,
			array( 'comments' => 'updated' )
		);
	}

	/**
	 * Tracks comment delete
	 *
	 * @action delete_comment
	 */
	public static function callback_delete_comment( $comment_id ) {
		$user      = wp_get_current_user();
		$user_name = $user->display_name;
		$comment   = get_comment( $comment_id );
		$his_or    = ( $comment->comment_author == $user->ID ) ? __( 'his', 'stream' ) : __( 'a', 'stream' );
		$post_id   = $comment->comment_post_ID;

		self::log(
			__( '%s just deleted %s comment', 'stream' ),
			compact( 'user_name', 'his_or', 'post_id' ),
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
		$user      = wp_get_current_user();
		$user_name = $user->display_name;
		$comment   = get_comment( $comment_id );
		$his_or    = ( $comment->comment_author == $user->ID ) ? __( 'his', 'stream' ) : __( 'a', 'stream' );
		$post_id   = $comment->comment_post_ID;

		self::log(
			__( '%s just trashed %s comment', 'stream' ),
			compact( 'user_name', 'his_or', 'post_id' ),
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
		$user      = wp_get_current_user();
		$user_name = $user->display_name;
		$comment   = get_comment( $comment_id );
		$his_or    = ( $comment->comment_author == $user->ID ) ? __( 'his', 'stream' ) : __( 'a', 'stream' );
		$post_id   = $comment->comment_post_ID;

		self::log(
			__( '%s just untrashed %s comment', 'stream' ),
			compact( 'user_name', 'his_or', 'post_id' ),
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
		$user      = wp_get_current_user();
		$user_name = $user->display_name;
		$comment   = get_comment( $comment_id );
		$his_or    = ( $comment->comment_author == $user->ID ) ? __( 'his', 'stream' ) : __( 'a', 'stream' );
		$post_id   = $comment->comment_post_ID;

		self::log(
			__( '%s just marked %s comment as spam', 'stream' ),
			compact( 'user_name', 'his_or', 'post_id' ),
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
		$user      = wp_get_current_user();
		$user_name = $user->display_name;
		$comment   = get_comment( $comment_id );
		$his_or    = ( $comment->comment_author == $user->ID ) ? __( 'his', 'stream' ) : __( 'a', 'stream' );
		$post_id   = $comment->comment_post_ID;

		self::log(
			__( '%s just unmarked %s comment as spam', 'stream' ),
			compact( 'user_name', 'his_or', 'post_id' ),
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
		$user           = wp_get_current_user();
		$user_name      = $user->display_name;
		$comment_id     = $comment->comment_ID;
		$post_id        = $comment->comment_post_ID;
		$comment_author = new WP_User( $comment->user_id );
		$author_name    = ( $comment_author->exists() )
			? $comment_author->display_name
			: __( 'Guest', 'stream' );
		self::log(
			__( '%s\'s comment has just been %s by %s', 'stream' ),
			compact( 'author_name', 'new_status', 'user_name', 'old_status', 'post_id' ),
			$comment_id,
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
		$comment_id     = $wpdb->last_result[0]->comment_ID;
		$comment        = get_comment( $comment_id );
		$user           = wp_get_current_user();
		$user_name      = $user->display_name;
		$post_id        = get_comment( $comment_id )->comment_post_ID;
		$post_title     = $post_id ? get_post_field( 'post_title', $post_id ) : __( 'a post', 'stream' );
		$comment_author = new WP_User( $comment->user_id );
		$author_name    = ( $comment_author->exists() )
			? $comment_author->display_name
			: __( 'Guest', 'stream' );
		self::log(
			__( '%s tried to post a duplicate comment on %s', 'stream' ),
			compact( 'author_name', 'post_title', 'user_name', 'post_id' ),
			$comment_id,
			array( 'comments' => 'duplicate' )
		);
	}

}
