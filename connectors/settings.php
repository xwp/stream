<?php

class WP_Stream_Connector_Settings extends WP_Stream_Connector {

	const HIGHLIGHT_FIELD_URL_HASH_PREFIX = 'wp-stream-highlight:';

	/**
	 * Context name
	 *
	 * @var string
	 */
	public static $name = 'settings';

	/**
	 * Actions registered for this context
	 *
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
	 *
	 * @var array
	 */
	public static $permalink_options = array(
		'permalink_structure',
		'category_base',
		'tag_base',
	);

	/**
	 * Register all context hooks
	 *
	 * @return void
	 */
	public static function register() {
		parent::register();

		add_action( 'admin_head', array( __CLASS__, 'highlight_field' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_jquery_color' ) );
		add_filter( 'wp_stream_action_link_url', array( __CLASS__, 'stream_settings_url' ), 10, 2 );
	}

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
			'wp_stream'  => __( 'Stream', 'stream' ),
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

		if ( isset( $labels[ $field_key ] ) ) {
			return $labels[ $field_key ];
		}

		return $field_key;
	}

	/**
	 * Enqueue jQuery Color plugin
	 *
	 * @action admin_enqueue_scripts
	 * @return void
	 */
	public static function enqueue_jquery_color() {
		wp_enqueue_script( 'jquery-color' );
	}

	/**
	 * Return translated labels for all serialized Settings found in WordPress.
	 *
	 * @return string Field key translation or key itself if not found
	 */
	public static function get_serialized_field_label( $option_name, $field_key ) {
		$labels = array(
			// to be updated
		);

		/**
		 * Filter allows for insertion of serialized labels
		 *
		 * @param  array  $lables  Serialized labels
		 * @return array  Updated array of serialzed labels
		 */
		$labels = apply_filters( 'wp_stream_serialized_labels', $labels );

		if ( isset( $labels[ $option_name ] ) && isset( $labels[ $option_name ][ $field_key ] ) ) {
			return $labels[ $option_name ][ $field_key ];
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
				$submenu_slug   = sprintf( 'options-%s.php', $record->context );
				$found_submenus = wp_list_filter(
					$submenu['options-general.php'],
					array( 2 => $submenu_slug )
				);

				if ( ! empty( $found_submenus ) || $record->context === 'wp_stream' ) {
					$target_submenu = array_pop( $found_submenus );

					if ( current_user_can( $target_submenu[1] ) ) {
						$text = sprintf( __( 'Edit %s Settings', 'stream' ), $context_labels[$record->context] );

						$url = apply_filters( 'wp_stream_action_link_url', admin_url( $submenu_slug ), $record );

						$field_name = get_stream_meta( $record->ID, 'option_key', true );

						if ( '' === $field_name ) {
							$field_name = get_stream_meta( $record->ID, 'option', true );
						}

						if ( '' !== $field_name ) {
							$url = sprintf( '%s#%s%s', rtrim( preg_replace( '/#.*/', '', $url ), '/' ), self::HIGHLIGHT_FIELD_URL_HASH_PREFIX, $field_name );
						}

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

		if ( 0 === strpos( $option, '_transient_' ) ) {
			return;
		}

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

		$changed_options = array();

		if ( is_array( $old_value ) && is_array( $value ) ) {
			$changed_keys = array();

			// Added keys
			$changed_keys = array_merge( $changed_keys, array_keys( array_diff_key( $value, $old_value ) ) );

			// Deleted keys
			$changed_keys = array_merge( $changed_keys, array_keys( array_diff_key( $old_value, $value ) ) );

			// array_diff_assoc is not sufficient
			foreach ( array_diff( array_keys( $value ), $changed_keys ) as $option_key ) {
				if ( $value[ $option_key ] !== $old_value[ $option_key ] ) {
					$changed_keys[] = $option_key;
				}
			}

			foreach ( $changed_keys as $field_key ) {
				$changed_options[] = array(
					'label'      => self::get_serialized_field_label( $option, $field_key ),
					'option'     => $current_key,
					'option_key' => $field_key,
					// Prevent fatal error when saving option as array
					'old_value'  => isset( $old_value[ $field_key ] ) ? maybe_serialize( $old_value[ $field_key ] ) : null,
					'value'      => isset( $value[ $field_key ] ) ? maybe_serialize( $value[ $field_key ] ) : null,
				);
			}
		} else {
			$changed_options[] = array(
				'label'     => self::get_field_label( $option ),
				'option'    => $option,
				// Prevent fatal error when saving option as array
				'old_value' => maybe_serialize( $old_value ),
				'value'     => maybe_serialize( $value ),
			);
		}

		foreach ( $changed_options as $properties ) {
			self::log(
				__( '"%s" setting was updated', 'stream' ),
				$properties,
				null,
				array( $current_key => 'updated' )
			);
		}
	}

	/**
	 * @filter wp_stream_action_link_url
	 */
	public static function stream_settings_url( $url, $record ) {
		if ( 'wp_stream' === $record->context ) {
			$option_key = get_stream_meta( $record->ID, 'option_key', true );
			$url_tab    = null;

			if ( $option_key !== '' ) {
				foreach ( WP_Stream_Settings::get_fields() as $tab_name => $tab_properties ) {
					foreach ( $tab_properties['fields'] as $field ) {
						$field_key = sprintf( '%s_%s', $tab_name, $field['name'] );
						if ( $field_key === $option_key ) {
							$url_tab = $tab_name;
							break 2;
						}
					}
				}
			}

			$url = add_query_arg(
				array(
					'page' => WP_Stream_Admin::SETTINGS_PAGE_SLUG,
					'tab'  => $url_tab,
				),
				admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
			);
		}

		return $url;
	}

	/**
	 * Add class to highlight field by URL param
	 *
	 * @action admin_head
	 */
	public static function highlight_field() {
		?>
		<script>
			(function ($) {
				$(function () {
					var hashPrefix = <?php echo json_encode( self::HIGHLIGHT_FIELD_URL_HASH_PREFIX ) ?>,
					    hashFieldName = "",
						fieldNames = [],
						$select2Choices = {},
						$field = {};

					if (location.hash.substr(1, hashPrefix.length) === hashPrefix) {
						hashFieldName = location.hash.substr(hashPrefix.length + 1);
						fieldNames = [hashFieldName];

						$field = $("input, textarea, select")
							.filter(function () {
								return fieldNames.indexOf($(this).attr("name")) > -1;
							});

						// try to find wp_stream field
						if ( $field.length === 0 ) {
							fieldNames = [
								"wp_stream_" + hashFieldName,
								"wp_stream[" + hashFieldName + "]"
							];

							$field = $("input, textarea, select, div").filter(function() {
								return fieldNames.indexOf( $(this).attr("id") ) > -1;
							});

							// if the field has been selectified, the list is the one to be colorized
							$select2Choices = $field.find(".select2-choices");
							if ( $select2Choices.length === 1 ) {
								$field = $select2Choices;
							}
						}

						$("html, body")
							.animate({
								scrollTop: ($field.closest("tr").length === 1 ? $field.closest("tr") : $field).offset().top - $("#wpadminbar").height()
							}, 1000, function () {
								$field
									.css("background", $(this).css("background-color"))
									.animate({
										backgroundColor: "#fffedf",
									}, 250);

								$("label")
									.filter(function () {
										return fieldNames.indexOf( $(this).attr("for") ) > -1;
									})
									.animate({
										color: "#d54e21"
									}, 250);
								}
							);
					}
				});
			}(jQuery));
		</script>
		<?php
	}

}
