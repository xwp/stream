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

		'bp_activity_delete',
		'bp_activity_mark_as_spam',
		'bp_activity_mark_as_ham',
		'bp_activity_admin_edit_after',

		'groups_create_group',
		'groups_update_group',
		'groups_before_delete_group',
		'groups_details_updated',
		'groups_settings_updated',

		'groups_leave_group',
		'groups_join_group',

		'groups_promote_member',
		'groups_demote_member',
		'groups_ban_member',
		'groups_unban_member',
		'groups_remove_member',

		'xprofile_field_after_save',
		'xprofile_fields_deleted_field',

		'xprofile_group_after_save',
		'xprofile_groups_deleted_group',

		// @todo implement the following
		//'groups_membership_accepted',
		//'groups_membership_rejected',
		//'groups_accept_all_pending_membership_requests',
		//'groups_send_invites',
		//'groups_delete_invite',
		//'groups_reject_invite',
		//'groups_accept_invite',
		//'groups_uninvite_user',
		//'groups_invite_user',
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
	 * Flag to stop logging update logic twice
	 *
	 * @var bool
	 */
	public static $is_update = false;

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
			'spammed'     => __( 'Marked as spam', 'stream' ),
			'unspammed'   => __( 'Unmarked as spam', 'stream' ),
			'promoted'    => __( 'Promoted', 'stream' ),
			'demoted'     => __( 'Demoted', 'stream' ),
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
			'groups' => __( 'Groups', 'stream' ),
			'activity' => __( 'Activity', 'stream' ),
			'profile_fields' => __( 'Profile fields', 'stream' ),
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
				__( '"%s" setting updated', 'stream' ),
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
					__( '"%1$s" component %2$s', 'stream' ),
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
					__( '"%1$s" page set to "%2$s"', 'stream' ),
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

	public static function callback_bp_activity_delete( $args ) {
		$activity = new BP_Activity_Activity( $args['id'] );

		self::log(
			sprintf(
				__( 'Deleted activity "%s"', 'stream' ),
				strip_tags( $activity->action )
			),
			array(
				'id' => $activity->id,
				'item_id' => $activity->item_id,
				'type' => $activity->type,
				'author' => $activity->user_id,
			),
			$activity->id,
			array(
				$activity->component => 'deleted',
			)
		);
	}

	public static function callback_bp_activity_mark_as_spam( $activity, $by ) {
		self::log(
			sprintf(
				__( 'Spammed activity "%s"', 'stream' ),
				strip_tags( $activity->action )
			),
			array(
				'id' => $activity->id,
				'item_id' => $activity->item_id,
				'type' => $activity->type,
				'author' => $activity->user_id,
			),
			$activity->id,
			array(
				$activity->component => 'spammed',
			)
		);
	}

	public static function callback_bp_activity_mark_as_ham( $activity, $by ) {
		self::log(
			sprintf(
				__( 'Unspammed activity "%s"', 'stream' ),
				strip_tags( $activity->action )
			),
			array(
				'id' => $activity->id,
				'item_id' => $activity->item_id,
				'type' => $activity->type,
				'author' => $activity->user_id,
			),
			$activity->id,
			array(
				$activity->component => 'unspammed',
			)
		);
	}

	public static function callback_bp_activity_admin_edit_after( $activity, $error ) {
		self::log(
			sprintf(
				__( 'Updated activity "%s"', 'stream' ),
				strip_tags( $activity->action )
			),
			array(
				'id' => $activity->id,
				'item_id' => $activity->item_id,
				'type' => $activity->type,
				'author' => $activity->user_id,
			),
			$activity->id,
			array(
				'activity' => 'updated',
			)
		);
	}

	public static function group_action( $group, $action, $meta = array(), $message = null ) {
		if ( is_numeric( $group ) ) {
			$group = groups_get_group( array( 'group_id' => $group ) );
		}

		$replacements = array(
			$group->name,
		);

		if ( $message ) {
			// Do nothing
		}
		elseif ( 'created' === $action ) {
			$message = __( 'Created group "%s"', 'stream' );
		}
		elseif ( 'updated' === $action ) {
			$message = __( 'Updated group "%s"', 'stream' );
		}
		elseif ( 'deleted' === $action ) {
			$message = __( 'Deleted group "%s"', 'stream' );
		}
		elseif ( 'joined' === $action ) {
			$message = __( 'Joined group "%s"', 'stream' );
		}
		elseif ( 'left' === $action ) {
			$message = __( 'Left group "%s"', 'stream' );
		}
		elseif ( 'banned' === $action ) {
			$message = __( 'Banned "%2$s" from "%1$s"', 'stream' );
			$replacements[] = get_user_by( 'id', $meta['user_id'] )->display_name;
		}
		elseif ( 'unbanned' === $action ) {
			$message = __( 'Unbanned "%2$s" from "%1$s"', 'stream' );
			$replacements[] = get_user_by( 'id', $meta['user_id'] )->display_name;
		}
		elseif ( 'removed' === $action ) {
			$message = __( 'Removed "%2$s" from "%1$s"', 'stream' );
			$replacements[] = get_user_by( 'id', $meta['user_id'] )->display_name;
		}
		else {
			return;
		}

		self::log(
			vsprintf(
				$message,
				$replacements
			),
			array_merge(
				array(
					'id' => $group->id,
					'name' => $group->name,
					'slug' => $group->slug,
				),
				$meta
			),
			$group->id,
			array(
				'groups' => $action,
			)
		);
	}

	public static function callback_groups_create_group( $group_id, $member, $group ) {
		self::group_action( $group, 'created' );
	}
	public static function callback_groups_update_group( $group_id, $group ) {
		self::group_action( $group, 'updated' );
	}
	public static function callback_groups_before_delete_group( $group_id ) {
		self::group_action( $group_id, 'deleted' );
	}
	public static function callback_groups_details_updated( $group_id ) {
		self::$is_update = true;
		self::group_action( $group_id, 'updated' );
	}
	public static function callback_groups_settings_updated( $group_id ) {
		if ( self::$is_update ) {
			return;
		}
		self::group_action( $group_id, 'updated' );
	}

	public static function callback_groups_leave_group( $group_id, $user_id ) {
		self::group_action( $group_id, 'left', compact( 'user_id' ) );
	}
	public static function callback_groups_join_group( $group_id, $user_id ) {
		self::group_action( $group_id, 'joined', compact( 'user_id' ) );
	}

	public static function callback_groups_promote_member( $group_id, $user_id, $status ) {
		$group = groups_get_group( array( 'group_id' => $group_id ) );
		$user = new WP_User( $user_id );
		$roles = array(
			'admin' => __( 'Administrator', 'buddypress' ),
			'mod' => __( 'Moderator', 'buddypress' ),
		);
		$message = sprintf(
			__( 'Promoted "%s" to "%s" in "%s"', 'stream' ),
			$user->display_name,
			$roles[ $status ],
			$group->name
		);
		self::group_action( $group_id, 'promoted', compact( 'user_id', 'status' ), $message );
	}
	public static function callback_groups_demote_member( $group_id, $user_id ) {
		$group = groups_get_group( array( 'group_id' => $group_id ) );
		$user = new WP_User( $user_id );
		$message = sprintf(
			__( 'Demoted "%s" to "%s" in "%s"', 'stream' ),
			$user->display_name,
			__( 'Member', 'buddypress' ),
			$group->name
		);
		self::group_action( $group_id, 'demoted', compact( 'user_id' ), $message );
	}
	public static function callback_groups_ban_member( $group_id, $user_id ) {
		self::group_action( $group_id, 'banned', compact( 'user_id' ) );
	}
	public static function callback_groups_unban_member( $group_id, $user_id ) {
		self::group_action( $group_id, 'unbanned', compact( 'user_id' ) );
	}
	public static function callback_groups_remove_member( $group_id, $user_id ) {
		self::group_action( $group_id, 'removed', compact( 'user_id' ) );
	}

	public static function field_action( $field, $action, $meta = array(), $message = null ) {
		$replacements = array(
			$field->name,
		);

		if ( $message ) {
			// Do nothing
		}
		elseif ( 'created' === $action ) {
			$message = __( 'Created profile field "%s"', 'stream' );
		}
		elseif ( 'updated' === $action ) {
			$message = __( 'Updated profile field "%s"', 'stream' );
		}
		elseif ( 'deleted' === $action ) {
			$message = __( 'Deleted profile field "%s"', 'stream' );
		}
		else {
			return;
		}

		self::log(
			vsprintf(
				$message,
				$replacements
			),
			array_merge(
				array(
					'id' => $field->id,
					'name' => $field->name,
				),
				$meta
			),
			$field->id,
			array(
				'profile_fields' => $action,
			)
		);
	}

	public static function callback_xprofile_field_after_save( $field ) {
		$action = isset( $field->id ) ? 'updated' : 'created';
		self::field_action( $field, $action );
	}

	public static function callback_xprofile_fields_deleted_field( $field ){
		self::field_action( $field, 'deleted' );
	}

	public static function field_group_action( $group, $action, $meta = array(), $message = null ) {
		$replacements = array(
			$group->name,
		);

		if ( $message ) {
			// Do nothing
		}
		elseif ( 'created' === $action ) {
			$message = __( 'Created profile field group "%s"', 'stream' );
		}
		elseif ( 'updated' === $action ) {
			$message = __( 'Updated profile field group "%s"', 'stream' );
		}
		elseif ( 'deleted' === $action ) {
			$message = __( 'Deleted profile field group "%s"', 'stream' );
		}
		else {
			return;
		}

		self::log(
			vsprintf(
				$message,
				$replacements
			),
			array_merge(
				array(
					'id' => $group->id,
					'name' => $group->name,
				),
				$meta
			),
			$group->id,
			array(
				'profile_fields' => $action,
			)
		);
	}

	public static function callback_xprofile_group_after_save( $group ) {
		global $wpdb;
		// a bit hacky, due to inconsistency with BP action scheme, see callback_xprofile_field_after_save for correct behavior
		$action = ( $group->id === $wpdb->insert_id ) ? 'created' : 'updated';
		self::field_group_action( $group, $action );
	}

	public static function callback_xprofile_groups_deleted_group( $group ){
		self::field_group_action( $group, 'deleted' );
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
