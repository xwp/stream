<?php

class WP_Stream_Connector_BuddyPress extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'buddypress';

	/**
	 * Holds tracked plugin minimum version required
	 *
	 * @const string
	 */
	const PLUGIN_MIN_VERSION = '2.0.1';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'update_option',
		'add_option',
		'delete_option',
		'update_site_option',
		'add_site_option',
		'delete_site_option',
		'add_user_meta',
		'update_user_meta',
		'delete_user_meta',
	);

	/**
	 * Tracked option keys
	 *
	 * @var array
	 */
	public static $options = array(
		'bp-active-components' => null,
		'bp-pages'             => null,
		'buddypress'           => null,
	);

	/**
	 * Tracking registered Settings, with overridden data
	 *
	 * @var array
	 */
	public static $options_override = array();

	/**
	 * Tracking user meta updates related to this connector
	 *
	 * @var array
	 */
	public static $user_meta = array();

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public static function is_dependency_satisfied() {
		if ( ! class_exists( 'BuddyPress' ) ) {
			//WP_Stream::notice(
			//	sprintf( __( '<strong>Stream EDD Connector</strong> requires the <a href="%1$s" target="_blank">EDD</a> plugin to be installed and activated.', 'stream' ), esc_url( 'https://easydigitaldownloads.com' ) ),
			//	true
			//);
		} elseif ( version_compare( BuddyPress::instance()->version, self::PLUGIN_MIN_VERSION, '<' ) ) {
			//WP_Stream::notice(
			//	sprintf( __( 'Please <a href="%1$s" target="_blank">install EDD</a> version %2$s or higher for the <strong>Stream EDD Connector</strong> plugin to work properly.', 'stream' ), esc_url( 'https://easydigitaldownloads.com' ), self::PLUGIN_MIN_VERSION ),
			//	true
			//);
		} else {
			return true;
		}
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return __( 'BuddyPress', 'buddypress' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created'     => __( 'Created', 'stream' ),
			'updated'     => __( 'Updated', 'stream' ),
			'activated'   => __( 'Activated', 'stream' ),
			'deactivated' => __( 'Deactivated', 'stream' ),
			'deleted'     => __( 'Deleted', 'stream' ),
			'trashed'     => __( 'Trashed', 'stream' ),
			'restored'    => __( 'Restored', 'stream' ),
			'generated'   => __( 'Generated', 'stream' ),
			'imported'    => __( 'Imported', 'stream' ),
			'exported'    => __( 'Exported', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'components' => __( 'Components', 'stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array  $links  Previous links registered
	 * @param  object $record Stream record
	 *
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		if ( in_array( $record->context, array( 'components' ) ) ) {
			$option_key = wp_stream_get_meta( $record->ID, 'option_key', true );

			if ( 'bp-active-components' === $option_key ) {
				$links[ __( 'Edit', 'stream' ) ] = add_query_arg(
					array(
						'page' => 'bp-components',
					),
					admin_url( 'admin.php' )
				);
			} elseif ( 'bp-pages' === $option_key ) {
				$page_id = wp_stream_get_meta( $record->ID, 'page_id', true );

				$links[ __( 'Edit setting', 'stream' ) ] = add_query_arg(
					array(
						'page' => 'bp-page-settings',
					),
					admin_url( 'admin.php' )
				);

				if ( $page_id ) {
					$links[ __( 'Edit Page', 'stream' ) ] = get_edit_post_link( $page_id );
					$links[ __( 'View', 'stream' ) ]      = get_permalink( $page_id );
				}
			}
		} elseif ( in_array( $record->context, array( 'settings' ) ) ) {
			$links[ __( 'Edit setting', 'stream' ) ] = add_query_arg(
				array(
					'page' => wp_stream_get_meta( $record->ID, 'page', true ),
				),
				admin_url( 'admin.php' )
			);
		}

		return $links;
	}

	public static function register() {
		parent::register();

		add_filter( 'wp_stream_log_data', array( __CLASS__, 'log_override' ) );

		self::$options = array_merge(
			self::$options,
			array(
				'hide-loggedout-adminbar'       => array(
					'label' => __( 'Toolbar', 'buddypress' ),
					'page'  => 'bp-settings',
				),
				'_bp_force_buddybar'            => array(
					'label' => __( 'Toolbar', 'buddypress' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-account-deletion'   => array(
					'label' => __( 'Account Deletion', 'buddypress' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-profile-sync'       => array(
					'label' => __( 'Profile Syncing', 'buddypress' ),
					'page'  => 'bp-settings',
				),
				'bp_restrict_group_creation'    => array(
					'label' => __( 'Group Creation', 'buddypress' ),
					'page'  => 'bp-settings',
				),
				'bb-config-location'            => array(
					'label' => __( 'bbPress Configuration', 'buddypress' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-blogforum-comments' => array(
					'label' => __( 'Blog &amp; Forum Comments', 'buddypress' ),
					'page'  => 'bp-settings',
				),
				'_bp_enable_heartbeat_refresh'  => array(
					'label' => __( 'Activity auto-refresh', 'buddypress' ),
					'page'  => 'bp-settings',
				),
				'_bp_enable_akismet'            => array(
					'label' => __( 'Akismet', 'buddypress' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-avatar-uploads'     => array(
					'label' => __( 'Avatar Uploads', 'buddypress' ),
					'page'  => 'bp-settings',
				),
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

	public static function callback_update_site_option( $option, $old, $new ) {
		self::check( $option, $old, $new );
	}

	public static function callback_add_site_option( $option, $val ) {
		self::check( $option, null, $val );
	}

	public static function callback_delete_site_option( $option ) {
		self::check( $option, null, null );
	}

	public static function check( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, self::$options ) ) {
			return;
		}

		$replacement = str_replace( '-', '_', $option );

		if ( method_exists( __CLASS__, 'check_' . $replacement ) ) {
			call_user_func( array( __CLASS__, 'check_' . $replacement ), $old_value, $new_value );
		} else {
			$data         = self::$options[ $option ];
			$option_title = $data['label'];
			$context      = isset( $data['context'] ) ? $data['context'] : 'settings';
			$page         = isset( $data['page'] ) ? $data['page'] : null;

			self::log(
				__( '"%s" setting was updated', 'stream' ),
				compact( 'option_title', 'option', 'old_value', 'new_value', 'page' ),
				null,
				array(
					$context => isset( $data['action'] ) ? $data['action'] : 'updated',
				)
			);
		}
	}

	public static function check_bp_active_components( $old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( self::get_changed_keys( $old_value, $new_value, 0 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		$components = bp_core_admin_get_components();

		$actions = array(
			true  => __( 'activated', 'stream' ),
			false => __( 'deactivated', 'stream' ),
		);

		foreach ( $options as $option => $option_value ) {
			if ( ! isset( $components[ $option ], $actions[ $option_value ] ) ) {
				continue;
			}

			self::log(
				sprintf(
					__( '"%s" component was %s', 'stream' ),
					$components[ $option ]['title'],
					$actions[ $option_value ]
				),
				array(
					'option'     => $option,
					'option_key' => 'bp-active-components',
					'old_value'  => maybe_serialize( $old_value ),
					'value'      => maybe_serialize( $new_value ),
				),
				null,
				array(
					'components' => $option_value ? 'activated' : 'deactivated',
				)
			);
		}
	}

	public static function check_bp_pages( $old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( self::get_changed_keys( $old_value, $new_value, 0 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		$pages = array_merge(
			self::bp_get_directory_pages(),
			array(
				'register' => __( 'Register', 'buddypress' ),
				'activate' => __( 'Activate', 'buddypress' ),
			)
		);

		foreach ( $options as $option => $option_value ) {
			if ( ! isset( $pages[ $option ] ) ) {
				continue;
			}

			$page = ! empty( $new_value[ $option ] ) ? get_post( $new_value[ $option ] )->post_title : __( 'No page', 'stream' );

			self::log(
				sprintf(
					__( '"%s" page was set to "%s"', 'stream' ),
					$pages[ $option ],
					$page
				),
				array(
					'option'     => $option,
					'option_key' => 'bp-pages',
					'old_value'  => maybe_serialize( $old_value ),
					'value'      => maybe_serialize( $new_value ),
					'page_id'    => empty( $new_value[ $option ] ) ? 0 : $new_value[ $option ],
				),
				null,
				array(
					'components' => 'updated',
				)
			);
		}
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public static function log_override( array $data ) {
		return $data;
	}

	public static function callback_update_user_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		self::meta( $object_id, $meta_key, $_meta_value );
	}

	public static function callback_add_user_meta( $object_id, $meta_key, $_meta_value ) {
		self::meta( $object_id, $meta_key, $_meta_value, true );
	}

	public static function callback_delete_user_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		self::meta( $object_id, $meta_key, null );
	}

	public static function meta( $object_id, $key, $value, $is_add = false ) {
		if ( ! in_array( $key, self::$user_meta ) ) {
			return false;
		}

		$key = str_replace( '-', '_', $key );

		if ( method_exists( __CLASS__, 'meta_' . $key ) ) {
			return call_user_func( array( __CLASS__, 'meta_' . $key ), $object_id, $value, $is_add );
		}
	}

	private static function bp_get_directory_pages() {
		$bp              = buddypress();
		$directory_pages = array();

		// Loop through loaded components and collect directories
		if ( is_array( $bp->loaded_components ) ) {
			foreach ( $bp->loaded_components as $component_slug => $component_id ) {
				// Only components that need directories should be listed here
				if ( isset( $bp->{$component_id} ) && ! empty( $bp->{$component_id}->has_directory ) ) {
					// component->name was introduced in BP 1.5, so we must provide a fallback
					$directory_pages[ $component_id ] = ! empty ( $bp->{ $component_id }->name ) ? $bp->{ $component_id }->name : ucwords( $component_id );
				}
			}
		}

		return $directory_pages;
	}

}
