<?php

class WP_Stream_Connector_Media extends WP_Stream_Connector {

	/**
	 * Context name
	 *
	 * @var string
	 */
	public static $name = 'media';

	/**
	 * Actions registered for this context
	 *
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
	 * Based on extension types used by wp_ext2type() in wp-includes/functions.php.
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'image'       => __( 'Image', 'stream' ),
			'audio'       => __( 'Audio', 'stream' ),
			'video'       => __( 'Video', 'stream' ),
			'document'    => __( 'Document', 'stream' ),
			'spreadsheet' => __( 'Spreadsheet', 'stream' ),
			'interactive' => __( 'Interactive', 'stream' ),
			'text'        => __( 'Text', 'stream' ),
			'archive'     => __( 'Archive', 'stream' ),
			'code'        => __( 'Code', 'stream' ),
		);
	}

	/**
	 * Return the file type for an attachment which corresponds with a context label
	 *
	 * @param  object $file_uri  URI of the attachment
	 * @return string            A file type which corresponds with a context label
	 */
	public static function get_attachment_type( $file_uri ) {
		$extension      = pathinfo( $file_uri, PATHINFO_EXTENSION );
		$extension_type = wp_ext2type( $extension );

		if ( empty( $extension_type ) ) {
			$extension_type = 'document';
		}

		$context_labels = self::get_context_labels();

		if ( ! isset( $context_labels[ $extension_type ] ) ) {
			$extension_type = 'document';
		}

		return $extension_type;
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
			$message = _x(
				'Attached "%1$s" to "%2$s"',
				'1: Attachment title, 2: Parent post title',
				'stream'
			);
		} else {
			$message = __( 'Added "%s" to Media library', 'stream' );
		}

		$name            = $post->post_title;
		$url             = $post->guid;
		$parent_id       = $post->post_parent;
		$parent          = get_post( $parent_id );
		$parent_title    = $parent_id ? $parent->post_title : null;
		$attachment_type = self::get_attachment_type( $post->guid );

		self::log(
			$message,
			compact( 'name', 'parent_title', 'parent_id', 'url' ),
			$post_id,
			array( $attachment_type => $post->post_parent ? 'attached' : 'uploaded' )
		);
	}

	/**
	 * Tracks editing attachments
	 *
	 * @action edit_attachment
	 */
	public static function callback_edit_attachment( $post_id ) {
		$post            = get_post( $post_id );
		$message         = __( 'Updated "%s"', 'stream' );
		$name            = $post->post_title;
		$attachment_type = self::get_attachment_type( $post->guid );

		self::log(
			$message,
			compact( 'name' ),
			$post_id,
			array( $attachment_type => 'updated' )
		);
	}

	/**
	 * Tracks deletion of attachments
	 *
	 * @action delete_attachment
	 */
	public static function callback_delete_attachment( $post_id ) {
		$post            = get_post( $post_id );
		$parent          = $post->post_parent ? get_post( $post->post_parent ) : null;
		$parent_id       = $parent ? $parent->ID : null;
		$message         = __( 'Deleted "%s"', 'stream' );
		$name            = $post->post_title;
		$url             = $post->guid;
		$attachment_type = self::get_attachment_type( $post->guid );

		self::log(
			$message,
			compact( 'name', 'parent_id', 'url' ),
			$post_id,
			array( $attachment_type => 'deleted' )
		);
	}

	/**
	 * Tracks changes made in the image editor
	 *
	 * @action delete_attachment
	 */
	public static function callback_wp_save_image_editor_file( $dummy, $filename, $image, $mime_type, $post_id ) {
		$name            = basename( $filename );
		$attachment_type = self::get_attachment_type( $post->guid );

		self::log(
			__( 'Edited image "%s"', 'stream' ),
			compact( 'name', 'filename', 'post_id' ),
			$post_id,
			array( $attachment_type => 'edited' )
		);
	}

	public static function callback_wp_save_image_file( $dummy, $filename, $image, $mime_type, $post_id ) {
		return self::callback_wp_save_image_editor_file( $dummy, $filename, $image, $mime_type, $post_id );
	}

}
