<?php

class WP_Stream_Connector_Jetpack extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'jetpack';

	/**
	 * Holds tracked plugin minimum version required
	 *
	 * @const string
	 */
	const PLUGIN_MIN_VERSION = '3.0.2';

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
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public static function is_dependency_satisfied() {
		if ( class_exists( 'Jetpack' ) && defined( 'JETPACK__VERSION' ) && version_compare( JETPACK__VERSION, self::PLUGIN_MIN_VERSION, '>=' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return _x( 'Jetpack', 'jetpack', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'activated'   => _x( 'Activated', 'jetpack', 'stream' ),
			'deactivated' => _x( 'Dectivated', 'jetpack', 'stream' ),
			'register'    => _x( 'Connected', 'jetpack', 'stream' ),
			'disconnect'  => _x( 'Disconnected', 'jetpack', 'stream' ),
			'authorize'   => _x( 'Link', 'jetpack', 'stream' ),
			'unlink'      => _x( 'Unlink', 'jetpack', 'stream' ),
			'updated'     => _x( 'Updated', 'jetpack', 'stream' ),
			'added'       => _x( 'Added', 'jetpack', 'stream' ),
			'removed'     => _x( 'Removed', 'jetpack', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'modules'            => _x( 'Modules', 'jetpack', 'stream' ),
			'blogs'              => _x( 'Blogs', 'jetpack', 'stream' ),
			'users'              => _x( 'Users', 'jetpack', 'stream' ),
			'options'            => _x( 'Options', 'jetpack', 'stream' ),
			'sharedaddy'         => _x( 'Sharing', 'jetpack', 'stream' ),
			'publicize'          => _x( 'Publicize', 'jetpack', 'stream' ),
			'gplus-authorship'   => _x( 'Google+ Profile', 'jetpack', 'stream' ),
			'stats'              => _x( 'WordPress.com Stats', 'jetpack', 'stream' ),
			'carousel'           => _x( 'Carousel', 'jetpack', 'stream' ),
			'custom-css'         => _x( 'Custom CSS', 'jetpack', 'stream' ),
			'subscriptions'      => _x( 'Subscriptions', 'jetpack', 'stream' ),
			'jetpack-comments'   => _x( 'Comments', 'jetpack', 'stream' ),
			'infinite-scroll'    => _x( 'Infinite Scroll', 'jetpack', 'stream' ),
			'sso'                => _x( 'SSO', 'jetpack', 'stream' ),
			'likes'              => _x( 'Likes', 'jetpack', 'stream' ),
			'minileven'          => _x( 'Mobile', 'jetpack', 'stream' ),
			'monitor'            => _x( 'Monitor', 'jetpack', 'stream' ),
			'post-by-email'      => _x( 'Post by Email', 'jetpack', 'stream' ),
			'related-posts'      => _x( 'Related Posts', 'jetpack', 'stream' ),
			'verification-tools' => _x( 'Site Verification', 'jetpack', 'stream' ),
			'tiled-gallery'      => _x( 'Tiled Galleries', 'jetpack', 'stream' ),
			'videopress'         => _x( 'VideoPress', 'jetpack', 'stream' ),
		);
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
		// @todo provide proper action links
		if ( 'jetpack' === $record->connector ) {
			if ( 'modules' === $record->context ) {
				$slug = wp_stream_get_meta( $record, 'module_slug', true );

				if ( is_array( $slug ) ) {
					$slug = current( $slug );
				}

				if ( Jetpack::is_module_active( $slug ) ) {
					if ( apply_filters( 'jetpack_module_configurable_' . $slug, false ) ) {
						$links[ __( 'Configure', 'stream' ) ] = Jetpack::module_configuration_url( $slug );;
					}

					$links[ __( 'Deactivate', 'stream' ) ] = wp_nonce_url(
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
					$links[ __( 'Activate', 'stream' ) ] = wp_nonce_url(
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
			} elseif ( Jetpack::is_module_active( str_replace( 'jetpack-', '', $record->context ) ) ) {
				$slug = str_replace( 'jetpack-', '', $record->context ); // handling jetpack-comment anomaly

				if ( apply_filters( 'jetpack_module_configurable_' . $slug, false ) ) {
					$links[ __( 'Configure module', 'stream' ) ] = Jetpack::module_configuration_url( $slug );;
				}
			}
		}

		return $links;
	}

	public static function register() {
		parent::register();

		add_filter( 'wp_stream_log_data', array( __CLASS__, 'log_override' ) );

		self::$options = array(
			'jetpack_options'                   => null,
			// Sharing module
			'hide_gplus'                        => null,
			'gplus_authors'                     => null,
			'sharing-options'                   => array(
				'label'   => __( 'Sharing options', 'stream' ),
				'context' => 'sharedaddy',
			),
			'sharedaddy_disable_resources'      => null,
			'jetpack-twitter-cards-site-tag'    => array(
				'label'   => __( 'Twitter site tag', 'stream' ),
				'context' => 'sharedaddy',
			),
			// Stats module
			'stats_options'                     => array(
				'label'   => __( 'WordPress.com Stats', 'stream' ),
				'context' => 'stats',
			),
			// Comments
			'jetpack_comment_form_color_scheme' => array(
				'label'   => __( 'Color Scheme', 'stream' ),
				'context' => 'jetpack-comments',
			),
			// Likes
			'disabled_likes'                    => array(
				'label'   => __( 'WP.com Site-wide Likes', 'stream' ),
				'context' => 'likes',
			),
			// Mobile
			'wp_mobile_excerpt'                 => array(
				'label'   => __( 'Excerpts appearance', 'stream' ),
				'context' => 'minileven',
			),
			'wp_mobile_app_promos'              => array(
				'label'   => __( 'App promos', 'stream' ),
				'context' => 'minileven',
			),
		);

		self::$options_override = array(
			// Carousel Module
			'carousel_background_color'        => array(
				'label'   => __( 'Background color', 'stream' ),
				'context' => 'carousel',
			),
			'carousel_display_exif'            => array(
				'label'   => __( 'Metadata', 'stream' ),
				'context' => 'carousel',
			),
			// Subscriptions
			'stb_enabled'                      => array(
				'label'   => __( 'Follow blog comment form button', 'stream' ),
				'context' => 'subscriptions',
			),
			'stc_enabled'                      => array(
				'label'   => __( 'Follow comments form button', 'stream' ),
				'context' => 'subscriptions',
			),
			// Jetpack comments
			'highlander_comment_form_prompt'   => array(
				'label'   => __( 'Greeting Text', 'stream' ),
				'context' => 'jetpack-comments',
			),
			// Infinite Scroll
			'infinite_scroll_google_analytics' => array(
				'label'   => __( 'Infinite Scroll Google Analytics', 'stream' ),
				'context' => 'infinite-scroll',
			),
			// SSO
			'jetpack_sso_require_two_step'     => array(
				'label'   => __( 'Require Two-Step Authentication', 'stream' ),
				'context' => 'sso',
			),
			'jetpack_sso_match_by_email'       => array(
				'label'   => __( 'Match by Email', 'stream' ),
				'context' => 'sso',
			),
			// Related posts
			'jetpack_relatedposts'             => array(
				'show_headline'   => array(
					'label'   => __( 'Show Related Posts Headline', 'stream' ),
					'context' => 'related-posts',
				),
				'show_thumbnails' => array(
					'label'   => __( 'Show Related Posts Thumbnails', 'stream' ),
					'context' => 'related-posts',
				),
			),
			// Site verification
			'verification_services_codes'      => array(
				'google'    => array(
					'label'   => __( 'Google Webmaster Tools Token', 'stream' ),
					'context' => 'verification-tools',
				),
				'bing'      => array(
					'label'   => __( 'Bing Webmaster Center Token', 'stream' ),
					'context' => 'verification-tools',
				),
				'pinterest' => array(
					'label'   => __( 'Pinterest Site Verification Token', 'stream' ),
					'context' => 'verification-tools',
				),
			),
			// Tiled galleries
			'tiled_galleries'                  => array(
				'label'   => __( 'Tiled Galleries', 'stream' ),
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
				__( '%1$s module %2$s', 'stream' ),
				$module_name,
				( 'activated' === $action ) ? __( 'activated', 'stream' ) : __( 'deactivated', 'stream' )
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
				__( '%1$s\'s account %2$s %3$s Jetpack', 'stream' ),
				$user->display_name,
				( 'unlink' === $action ) ? __( 'unlinked', 'stream' ) : __( 'linked', 'stream' ),
				( 'unlink' === $action ) ? __( 'from', 'stream' ) : __( 'to', 'stream' )
			);
		} elseif ( in_array( $method, array( 'register', 'disconnect', 'subsiteregister', 'subsitedisconnect' ) ) ) {
			$context      = 'blogs';
			$action       = str_replace( 'subsite', '', $method );
			$is_multisite = ( 0 === strpos( $method, 'subsite' ) );
			$blog_id      = $is_multisite ? ( isset( $_GET['site_id'] ) ? intval( $_GET['site_id'] ) : null ) : get_current_blog_id();

			if ( empty( $blog_id ) ) {
				return;
			}

			$meta = array();

			if ( ! $is_multisite ) {
				$message = sprintf(
					__( 'Site %s Jetpack', 'stream' ),
					( 'register' === $action ) ? __( 'connected to', 'stream' ) : __( 'disconnected from', 'stream' )
				);
			} else {
				$blog_details = get_blog_details( array( 'blog_id' => $blog_id ) );
				$blog_name    = $blog_details->blogname;
				$meta        += compact( 'blog_id', 'blog_name' );

				$message = sprintf(
					__( '"%1$s" blog %2$s Jetpack', 'stream' ),
					$blog_name,
					( 'register' === $action ) ? __( 'connected to', 'stream' ) : __( 'disconnected from', 'stream' )
				);
			}
		}

		if ( empty( $message ) ) {
			return;
		}

		self::log(
			$message,
			$meta,
			null,
			$context,
			$action
		);
	}

	/**
	 * Track visible/enabled sharing services ( buttons )
	 *
	 * @param $state
	 */
	public static function callback_sharing_get_services_state( $state ) {
		self::log(
			__( 'Sharing services updated', 'stream' ),
			$state,
			null,
			'sharedaddy',
			'updated'
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
			$active = wp_stream_filter_input( INPUT_POST, 'receive_jetpack_monitor_notification' );

			self::log(
				__( 'Monitor notifications %s', 'stream' ),
				array(
					'status'    => $active ? __( 'activated', 'stream' ) : __( 'deactivated', 'stream' ),
					'option'    => 'receive_jetpack_monitor_notification',
					'old_value' => ! $active,
					'value'     => $active,
				),
				null,
				'monitor',
				'updated'
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
			$action = __( 'enabled', 'stream' );
		} elseif ( false === $status ) {
			$action = __( 'disabled', 'stream' );
		} elseif ( null === $status ) {
			$action = __( 'regenerated', 'stream' );
		}

		$user = wp_get_current_user();

		self::log(
			__( '%1$s %2$s Post by Email', 'stream' ),
			array(
				'user_displayname' => $user->display_name,
				'action'           => $action,
				'status'           => $status,
			),
			null,
			'post-by-email',
			'updated'
		);
	}

	public static function check( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, self::$options ) ) {
			return;
		}

		if ( is_null( self::$options[ $option ] ) ) {
			call_user_func( array( __CLASS__, 'check_' . str_replace( '-', '_', $option ) ), $old_value, $new_value );
		} else {
			$data         = self::$options[ $option ];
			$option_title = $data['label'];

			self::log(
				__( '"%s" setting updated', 'stream' ),
				compact( 'option_title', 'option', 'old_value', 'new_value' ),
				null,
				$data['context'],
				isset( $data['action'] ) ? $data['action'] : 'updated'
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

			if ( 0 === $option_value ) { // Skip updated array with updated members, we'll be logging those instead
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
				$settings['context'],
				$settings['action']
			);
		}
	}

	public static function check_hide_gplus( $old_value, $new_value ) {
		$status = ! is_null( $new_value );

		if ( $status && $old_value ) {
			return false;
		}

		self::log(
			__( 'G+ profile display %s', 'stream' ),
			array(
				'action' => $status ? __( 'enabled', 'stream' ) : __( 'disabled', 'stream' ),
			),
			null,
			'gplus-authorship',
			'updated'
		);
	}

	public static function check_gplus_authors( $old_value, $new_value ) {
		$user      = wp_get_current_user();
		$connected = is_array( $new_value ) && array_key_exists( $user->ID, $new_value );

		self::log(
			__( '%1$s\'s Google+ account %2$s', 'stream' ),
			array(
				'display_name' => $user->display_name,
				'action'       => $connected ? __( 'connected', 'stream' ) : __( 'disconnected', 'stream' ),
				'user_id'      => $user->ID,
			),
			$user->ID,
			'gplus-authorship',
			'updated'
		);
	}

	public static function check_sharedaddy_disable_resources( $old_value, $new_value ) {
		if ( $old_value == $new_value ) {
			return;
		}

		$status = ! $new_value ? 'enabled' : 'disabled'; // disabled = 1

		self::log(
			__( 'Sharing CSS/JS %s', 'stream' ),
			compact( 'status', 'old_value', 'new_value' ),
			null,
			'sharing',
			'updated'
		);
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public static function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		// Handling our Settings
		if ( 'settings' === $data['connector'] && isset( self::$options_override[ $data['args']['option'] ] ) ) {
			if ( isset( $data['args']['option_key'] ) ) {
				$overrides = self::$options_override[ $data['args']['option'] ][ $data['args']['option_key'] ];
			} else {
				$overrides = self::$options_override[ $data['args']['option'] ];
			}

			if ( isset( $overrides ) ) {
				$data['args']['label']   = $overrides['label'];
				$data['args']['context'] = $overrides['context'];
				$data['context']         = $overrides['context'];
				$data['connector']       = self::$name;
			}
		} elseif ( 'posts' === $data['connector'] && 'safecss' === $data['context'] ) {
			$data = array_merge(
				$data,
				array(
					'connector' => self::$name,
					'message'   => __( 'Custom CSS updated', 'stream' ),
					'args'      => array(),
					'object_id' => null,
					'context'   => 'custom-css',
					'action'    => 'updated',
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
				'message' => __( '%1$s connection %2$s', 'stream' ),
				'meta'    => array(
					'connection' => $publicize_ui->publicize->get_service_label( $name ),
					'action'     => $value ? __( 'added', 'stream' ) : __( 'removed', 'stream' ),
					'option'     => 'jetpack_options',
					'option_key' => $key,
				),
				'action'  => $value ? 'added' : 'removed',
				'context' => 'publicize',
			);
		} elseif ( 0 === strpos( $key, 'videopress::' ) ) {
			$name    = str_replace( 'videopress::', '', $key );
			$options = array(
				'access'  => __( 'Video Library Access', 'stream' ),
				'upload'  => __( 'Allow users to upload videos', 'stream' ),
				'freedom' => __( 'Free formats', 'stream' ),
				'hd'      => __( 'Default quality', 'stream' ),
			);

			if ( ! isset( $options[ $name ] ) ) {
				return false;
			}

			return array(
				'message' => __( '"%s" setting updated', 'stream' ),
				'meta'    => array(
					'option_name' => $options[ $name ],
					'option'      => 'jetpack_options',
					'option_key'  => $key,
				),
				'action'  => 'updated',
				'context' => 'videopress',
			);
		}

		return false;
	}

}
