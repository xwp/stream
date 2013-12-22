<?php

class WP_Stream_Connector_Settings extends WP_Stream_Connector {

	/**
	 * Context name
	 * @var string
	 */
	public static $name = 'settings';

	/**
	 * Actions registered for this context
	 * @var array
	 */
	public static $actions = array(
		'whitelist_options',
		'update_option_permalink_structure',
		'update_option_category_base',
		'update_option_tag_base',
	);

	/**
	 * Option names used in options-permalink.php
	 * @var array
	 */
	public static $permalink_options = array(
		'permalink_structure',
		'category_base',
		'tag_base',
	);

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Settings', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'updated' => __( 'Updated', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'settings'   => __( 'Settings', 'stream' ),
			'general'    => __( 'General', 'stream' ),
			'writing'    => __( 'Writing', 'stream' ),
			'reading'    => __( 'Reading', 'stream' ),
			'discussion' => __( 'Discussion', 'stream' ),
			'media'      => __( 'Media', 'stream' ),
			'permalink'  => __( 'Permalinks', 'stream' ),
		);
	}

	/**
	 * Return translated labels for all default Settings fields found in WordPress.
	 *
	 * @return array Field label translations
	 */
	public static function get_field_label( $field_key ) {
		$labels = array(
			// General
			'blogname'                      => __( 'Site Title', 'stream' ),
			'blogdescription'               => __( 'Tagline', 'stream' ),
			'siteurl'                       => __( 'WordPress Address (URL)', 'stream' ),
			'home'                          => __( 'Site Address (URL)', 'stream' ),
			'admin_email'                   => __( 'E-mail Address', 'stream' ),
			'users_can_register'            => __( 'Membership', 'stream' ),
			'default_role'                  => __( 'New User Default Role', 'stream' ),
			'timezone_string'               => __( 'Timezone', 'stream' ),
			'date_format'                   => __( 'Date Format', 'stream' ),
			'time_format'                   => __( 'Time Format', 'stream' ),
			'start_of_week'                 => __( 'Week Starts On', 'stream' ),
			// Writing
			'use_smilies'                   => __( 'Formatting', 'stream' ),
			'use_balanceTags'               => __( 'Formatting', 'stream' ),
			'default_category'              => __( 'Default Post Category', 'stream' ),
			'default_post_format'           => __( 'Default Post Format', 'stream' ),
			'mailserver_url'                => __( 'Mail Server Address', 'stream' ),
			'mailserver_login'              => __( 'Mail Server Login Name', 'stream' ),
			'mailserver_pass'               => __( 'Mail Server Password', 'stream' ),
			'default_email_category'        => __( 'Default Mail Category', 'stream' ),
			'ping_sites'                    => __( 'Update Services', 'stream' ),
			// Reading
			'show_on_front'                 => __( 'Front page displays', 'stream' ),
			'page_on_front'                 => __( 'Front page displays', 'stream' ),
			'page_for_posts'                => __( 'Front page displays', 'stream' ),
			'posts_per_page'                => __( 'Blog pages show at most', 'stream' ),
			'posts_per_rss'                 => __( 'Syndication feeds show the most recent', 'stream' ),
			'rss_use_excerpt'               => __( 'For each article in a feed, show', 'stream' ),
			'blog_public'                   => __( 'Search Engine Visibility', 'stream' ),
			// Discussion
			'default_pingback_flag'         => __( 'Default article settings', 'stream' ),
			'default_ping_status'           => __( 'Default article settings', 'stream' ),
			'default_comment_status'        => __( 'Default article settings', 'stream' ),
			'require_name_email'            => __( 'Other comment settings', 'stream' ),
			'comment_registration'          => __( 'Other comment settings', 'stream' ),
			'close_comments_for_old_posts'  => __( 'Other comment settings', 'stream' ),
			'close_comments_days_old'       => __( 'Other comment settings', 'stream' ),
			'thread_comments'               => __( 'Other comment settings', 'stream' ),
			'thread_comments_depth'         => __( 'Other comment settings', 'stream' ),
			'page_comments'                 => __( 'Other comment settings', 'stream' ),
			'comments_per_page'             => __( 'Other comment settings', 'stream' ),
			'default_comments_page'         => __( 'Other comment settings', 'stream' ),
			'comment_order'                 => __( 'Other comment settings', 'stream' ),
			'comments_notify'               => __( 'E-mail me whenever', 'stream' ),
			'moderation_notify'             => __( 'E-mail me whenever', 'stream' ),
			'comment_moderation'            => __( 'Before a comment appears', 'stream' ),
			'comment_whitelist'             => __( 'Before a comment appears', 'stream' ),
			'comment_max_links'             => __( 'Comment Moderation', 'stream' ),
			'moderation_keys'               => __( 'Comment Moderation', 'stream' ),
			'blacklist_keys'                => __( 'Comment Blacklist', 'stream' ),
			'show_avatars'                  => __( 'Avatar Display', 'stream' ),
			'avatar_rating'                 => __( 'Avatar Maximum Rating', 'stream' ),
			'avatar_default'                => __( 'Default Avatar', 'stream' ),
			// Media
			'thumbnail_size_w'              => __( 'Thumbnail Image Size', 'stream' ),
			'thumbnail_size_h'              => __( 'Thumbnail Image Size', 'stream' ),
			'thumbnail_crop'                => __( 'Thumbnail Image Size', 'stream' ),
			'medium_size_w'                 => __( 'Medium Image Size', 'stream' ),
			'medium_size_h'                 => __( 'Medium Image Size', 'stream' ),
			'large_size_w'                  => __( 'Large Image Size', 'stream' ),
			'large_size_h'                  => __( 'Large Image Size', 'stream' ),
			'uploads_use_yearmonth_folders' => __( 'Uploading Files Organization', 'stream' ),
			// Permalinks
			'permalink_structure'           => __( 'Permalink structure', 'stream' ),
			'category_base'                 => __( 'Category base', 'stream' ),
			'tag_base'                      => __( 'Tag base', 'stream' ),
		);

		if ( isset( $labels[$field_key] ) ) {
			return $labels[$field_key];
		}

		return $field_key;
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
		$context_labels = self::get_context_labels();
		if ( 'settings' !== $record->context && in_array( $record->context, array_keys( $context_labels ) ) ) {
			global $submenu;
			if ( ! empty( $submenu['options-general.php'] ) ) {
				$submenu_slug   = 'options-' . $record->context . '.php';
				$found_submenus = wp_list_filter(
					$submenu['options-general.php'],
					array( 2 => $submenu_slug )
				);

				if ( ! empty( $found_submenus ) ) {
					$target_submenu = array_pop( $found_submenus );
					if ( current_user_can( $target_submenu[1] ) ) {
						$text = sprintf( __( 'Edit %s Settings', 'stream' ), $context_labels[$record->context] );
						$url  = admin_url( $submenu_slug );

						$links[ $text ] = $url;
					}
				}
			}
		}

		return $links;
	}

	/**
	 * Trigger this connector core tracker, only on options.php page
	 *
	 * @action whitelist_options
	 */
	public static function callback_whitelist_options( $options ) {
		add_action( 'updated_option', array( __CLASS__, 'callback' ), 10, 3 );

		return $options;
	}

	/**
	 * Trigger this connector core tracker, only on options-permalink.php page
	 *
	 * @action update_option_permalink_structure
	 */
	public static function callback_update_option_permalink_structure( $old_value, $value ) {
		self::callback_updated_option( 'permalink_structure', $old_value, $value );
	}

	/**
	 * Trigger this connector core tracker, only on options-permalink.php page
	 *
	 * @action update_option_category_base
	 */
	public static function callback_update_option_category_base( $old_value, $value ) {
		self::callback_updated_option( 'category_base', $old_value, $value );
	}

	/**
	 * Trigger this connector core tracker, only on options-permalink.php page
	 *
	 * @action update_option_tag_base
	 */
	public static function callback_update_option_tag_base( $old_value, $value ) {
		self::callback_updated_option( 'tag_base', $old_value, $value );
	}

	/**
	 * Track updated settings
	 *
	 * @action updated_option
	 */
	public static function callback_updated_option( $option, $old_value, $value ) {
		global $new_whitelist_options, $whitelist_options;
		$options = array_merge(
			(array) $whitelist_options,
			$new_whitelist_options,
			array( 'permalink' => self::$permalink_options )
		);

		foreach ( $options as $key => $opts ) {
			if ( in_array( $option, $opts ) ) {
				$current_key = $key;
				break;
			}
		}

		if ( ! isset( $current_key ) ) {
			$current_key = 'settings';
		}

		$label = self::get_field_label( $option );

		// Prevent php fatal error when saving array as option
		$old_value = maybe_serialize( $old_value );
		$value     = maybe_serialize( $value );

		self::log(
			__( '"%s" setting was updated', 'stream' ),
			compact( 'label', 'option', 'old_value', 'value' ),
			null,
			array(
				$current_key => 'updated',
			)
		);
	}

}
