<?php

class WP_Stream_Connector_Media extends WP_Stream_Connector {

	/**
	 * Context name
	 * @var string
	 */
	public static $name = 'media';

	/**
	 * Actions registered for this context
	 * @var array
	 */
	public static $actions = array(
		'add_attachment',
		'edit_attachment',
		'delete_attachment',
		'wp_save_image_editor_file',
		'wp_save_image_file',
	);

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Media', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'attached'   => __( 'Attached', 'stream' ),
			'uploaded'   => __( 'Uploaded', 'stream' ),
			'updated'    => __( 'Updated', 'stream' ),
			'deleted'    => __( 'Deleted', 'stream' ),
			'assigned'   => __( 'Assigned', 'stream' ),
			'unassigned' => __( 'Unassigned', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'media' => __( 'Media', 'stream' ),
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
			if ( $link = get_edit_post_link( $record->object_id ) ) {
				$links[ __( 'Edit Media', 'stream' ) ] = $link;
			}
			if ( $link = get_permalink( $record->object_id ) ) {
				$links[ __( 'View', 'stream' ) ] = $link;
			}
		}
		return $links;
	}

	/**
	 * Tracks creation of attachments
	 *
	 * @action add_attachment
	 */
	public static function callback_add_attachment( $post_id ) {
		$post = get_post( $post_id );
		if ( $post->post_parent ) {
			$message = __( 'Attached "%s" to "%s"', 'stream' );
		} else {
			$message = __( 'Added "%s" to Media library', 'stream' );
		}
		$name      = $post->post_title;
		$url       = $post->guid;
		$parent_id = $post->post_parent;
		if ( $parent_id && $parent = get_post( $post->post_parent ) ) $parent_title = $parent->post_title;

		self::log(
			$message,
			compact( 'name', 'parent_title', 'parent_id', 'url' ),
			$post_id,
			array( 'media' => $post->post_parent ? 'attached' : 'uploaded' )
			);
	}

	/**
	 * Tracks editing attachments
	 *
	 * @action edit_attachment
	 */
	public static function callback_edit_attachment( $post_id ) {
		$post    = get_post( $post_id );
		$message = __( 'Updated "%s"', 'stream' );
		$name    = $post->post_title;

		self::log(
			$message,
			compact( 'name' ),
			$post_id,
			array( 'media' => 'updated' )
			);
	}

	/**
	 * Tracks deletion of attachments
	 *
	 * @action delete_attachment
	 */
	public static function callback_delete_attachment( $post_id ) {
		$post   = get_post( $post_id );
		$parent = $post->post_parent ? get_post( $post->post_parent ) : null;
		if ( $parent ) $parent_id = $parent->ID;
		$message = __( 'Deleted "%s"', 'stream' );
		$name    = $post->post_title;
		$url     = $post->guid;

		self::log(
			$message,
			compact( 'name', 'parent_id', 'url' ),
			$post_id,
			array( 'media' => 'deleted' )
			);
	}

	public static function callback_wp_save_image_editor_file( $dummy, $filename, $image, $mime_type, $post_id ) {
		$name = basename( $filename );
		self::log(
			__( 'Edited image "%s"', 'stream' ),
			compact( 'name', 'filename', 'post_id' ),
			$post_id,
			array( 'media' => 'edited' )
			);
	}

	public static function callback_wp_save_image_file( $dummy, $filename, $image, $mime_type, $post_id ) {
		return self::callback_wp_save_image_editor_file( $dummy, $filename, $image, $mime_type, $post_id );
	}


}
