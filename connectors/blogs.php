<?php

class WP_Stream_Connector_Blogs extends WP_Stream_Connector {

	/**
	 * Context name
	 * @var string
	 */
	public static $name = 'blogs';

	/**
	 * Actions registered for this context
	 * @var array
	 */
	public static $actions = array(
		'wpmu_new_blog',
		'wpmu_activate_blog',
		'wpmu_new_user',
		'wpmu_add_existing_user',
		'add_user_to_blog',
		'remove_users_from_blog',
		'wpmu_activate_user',
		'make_spam_blog',
		'make_ham_blog',
		'mature_blog',
		'unmature_blog',
		'archive_blog',
		'unarchive_blog',
		'make_delete_blog',
		'make_undelete_blog',
		'update_blog_public',

	);

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Blogs', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'updated'       => __( 'Updated', 'stream' ),
			'created'       => __( 'Created', 'stream' ),
			'archive_blog'  => __( 'Archived', 'stream' ),
			'deleted'       => __( 'Deleted', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
//	public static function get_context_labels() {
//		$blogs = wp_get_sites();
//		$blog_labels = array();
//		foreach( $blogs as $blog ) {
//			$blog_labels[$blog['blog_id']] = array(
//				'domain' => $blog['domain'],
//				'public' => $blog['public'],
//				'last_updated' => $blog['last_updated'],
//				'registered' => $blog['registered'],
//				'archived' => $blog['archived'],
//				'mature' => $blog['mature'],
//				'spam' => $blog['spam'],
//				'deleted' => $blog['deleted'],
//			);
//		}
//	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 * @param  array $links      Previous links registered
	 * @param  int   $record     Stream record
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {

		return $links;
	}

	/**
	 * @param $blog_id
	 * @action activate_blog
	 */
	public static function callback_activate_blog( $blog_id ) {

	}

	/**
	 * @param $blog_id
	 * @action wpmu_new_blog
	 */
	public static function callback_wpmu_new_blog( $blog_id ) {

	}

	/**
	 * @param $post_id
	 * @action make_spam_blog
	 */
	public static function callback_make_spam_blog( $post_id ) {

	}

	/**
	 * @param $blog
	 * @action make_ham_blog
	 */
	public static function callback_make_ham_blog( $blog ) {

	}

	/**
	 * @param $blog_id
	 * @action mature_blog
	 */
	public static function callback_mature_blog( $blog_id ) {

	}

	/**
	 * @param $blog
	 * @action unmature_blog
	 */
	public static function callback_unmature_blog ( $blog ) {

	}

	/**
	 * @param $blog
	 * @action archive_blog
	 */
	public static function callback_archive_blog( $blog ) {

	}

	/**
	 * @param $blog_id
	 * @action unarchive_blog
	 */
	public static function callback_unarchive_blog( $blog_id ) {

	}

	/**
	 * @param $blog_id
	 * @action make_delete_blog
	 */
	public static function callback_make_delete_blog( $blog_id ) {

	}

	/**
	 * @param $blog_id
	 * @action undelete_blog
	 */
	public static function callback_make_undelete_blog( $blog_id ) {

	}

	/**
	 * @param $blog_id
	 * @action update_blog_public
	 */
	public static function callback_update_blog_public( $blog_id ) {

	}

}