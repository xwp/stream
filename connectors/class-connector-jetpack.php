<?php
/**
 * Connector for Jetpack
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_Jetpack
 */
class Connector_Jetpack extends Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'jetpack';

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
	public $actions = array(
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
	 * Register connector in the WP Frontend
	 *
	 * @var bool
	 */
	public $register_frontend = false;

	/**
	 * Tracked option keys
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * Tracking registered Settings, with overridden data
	 *
	 * @var array
	 */
	public $options_override = array();

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
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
	public function get_label() {
		return esc_html_x( 'Jetpack', 'jetpack', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'activated'   => esc_html_x( 'Activated', 'jetpack', 'stream' ),
			'deactivated' => esc_html_x( 'Deactivated', 'jetpack', 'stream' ),
			'register'    => esc_html_x( 'Connected', 'jetpack', 'stream' ),
			'disconnect'  => esc_html_x( 'Disconnected', 'jetpack', 'stream' ),
			'authorize'   => esc_html_x( 'Link', 'jetpack', 'stream' ),
			'unlink'      => esc_html_x( 'Unlink', 'jetpack', 'stream' ),
			'updated'     => esc_html_x( 'Updated', 'jetpack', 'stream' ),
			'added'       => esc_html_x( 'Added', 'jetpack', 'stream' ),
			'removed'     => esc_html_x( 'Removed', 'jetpack', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'blogs'              => esc_html_x( 'Blogs', 'jetpack', 'stream' ),
			'carousel'           => esc_html_x( 'Carousel', 'jetpack', 'stream' ),
			'custom-css'         => esc_html_x( 'Custom CSS', 'jetpack', 'stream' ),
			'gplus-authorship'   => esc_html_x( 'Google+ Profile', 'jetpack', 'stream' ),
			'infinite-scroll'    => esc_html_x( 'Infinite Scroll', 'jetpack', 'stream' ),
			'jetpack-comments'   => esc_html_x( 'Comments', 'jetpack', 'stream' ),
			'likes'              => esc_html_x( 'Likes', 'jetpack', 'stream' ),
			'minileven'          => esc_html_x( 'Mobile', 'jetpack', 'stream' ),
			'modules'            => esc_html_x( 'Modules', 'jetpack', 'stream' ),
			'monitor'            => esc_html_x( 'Monitor', 'jetpack', 'stream' ),
			'options'            => esc_html_x( 'Options', 'jetpack', 'stream' ),
			'post-by-email'      => esc_html_x( 'Post by Email', 'jetpack', 'stream' ),
			'protect'            => esc_html_x( 'Protect', 'jetpack', 'stream' ),
			'publicize'          => esc_html_x( 'Publicize', 'jetpack', 'stream' ),
			'related-posts'      => esc_html_x( 'Related Posts', 'jetpack', 'stream' ),
			'sharedaddy'         => esc_html_x( 'Sharing', 'jetpack', 'stream' ),
			'subscriptions'      => esc_html_x( 'Subscriptions', 'jetpack', 'stream' ),
			'sso'                => esc_html_x( 'SSO', 'jetpack', 'stream' ),
			'stats'              => esc_html_x( 'WordPress.com Stats', 'jetpack', 'stream' ),
			'tiled-gallery'      => esc_html_x( 'Tiled Galleries', 'jetpack', 'stream' ),
			'users'              => esc_html_x( 'Users', 'jetpack', 'stream' ),
			'verification-tools' => esc_html_x( 'Site Verification', 'jetpack', 'stream' ),
			'videopress'         => esc_html_x( 'VideoPress', 'jetpack', 'stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param array  $links   Previous links registered.
	 * @param object $record  Stream record.
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		// @todo provide proper action links
		if ( 'jetpack' === $record->connector ) {
			if ( 'modules' === $record->context ) {
				$slug = $record->get_meta( 'module_slug', true );

				if ( is_array( $slug ) ) {
					$slug = current( $slug );
				}

				if ( \Jetpack::is_module_active( $slug ) ) {
					if ( apply_filters( 'jetpack_module_configurable_' . $slug, false ) ) {
						$links[ esc_html__( 'Configure', 'stream' ) ] = \Jetpack::module_configuration_url( $slug );
					}

					$links[ esc_html__( 'Deactivate', 'stream' ) ] = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'deactivate',
								'module' => $slug,
							),
							\Jetpack::admin_url()
						),
						'jetpack_deactivate-' . sanitize_title( $slug )
					);
				} else {
					$links[ esc_html__( 'Activate', 'stream' ) ] = wp_nonce_url(
						add_query_arg(
							array(
								'action' => 'activate',
								'module' => $slug,
							),
							\Jetpack::admin_url()
						),
						'jetpack_activate-' . sanitize_title( $slug )
					);
				}
			} elseif ( \Jetpack::is_module_active( str_replace( 'jetpack-', '', $record->context ) ) ) {
				$slug = str_replace( 'jetpack-', '', $record->context ); // handling jetpack-comment anomaly.

				if ( apply_filters( 'jetpack_module_configurable_' . $slug, false ) ) {
					$links[ esc_html__( 'Configure module', 'stream' ) ] = \Jetpack::module_configuration_url( $slug );
				}
			}
		}

		return $links;
	}

	/**
	 * Register all context hooks
	 */
	public function register() {
		parent::register();

		add_filter( 'wp_stream_log_data', array( $this, 'log_override' ) );

		$this->options = array(
			'jetpack_options'                   => null,
			// Sharing module.
			'hide_gplus'                        => null,
			'gplus_authors'                     => null,
			'sharing-options'                   => array(
				'label'   => esc_html__( 'Sharing options', 'stream' ),
				'context' => 'sharedaddy',
			),
			'sharedaddy_disable_resources'      => null,
			'jetpack-twitter-cards-site-tag'    => array(
				'label'   => esc_html__( 'Twitter site tag', 'stream' ),
				'context' => 'sharedaddy',
			),
			// Stats module.
			'stats_options'                     => array(
				'label'   => esc_html__( 'WordPress.com Stats', 'stream' ),
				'context' => 'stats',
			),
			// Comments.
			'jetpack_comment_form_color_scheme' => array(
				'label'   => esc_html__( 'Color Scheme', 'stream' ),
				'context' => 'jetpack-comments',
			),
			// Likes.
			'disabled_likes'                    => array(
				'label'   => esc_html__( 'WP.com Site-wide Likes', 'stream' ),
				'context' => 'likes',
			),
			// Mobile.
			'wp_mobile_excerpt'                 => array(
				'label'   => esc_html__( 'Excerpts appearance', 'stream' ),
				'context' => 'minileven',
			),
			'wp_mobile_app_promos'              => array(
				'label'   => esc_html__( 'App promos', 'stream' ),
				'context' => 'minileven',
			),
		);

		$this->options_override = array(
			// Carousel Module.
			'carousel_background_color'        => array(
				'label'   => esc_html__( 'Background color', 'stream' ),
				'context' => 'carousel',
			),
			'carousel_display_exif'            => array(
				'label'   => esc_html__( 'Metadata', 'stream' ),
				'context' => 'carousel',
			),
			// Subscriptions.
			'stb_enabled'                      => array(
				'label'   => esc_html__( 'Follow blog comment form button', 'stream' ),
				'context' => 'subscriptions',
			),
			'stc_enabled'                      => array(
				'label'   => esc_html__( 'Follow comments form button', 'stream' ),
				'context' => 'subscriptions',
			),
			// Jetpack comments.
			'highlander_comment_form_prompt'   => array(
				'label'   => esc_html__( 'Greeting Text', 'stream' ),
				'context' => 'jetpack-comments',
			),
			// Infinite Scroll.
			'infinite_scroll_google_analytics' => array(
				'label'   => esc_html__( 'Infinite Scroll Google Analytics', 'stream' ),
				'context' => 'infinite-scroll',
			),
			// Protect.
			'jetpack_protect_blocked_attempts' => array(
				'label'   => esc_html__( 'Blocked Attempts', 'stream' ),
				'context' => 'protect',
			),
			// SSO.
			'jetpack_sso_require_two_step'     => array(
				'label'   => esc_html__( 'Require Two-Step Authentication', 'stream' ),
				'context' => 'sso',
			),
			'jetpack_sso_match_by_email'       => array(
				'label'   => esc_html__( 'Match by Email', 'stream' ),
				'context' => 'sso',
			),
			// Related posts.
			'jetpack_relatedposts'             => array(
				'show_headline'   => array(
					'label'   => esc_html__( 'Show Related Posts Headline', 'stream' ),
					'context' => 'related-posts',
				),
				'show_thumbnails' => array(
					'label'   => esc_html__( 'Show Related Posts Thumbnails', 'stream' ),
					'context' => 'related-posts',
				),
			),
			// Site verification.
			'verification_services_codes'      => array(
				'google'    => array(
					'label'   => esc_html__( 'Google Webmaster Tools Token', 'stream' ),
					'context' => 'verification-tools',
				),
				'bing'      => array(
					'label'   => esc_html__( 'Bing Webmaster Center Token', 'stream' ),
					'context' => 'verification-tools',
				),
				'pinterest' => array(
					'label'   => esc_html__( 'Pinterest Site Verification Token', 'stream' ),
					'context' => 'verification-tools',
				),
			),
			// Tiled galleries.
			'tiled_galleries'                  => array(
				'label'   => esc_html__( 'Tiled Galleries', 'stream' ),
				'context' => 'tiled-gallery',
			),
			// Monitor.
			'monitor_receive_notification'     => array(
				'label'   => esc_html__( 'Monitor notifications', 'stream' ),
				'context' => 'monitor',
			),
		);
	}

	/**
	 * Tracks logs add to Jetpack logging.
	 * Includes:
	 * - Activation/Deactivation of modules
	 * - Registration/Disconnection of blogs
	 * - Authorization/unlinking of users
	 *
	 * @param array $entry  Entry data.
	 */
	public function callback_jetpack_log_entry( array $entry ) {
		if ( isset( $entry['code'] ) ) {
			$method = $entry['code'];
		} else {
			return;
		}

		if ( isset( $entry['data'] ) ) {
			$data = $entry['data'];
		} else {
			$data = null;
		}

		$context = null;
		$action  = null;
		$meta    = array();

		if ( in_array( $method, array( 'activate', 'deactivate' ), true ) && ! is_null( $data ) ) {
			$module_slug = $data;
			$module      = \Jetpack::get_module( $module_slug );
			$module_name = $module['name'];
			$context     = 'modules';
			$action      = $method . 'd';
			$meta        = compact( 'module_slug' );
			$message     = sprintf(
				/* translators: %1$s: a module name, %2$s a status (e.g. "Photon", "activated") */
				__( '%1$s module %2$s', 'stream' ),
				$module_name,
				( 'activated' === $action ) ? esc_html__( 'activated', 'stream' ) : esc_html__( 'deactivated', 'stream' )
			);
		} elseif ( in_array( $method, array( 'authorize', 'unlink' ), true ) && ! is_null( $data ) ) {
			$user_id = intval( $data );

			if ( empty( $user_id ) ) {
				$user_id = get_current_user_id();
			}

			$user       = new \WP_User( $user_id );
			$user_email = $user->user_email;
			$user_login = $user->user_login;
			$context    = 'users';
			$action     = $method;
			$meta       = compact( 'user_id', 'user_email', 'user_login' );
			$message    = sprintf(
				/* translators: %1$s: a user display name, %2$s: a status and the connection either "from" or "to" (e.g. "Jane Doe", "unlinked from") */
				__( '%1$s\'s account %2$s Jetpack', 'stream' ),
				$user->display_name,
				( 'unlink' === $action ) ? esc_html__( 'unlinked from', 'stream' ) : esc_html__( 'linked to', 'stream' )
			);
		} elseif ( in_array( $method, array( 'register', 'disconnect', 'subsiteregister', 'subsitedisconnect' ), true ) ) {
			$context      = 'blogs';
			$action       = str_replace( 'subsite', '', $method );
			$is_multisite = ( 0 === strpos( $method, 'subsite' ) );
			// @codingStandardsIgnoreLine
			$blog_id      = $is_multisite ? ( isset( $_GET['site_id'] ) ? intval( wp_unslash( $_GET['site_id'] ) ) : null ) : get_current_blog_id();

			if ( empty( $blog_id ) ) {
				return;
			}

			if ( ! $is_multisite ) {
				$message = sprintf(
					/* translators: %s: a connection status. Either "connected to" or "disconnected from". */
					__( 'Site %s Jetpack', 'stream' ),
					( 'register' === $action ) ? esc_html__( 'connected to', 'stream' ) : esc_html__( 'disconnected from', 'stream' )
				);
			} else {
				$blog_details = get_blog_details(
					array(
						'blog_id' => $blog_id,
					)
				);
				$blog_name    = $blog_details->blogname;
				$meta        += compact( 'blog_id', 'blog_name' );

				$message = sprintf(
					/* translators: %1$s: Blog name, %2$s: a connection status. Either "connected to" or "disconnected from". */
					__( '"%1$s" blog %2$s Jetpack', 'stream' ),
					$blog_name,
					( 'register' === $action ) ? esc_html__( 'connected to', 'stream' ) : esc_html__( 'disconnected from', 'stream' )
				);
			}
		}

		if ( empty( $message ) ) {
			return;
		}

		$this->log(
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
	 * @param string $state  Service state.
	 */
	public function callback_sharing_get_services_state( $state ) {
		$this->log(
			__( 'Sharing services updated', 'stream' ),
			$state,
			null,
			'sharedaddy',
			'updated'
		);
	}

	/**
	 * Track Jetpack-specific option changes.
	 *
	 * @param string $option Option key.
	 * @param string $old    Old value.
	 * @param string $new    New value.
	 */
	public function callback_update_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

	/**
	 * Track Jetpack-specific option creations.
	 *
	 * @param string $option Option key.
	 * @param string $val    Value.
	 */
	public function callback_add_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

	/**
	 * Track Jetpack-specific option deletions.
	 *
	 * @param string $option Option key.
	 */
	public function callback_delete_option( $option ) {
		$this->check( $option, null, null );
	}

	/**
	 * Track Monitor module notification status
	 */
	public function callback_jetpack_module_configuration_load_monitor() {
		$active = wp_stream_filter_input( INPUT_POST, 'receive_jetpack_monitor_notification' );

		if ( ! $active ) {
			return;
		}

		$this->log(
			/* translators: %s: a status (e.g. "activated") */
			__( 'Monitor notifications %s', 'stream' ),
			array(
				'status'    => $active ? esc_html__( 'activated', 'stream' ) : esc_html__( 'deactivated', 'stream' ),
				'option'    => 'receive_jetpack_monitor_notification',
				'old_value' => ! $active,
				'value'     => $active,
			),
			null,
			'monitor',
			'updated'
		);
	}

	/**
	 * Logs when user enables "post_by_email"
	 *
	 * @action wp_ajax_jetpack_post_by_email_enable
	 */
	public function callback_wp_ajax_jetpack_post_by_email_enable() {
		$this->track_post_by_email( true );
	}

	/**
	 * Logs when user regenerates "post_by_email"
	 *
	 * @action wp_ajax_jetpack_post_by_email_regenerate
	 */
	public function callback_wp_ajax_jetpack_post_by_email_regenerate() {
		$this->track_post_by_email( null );
	}

	/**
	 * Logs when user disables "post_by_email"
	 *
	 * @action wp_ajax_jetpack_post_by_email_disable
	 */
	public function callback_wp_ajax_jetpack_post_by_email_disable() {
		$this->track_post_by_email( false );
	}

	/**
	 * Tracks changes a user post by email status
	 *
	 * @param string $status Status.
	 * @return void
	 */
	public function track_post_by_email( $status ) {
		if ( true === $status ) {
			$action = esc_html__( 'enabled', 'stream' );
		} elseif ( false === $status ) {
			$action = esc_html__( 'disabled', 'stream' );
		} elseif ( null === $status ) {
			$action = esc_html__( 'regenerated', 'stream' );
		}

		$user = wp_get_current_user();

		$this->log(
			/* translators: %1$s: a user display name, %2$s: a status (e.g. "Jane Doe", "enabled") */
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

	/**
	 * Tracks Jetpack-specific option activity.
	 *
	 * @param string $option     Option key.
	 * @param string $old_value  Old value.
	 * @param string $new_value  New value.
	 */
	public function check( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, $this->options ) ) {
			return;
		}

		if ( is_null( $this->options[ $option ] ) ) {
			call_user_func( array( $this, 'check_' . str_replace( '-', '_', $option ) ), $old_value, $new_value );
		} else {
			$data         = $this->options[ $option ];
			$option_title = $data['label'];

			$this->log(
				/* translators: %s: a setting name (e.g. "Language") */
				__( '"%s" setting updated', 'stream' ),
				compact( 'option_title', 'option', 'old_value', 'new_value' ),
				null,
				$data['context'],
				isset( $data['action'] ) ? $data['action'] : 'updated'
			);
		}
	}

	/**
	 * Track Jetpack-specific option activity.
	 *
	 * @param string $old_value  Old value.
	 * @param string $new_value  New value.
	 */
	public function check_jetpack_options( $old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( $this->get_changed_keys( $old_value, $new_value, 1 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		foreach ( $options as $option => $option_value ) {
			$settings = $this->get_settings_def( $option, $option_value );

			if ( ! $settings ) {
				continue;
			}

			if ( 0 === $option_value ) { // Skip updated array with updated members, we'll be logging those instead.
				continue;
			}

			$settings['meta'] += array(
				'option'    => $option,
				'old_value' => $old_value,
				'value'     => $new_value,
			);

			$this->log(
				$settings['message'],
				$settings['meta'],
				isset( $settings['object_id'] ) ? $settings['object_id'] : null,
				$settings['context'],
				$settings['action']
			);
		}
	}

	/**
	 * Logs Google+ profile display status
	 *
	 * @param string $old_value  Old status.
	 * @param string $new_value  New status.
	 * @return null|bool
	 */
	public function check_hide_gplus( $old_value, $new_value ) {
		$status = ! is_null( $new_value );

		if ( $status && $old_value ) {
			return false;
		}

		$this->log(
			/* translators: Placeholder refers to a status (e.g. "enabled") */
			__( 'G+ profile display %s', 'stream' ),
			array(
				'action' => $status ? esc_html__( 'enabled', 'stream' ) : esc_html__( 'disabled', 'stream' ),
			),
			null,
			'gplus-authorship',
			'updated'
		);
	}

	/**
	 * Logs if current user's Google+ account connection status
	 *
	 * @param string $old_value  Old status.
	 * @param string $new_value  New status.
	 * @return void
	 */
	public function check_gplus_authors( $old_value, $new_value ) {
		unset( $old_value );

		$user      = wp_get_current_user();
		$connected = is_array( $new_value ) && array_key_exists( $user->ID, $new_value );

		$this->log(
			/* translators: %1$s: a user display name, %2$s: a status (e.g. "Jane Doe", "connected") */
			__( '%1$s\'s Google+ account %2$s', 'stream' ),
			array(
				'display_name' => $user->display_name,
				'action'       => $connected ? esc_html__( 'connected', 'stream' ) : esc_html__( 'disconnected', 'stream' ),
				'user_id'      => $user->ID,
			),
			$user->ID,
			'gplus-authorship',
			'updated'
		);
	}

	/**
	 * Logs sharedaddy resource status.
	 *
	 * @param string $old_value  Old status.
	 * @param string $new_value  New status.
	 * @return void
	 */
	public function check_sharedaddy_disable_resources( $old_value, $new_value ) {
		if ( $old_value === $new_value ) {
			return;
		}

		$status = ! $new_value ? 'enabled' : 'disabled'; // disabled = 1.

		$this->log(
			/* translators: %s: a status (e.g. "enabled") */
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
	 * @param array $data  Record data.
	 *
	 * @return array|bool
	 */
	public function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		// Handling our Settings.
		if ( 'settings' === $data['connector'] && isset( $this->options_override[ $data['args']['option'] ] ) ) {
			if ( isset( $data['args']['option_key'] ) ) {
				$overrides = $this->options_override[ $data['args']['option'] ][ $data['args']['option_key'] ];
			} else {
				$overrides = $this->options_override[ $data['args']['option'] ];
			}

			if ( isset( $overrides ) ) {
				$data['args']['label']   = $overrides['label'];
				$data['args']['context'] = $overrides['context'];
				$data['context']         = $overrides['context'];
				$data['connector']       = $this->name;
			}
		} elseif ( 'posts' === $data['connector'] && 'safecss' === $data['context'] ) {
			$data = array_merge(
				$data,
				array(
					'connector' => $this->name,
					'message'   => esc_html__( 'Custom CSS updated', 'stream' ),
					'args'      => array(),
					'object_id' => null,
					'context'   => 'custom-css',
					'action'    => 'updated',
				)
			);
		}

		return $data;
	}

	/**
	 * Returns an option's status
	 *
	 * @param string $key    Option key.
	 * @param string $value  Option value.
	 */
	private function get_settings_def( $key, $value = null ) {
		// Sharing.
		if ( 0 === strpos( $key, 'publicize_connections::' ) ) {
			global $publicize_ui;

			$name = str_replace( 'publicize_connections::', '', $key );

			return array(
				/* translators: %1$s: a service, %2$s: a status (e.g. "Facebook", "added") */
				'message' => esc_html__( '%1$s connection %2$s', 'stream' ),
				'meta'    => array(
					'connection' => $publicize_ui->publicize->get_service_label( $name ),
					'action'     => $value ? esc_html__( 'added', 'stream' ) : esc_html__( 'removed', 'stream' ),
					'option'     => 'jetpack_options',
					'option_key' => $key,
				),
				'action'  => $value ? 'added' : 'removed',
				'context' => 'publicize',
			);
		} elseif ( 0 === strpos( $key, 'videopress::' ) ) {
			$name    = str_replace( 'videopress::', '', $key );
			$options = array(
				'access'  => esc_html__( 'Video Library Access', 'stream' ),
				'upload'  => esc_html__( 'Allow users to upload videos', 'stream' ),
				'freedom' => esc_html__( 'Free formats', 'stream' ),
				'hd'      => esc_html__( 'Default quality', 'stream' ),
			);

			if ( ! isset( $options[ $name ] ) ) {
				return false;
			}

			return array(
				/* translators: %s: a setting name (e.g. "Language") */
				'message' => esc_html__( '"%s" setting updated', 'stream' ),
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
