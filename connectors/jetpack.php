<?php

class WP_Stream_Connector_Jetpack extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'jetpack';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'jetpack_log_entry',
		'sharing_get_services_state',
		'update_option',
		'add_option',
		'delete_option',
		'jetpack_module_configuration_load_monitor',
		'wp_ajax_jetpack_post_by_email_enable', // @todo These three actions do not verify whether the action has been done or if an error has been raised
		'wp_ajax_jetpack_post_by_email_regenerate',
		'wp_ajax_jetpack_post_by_email_disable',
	);

	/**
	 * Tracked option keys
	 *
	 * @var array
	 */
	public static $options = array();

	/**
	 * Tracking registered Settings, with overridden data
	 *
	 * @var array
	 */
	public static $options_override = array();

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return __( 'Jetpack', 'jetpack' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'activated'   => __( 'Activated', 'stream-connector-jetpack' ),
			'deactivated' => __( 'Dectivated', 'stream-connector-jetpack' ),
			'register'    => __( 'Connected', 'stream-connector-jetpack' ),
			'disconnect'  => __( 'Disconnected', 'stream-connector-jetpack' ),
			'authorize'   => __( 'Link', 'stream-connector-jetpack' ),
			'unlink'      => __( 'Unlink', 'jetpack' ),
			'updated'     => __( 'Updated', 'stream' ),
			'added'       => __( 'Added', 'stream-connector-jetpack' ),
			'removed'     => __( 'Removed', 'stream-connector-jetpack' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'modules' => __( 'Modules', 'jetpack' ),
			'blogs'   => __( 'Blogs', 'default' ),
			'users'   => __( 'Users', 'default' ),
			'options' => __( 'Options', 'default' ),
			'sharedaddy' => __( 'Sharing', 'jetpack' ),
			'publicize' => __( 'Publicize', 'jetpack' ),
			'gplus-authorship' => __( 'Google+ Profile', 'jetpack' ),
			'stats'   => __( 'WordPress.com Stats', 'jetpack' ),
			'carousel'   => __( 'Carousel', 'jetpack' ),
			'custom-css' => __( 'Custom CSS', 'jetpack' ),
			'subscriptions' => __( 'Subscriptions', 'jetpack' ),
			'jetpack-comments' => __( 'Comments', 'default' ),
			'infinite-scroll' => __( 'Infinite Scroll', 'jetpack' ),
			'sso' => __( 'SSO', 'stream-connector-jetpack' ),
			'likes' => __( 'Likes', 'stream-connector-jetpack' ),
			'minileven' => __( 'Mobile', 'stream-connector-jetpack' ),
			'monitor' => __( 'Monitor', 'stream-connector-jetpack' ),
			'post-by-email' => __( 'Post by Email', 'jetpack' ),
			'related-posts' => __( 'Related Posts', 'jetpack' ),
			'verification-tools' => __( 'Site Verification', 'jetpack' ),
			'tiled-gallery' => __( 'Tiled Galleries', 'jetpack' ),
			'videopress' => __( 'VideoPress', 'jetpack' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array $links  Previous links registered
	 * @param  int   $record Stream record
	 *
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		// @todo provide proper action links
		if ( 'jetpack' === $record->connector ) {
			if ( 'modules' === $record->context ) {
				$slug = wp_stream_get_meta( $record->ID, 'module_slug', true );
				if ( Jetpack::is_module_active( $slug )  ) {
					if ( apply_filters( 'jetpack_module_configurable_' . $slug, false ) ) {
						$links[ __( 'Configure', 'jetpack' ) ] = Jetpack::module_configuration_url( $slug );;
					}
					$links[ __( 'Deactivate', 'jetpack' ) ] = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'deactivate',
								'module' => $slug,
							),
							Jetpack::admin_url()
						),
						'jetpack_deactivate-' . sanitize_title( $slug )
					);
				} else {
					$links[ __( 'Activate', 'jetpack' ) ] = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'activate',
								'module' => $slug,
							),
							Jetpack::admin_url()
						),
						'jetpack_activate-' . sanitize_title( $slug )
					);
				}
			}
			elseif ( Jetpack::is_module_active( str_replace( 'jetpack-', '', $record->context ) ) ) {
				$slug = str_replace( 'jetpack-', '', $record->context ); // handling jetpack-comment anomaly
				if ( apply_filters( 'jetpack_module_configurable_' . $slug, false ) ) {
					$links[ __( 'Configure module', 'stream-connector-jetpack' ) ] = Jetpack::module_configuration_url( $slug );;
				}
			}
		}
		return $links;
	}

	public static function register() {
		parent::register();

		add_filter( 'wp_stream_log_data', array( __CLASS__, 'log_override' ) );

		self::$options = array(
			'jetpack_options' => null,
			// Sharing module
			'hide_gplus' => null,
			'gplus_authors' => null,
			'sharing-options' => array(
				'label' => __( 'Sharing options', 'stream-connector-jetpack' ),
				'context' => 'sharedaddy',
			),
			'sharedaddy_disable_resources' => null,
			'jetpack-twitter-cards-site-tag' => array(
				'label' => __( 'Twitter site tag', 'stream-connector-jetpack' ),
				'context' => 'sharedaddy',
			),
			// Stats module
			'stats_options' => array(
				'label' => __( 'WordPress.com Stats', 'stream-connector-jetpack' ),
				'context' => 'stats',
			),
			// Comments
			'jetpack_comment_form_color_scheme' => array(
				'label'   => __( 'Color Scheme', 'jetpack' ),
				'context' => 'jetpack-comments',
			),
			// Likes
			'disabled_likes' => array(
				'label'   => __( 'WP.com Site-wide Likes', 'stream-connector-jetpack' ),
				'context' => 'likes',
			),
			// Mobile
			'wp_mobile_excerpt' => array(
				'label' => __( 'Excerpts appearance' , 'stream-connector-jetpack' ),
				'context' => 'minileven',
			),
			'wp_mobile_app_promos' => array(
				'label' => __( 'App promos' , 'stream-connector-jetpack' ),
				'context' => 'minileven',
			),
		);

		self::$options_override = array(
			// Carousel Module
			'carousel_background_color' => array(
				'label' => __( 'Background color', 'jetpack' ),
				'context' => 'carousel',
			),
			'carousel_display_exif' => array(
				'label' => __( 'Metadata', 'jetpack' ),
				'context' => 'carousel',
			),
			// Subscriptions
			'stb_enabled' => array(
				'label' => __( 'Follow blog comment form button', 'jetpack' ),
				'context' => 'subscriptions',
			),
			'stc_enabled' => array(
				'label' => __( 'Follow comments form button', 'jetpack' ),
				'context' => 'subscriptions',
			),
			// Jetpack comments
			'highlander_comment_form_prompt' => array(
				'label' => __( 'Greeting Text', 'jetpack' ),
				'context' => 'jetpack-comments',
			),
			// Infinite Scroll
			'infinite_scroll_google_analytics' => array(
				'label' => __( 'Infinite Scroll Google Analytics', 'jetpack' ),
				'context' => 'infinite-scroll',
			),
			// SSO
			'jetpack_sso_require_two_step' => array(
				'label' => __( 'Require Two-Step Authentication' , 'jetpack' ),
				'context' => 'sso',
			),
			'jetpack_sso_match_by_email' => array(
				'label' => __( 'Match by Email' , 'jetpack' ),
				'context' => 'sso',
			),
			// Related posts
			'jetpack_relatedposts' => array(
				'show_headline' => array(
					'label' => __( 'Show Related Posts Headline' , 'stream-connector-jetpack' ),
					'context' => 'related-posts',
				),
				'show_thumbnails' => array(
					'label' => __( 'Show Related Posts Thumbnails' , 'stream-connector-jetpack' ),
					'context' => 'related-posts',
				),
			),
			// Site verification
			'verification_services_codes' => array(
				'google' => array(
					'label' => __( 'Google Webmaster Tools Token' , 'stream-connector-jetpack' ),
					'context' => 'verification-tools',
				),
				'bing' => array(
					'label' => __( 'Bing Webmaster Center Token' , 'stream-connector-jetpack' ),
					'context' => 'verification-tools',
				),
				'pinterest' => array(
					'label' => __( 'Pinterest Site Verification Token' , 'stream-connector-jetpack' ),
					'context' => 'verification-tools',
				),
			),
			// Tiled galleries
			'tiled_galleries' => array(
				'label' => __( 'Tiled Galleries', 'jetpack' ),
				'context' => 'tiled-gallery',
			),
		);
	}

	/**
	 * Track Jetpack log entries
	 * Includes:
	 * - Activation/Deactivation of modules
	 * - Registration/Disconnection of blogs
	 * - Authorization/unlinking of users
	 *
	 * @param array $entry
	 */
	public static function callback_jetpack_log_entry( array $entry ) {
		$method  = $entry['code'];
		$data    = $entry['data'];
		$context = null;
		$action  = null;

		if ( in_array( $method, array( 'activate', 'deactivate' ) ) ) {
			$module_slug = $data;
			$module      = Jetpack::get_module( $module_slug );
			$module_name = $module['name'];
			$context     = 'modules';
			$action      = $method . 'd';
			$meta        = compact( 'module_slug' );
			$message     = sprintf(
				__( '%s module has been %s', 'stream-connector-jetpack' ),
				$module_name,
				( $action === 'activated' ) ? __( 'activated', 'stream-connector-jetpack' ) : __( 'deactivated', 'stream-connector-jetpack' )
			);
		} elseif ( in_array( $method, array( 'authorize', 'unlink' ) ) ) {
			$user_id = intval( $data );
			if ( empty( $user_id ) ) {
				$user_id = get_current_user_id();
			}
			$user       = new WP_User( $user_id );
			$user_email = $user->user_email;
			$user_login = $user->user_login;
			$context    = 'users';
			$action     = $method;
			$meta       = compact( 'user_id', 'user_email', 'user_login' );
			$message    = sprintf(
				__( '%s %s his account %s Jetpack', 'stream-connector-jetpack' ),
				$user->display_name,
				( $action === 'unlink' ) ? __( 'unlinked', 'stream-connector-jetpack' ) : __( 'linked', 'stream-connector-jetpack' ),
				( $action === 'unlink' ) ? __( 'from', 'stream-connector-jetpack' ) : __( 'to', 'stream-connector-jetpack' )
			);
		} elseif ( in_array( $method, array( 'register', 'disconnect', 'subsiteregister', 'subsitedisconnect' ) ) ) {
			$context      = 'blogs';
			$action       = str_replace( 'subsite', '', $method );
			$is_multisite = ( 0 === strpos( $method, 'subsite' ) );
			$blog_id      = $is_multisite
				? ( isset( $_GET['site_id'] ) ? intval( $_GET['site_id'] ) : null )
				: get_current_blog_id();
			if ( empty( $blog_id ) ) {
				return;
			}
			$meta = array();
			if ( ! $is_multisite ) {
				$message = sprintf(
					__( 'Site has been %s Jetpack', 'stream-connector-jetpack' ),
					( $action === 'register' ) ? __( 'registered to', 'stream-connector-jetpack' ) : __( 'disconnected from', 'stream-connector-jetpack' )
				);
			} else {
				$blog_details = get_blog_details( array( 'blog_id' => $blog_id ) );
				$blog_name    = $blog_details->blogname;
				$meta += compact( 'blog_id', 'blog_name' );
				$message = sprintf(
					__( '"%s" blog has been %s Jetpack', 'stream-connector-jetpack' ),
					$blog_name,
					( $action === 'register' ) ? __( 'registered to', 'stream-connector-jetpack' ) : __( 'disconnected from', 'stream-connector-jetpack' )
				);
			}
		}

		if ( empty( $message ) ) {
			return;
		}

		self::log( $message, $meta, null, array( $context => $action ) );
	}

	/**
	 * Track visible/enabled sharing services ( buttons )
	 *
	 * @param $state
	 */
	public static function callback_sharing_get_services_state( $state ) {
		self::log(
			__( 'Sharing services has been updated', 'stream-connector-jetpack' ),
			$state,
			null,
			array(
				'sharedaddy' => 'updated',
			)
		);
	}

	public static function callback_update_option( $option, $old, $new ) {
		self::check( $option, $old, $new );
	}

	public static function callback_add_option( $option, $val ) {
		self::check( $option, null, $val );
	}

	public static function callback_delete_option( $option ) {
		self::check( $option, null, null );
	}

	/**
	 * Track Monitor module notification status
	 */
	public static function callback_jetpack_module_configuration_load_monitor() {
		if ( $_POST ) {
			$active = isset( $_POST['receive_jetpack_monitor_notification'] );
			self::log(
				__( 'Monitor notifications was %s', 'stream-connector-jetpack' ),
				array(
					'status' => $active ? __( 'Activated', 'stream-connector-stream' ) : __( 'Deactivated', 'stream-connector-stream' ),
					'option' => 'receive_jetpack_monitor_notification',
					'old_value' => ! $active,
					'value' => $active,
				),
				null,
				array( 'monitor' => 'updated' )
			);
		}
	}

	public static function callback_wp_ajax_jetpack_post_by_email_enable() {
		self::track_post_by_email( true );
	}
	public static function callback_wp_ajax_jetpack_post_by_email_regenerate() {
		self::track_post_by_email( null );
	}
	public static function callback_wp_ajax_jetpack_post_by_email_disable() {
		self::track_post_by_email( false );
	}
	public static function track_post_by_email( $status ) {
		if ( true === $status ) {
			$action = __( 'Enabled', 'stream-connector-jetpack' );
		} elseif ( false === $status ) {
			$action = __( 'Disabled', 'stream-connector-jetpack' );
		} elseif ( null === $status ) {
			$action = __( 'Renegerated', 'stream-connector-jetpack' );
		}

		$user = wp_get_current_user();
		self::log(
			__( '%1$s has %2$s Post by Email', 'stream-connector-jetpack' ),
			array(
				'user_displayname' => $user->display_name,
				'action' => $action,
				'status' => $status,
			),
			null,
			array(
				'post-by-email' => 'updated',
			)
		);
	}

	public static function check( $option, $old_value, $new_value ){
		if ( ! array_key_exists( $option, self::$options ) ) {
			return;
		}

		if ( is_null( self::$options[ $option ] ) ) {
			call_user_func( array( __CLASS__, 'check_' . str_replace( '-', '_', $option ) ), $old_value, $new_value );
		} else {
			$data = self::$options[ $option ];
			$option_title = $data['label'];
			self::log(
				__( '"%s" setting was updated', 'stream' ),
				compact( 'option_title', 'option', 'old_value', 'new_value' ),
				null,
				array(
					$data['context'] => isset( $data['action'] ) ? $data['action'] : 'updated',
				)
			);
		}
	}

	public static function check_jetpack_options( $old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( self::get_changed_keys( $old_value, $new_value, 1 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		foreach ( $options as $option => $option_value ) {
			$settings = self::get_settings_def( $option, $option_value );
			if ( ! $settings ) {
				continue;
			}

			if ( $option_value === 0 ) { // Skip updated array with updated members, we'll be logging those instead
				continue;
			}

			$settings['meta'] += array(
				'option'    => $option,
				'old_value' => maybe_serialize( $old_value ),
				'value'     => maybe_serialize( $new_value ),
			);

			self::log(
				$settings['message'],
				$settings['meta'],
				isset( $settings['object_id'] ) ? $settings['object_id'] : null,
				array(
					$settings['context'] => $settings['action'],
				)
			);
		}
	}

	public static function check_hide_gplus( $old_value, $new_value ) {
		$status = ! is_null( $new_value );
		if ( $status && $old_value ) {
			return false;
		}

		self::log(
			__( 'G+ profile display was %s', 'stream-connector-jetpack' ),
			array(
				'action' => $status ? __( 'Enabled', 'stream' ) : __( 'Disabled', 'stream' ),
			),
			null,
			array(
				'gplus-authorship' => 'updated',
			)
		);
	}

	public static function check_gplus_authors( $old_value, $new_value ) {
		$user      = wp_get_current_user();
		$connected = is_array( $new_value ) && array_key_exists( $user->ID, $new_value );

		self::log(
			__( '%s has %s his Google+ account', 'stream-connector-jetpack' ),
			array(
				'display_name' => $user->display_name,
				'action'       => $connected ? __( 'connected', 'stream-connector-jetpack' ) : __( 'disconnected', 'stream-connector-jetpack' ),
				'user_id'      => $user->ID,
			),
			$user->ID,
			array(
				'gplus-authorship' => 'updated',
			)
		);
	}

	public static function check_sharedaddy_disable_resources( $old_value, $new_value ) {
		if ( $old_value == $new_value ) {
			return;
		}
		$status = ! $new_value ? 'enabled' : 'disabled'; // disabled = 1
		self::log(
			__( 'Sharing CSS/JS has been %s', 'stream-connector-jetpack' ),
			compact( 'status', 'old_value', 'new_value' ),
			null,
			array(
				'sharing' => 'updated',
			)
		);
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public static function log_override( array $data ) {
		// Handling our Settings
		if ( 'settings' === $data['connector'] && isset( self::$options_override[ $data['args']['option'] ] ) ) {
			if ( isset( $data['args']['option_key'] ) ) {
				$overrides = self::$options_override[ $data['args']['option'] ][ $data['args']['option_key'] ];
			} else {
				$overrides = self::$options_override[ $data['args']['option'] ];
			}
			if ( isset( $overrides ) ) {
				$data['args']['label'] = $overrides['label'];
				$data['args']['context'] = $overrides['context'];
				$data['contexts'] = array( $overrides['context'] => current( $data['contexts'] ) );
				$data['connector'] = self::$name;
			}
		}
		elseif ( 'posts' === $data['connector'] && isset( $data['contexts']['safecss'] ) ) {
			$data = array_merge(
				$data,
				array(
					'connector' => self::$name,
					'message' => __( 'Custom CSS has been updated', 'stream-connector-jetpack' ),
					'args' => array(),
					'object_id' => null,
					'contexts' => array( 'custom-css' => 'updated' ),
				)
			);
		}

		return $data;
	}

	private static function get_settings_def( $key, $value = null ) {
		// Sharing
		if ( 0 === strpos( $key, 'publicize_connections::' ) ) {
			global $publicize_ui;
			$name = str_replace( 'publicize_connections::', '', $key );

			return array(
				'message' => __( '%s connection has been %s', 'stream-connector-jetpack' ),
				'meta'    => array(
					'connection' => $publicize_ui->publicize->get_service_label( $name ),
					'action'     => $value ? __( 'added', 'stream-connector-jetpack' ) : __( 'removed', 'stream-connector-jetpack' ),
					'option'     => 'jetpack_options',
					'option_key' => $key,
				),
				'action'  => $value ? 'added' : 'removed',
				'context' => 'publicize',
			);
		} elseif ( 0 === strpos( $key, 'videopress::' ) ) {
			$name = str_replace( 'videopress::', '', $key );
			$options = array(
				'access' => __( 'Video Library Access', 'jetpack' ),
				'upload' => __( 'Allow users to upload videos', 'jetpack' ),
				'freedom' => __( 'Free formats', 'jetpack' ),
				'hd' => __( 'Default quality', 'jetpack' ),
			);
			if ( ! isset( $options[ $name ] ) ) {
				return false;
			}
			return array(
				'message' => __( '"%s" setting has been updated' ),
				'meta' => array(
					'option_name' => $options[ $name ],
					'option' => 'jetpack_options',
					'option_key' => $key,
				),
				'action' => 'updated',
				'context' => 'videopress',
			);
		}

		return false;
	}

}
