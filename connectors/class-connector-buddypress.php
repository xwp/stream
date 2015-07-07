<?php
namespace WP_Stream;

class Connector_BuddyPress extends Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'buddypress';

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
	public $actions = array(
		'update_option',
		'add_option',
		'delete_option',
		'update_site_option',
		'add_site_option',
		'delete_site_option',

		'bp_before_activity_delete',
		'bp_activity_deleted_activities',

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
	);

	/**
	 * Tracked option keys
	 *
	 * @var array
	 */
	public $options = array(
		'bp-active-components' => null,
		'bp-pages'             => null,
		'buddypress'           => null,
	);

	/**
	 * Flag to stop logging update logic twice
	 *
	 * @var bool
	 */
	public $is_update = false;

	/**
	 * @var bool
	 */
	public $_deleted_activity = false;

	/**
	 * @var array
	 */
	public $_delete_activity_args = array();

	/**
	 * @var bool
	 */
	public $ignore_activity_bulk_deletion = false;

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		if ( class_exists( 'BuddyPress' ) && version_compare( \BuddyPress::instance()->version, self::PLUGIN_MIN_VERSION, '>=' ) ) {
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
		return esc_html_x( 'BuddyPress', 'buddypress', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'created'     => esc_html_x( 'Created', 'buddypress', 'stream' ),
			'updated'     => esc_html_x( 'Updated', 'buddypress', 'stream' ),
			'activated'   => esc_html_x( 'Activated', 'buddypress', 'stream' ),
			'deactivated' => esc_html_x( 'Deactivated', 'buddypress', 'stream' ),
			'deleted'     => esc_html_x( 'Deleted', 'buddypress', 'stream' ),
			'spammed'     => esc_html_x( 'Marked as spam', 'buddypress', 'stream' ),
			'unspammed'   => esc_html_x( 'Unmarked as spam', 'buddypress', 'stream' ),
			'promoted'    => esc_html_x( 'Promoted', 'buddypress', 'stream' ),
			'demoted'     => esc_html_x( 'Demoted', 'buddypress', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'components'     => esc_html_x( 'Components', 'buddypress', 'stream' ),
			'groups'         => esc_html_x( 'Groups', 'buddypress', 'stream' ),
			'activity'       => esc_html_x( 'Activity', 'buddypress', 'stream' ),
			'profile_fields' => esc_html_x( 'Profile fields', 'buddypress', 'stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array $links   Previous links registered
	 * @param  object $record Stream record
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		if ( in_array( $record->context, array( 'components' ) ) ) {
			$option_key = $record->get_meta( 'option_key', true );

			if ( 'bp-active-components' === $option_key ) {
				$links[ esc_html__( 'Edit', 'stream' ) ] = add_query_arg(
					array(
						'page' => 'bp-components',
					),
					admin_url( 'admin.php' )
				);
			} elseif ( 'bp-pages' === $option_key ) {
				$page_id = $record->get_meta( 'page_id', true );

				$links[ esc_html__( 'Edit setting', 'stream' ) ] = add_query_arg(
					array(
						'page' => 'bp-page-settings',
					),
					admin_url( 'admin.php' )
				);

				if ( $page_id ) {
					$links[ esc_html__( 'Edit Page', 'stream' ) ] = get_edit_post_link( $page_id );
					$links[ esc_html__( 'View', 'stream' ) ]      = get_permalink( $page_id );
				}
			}
		} elseif ( in_array( $record->context, array( 'settings' ) ) ) {
			$links[ esc_html__( 'Edit setting', 'stream' ) ] = add_query_arg(
				array(
					'page' => $record->get_meta( 'page', true ),
				),
				admin_url( 'admin.php' )
			);
		} elseif ( in_array( $record->context, array( 'groups' ) ) ) {
			$group_id = $record->get_meta( 'id', true );
			$group    = \groups_get_group( array( 'group_id' => $group_id ) );

			if ( $group ) {
				// Build actions URLs
				$base_url   = \bp_get_admin_url( 'admin.php?page=bp-groups&amp;gid=' . $group_id );
				$delete_url = wp_nonce_url( $base_url . '&amp;action=delete', 'bp-groups-delete' );
				$edit_url   = $base_url . '&amp;action=edit';
				$visit_url  = \bp_get_group_permalink( $group );

				$links[ esc_html__( 'Edit group', 'stream' ) ] = $edit_url;
				$links[ esc_html__( 'View group', 'stream' ) ] = $visit_url;
				$links[ esc_html__( 'Delete group', 'stream' ) ] = $delete_url;
			}
		} elseif ( in_array( $record->context, array( 'activity' ) ) ) {
			$activity_id = $record->get_meta( 'id', true );
			$activities = \bp_activity_get( array( 'in' => $activity_id, 'spam' => 'all' ) );
			if ( ! empty( $activities['activities'] ) ) {
				$activity = reset( $activities['activities'] );

				$base_url   = \bp_get_admin_url( 'admin.php?page=bp-activity&amp;aid=' . $activity->id );
				$spam_nonce = esc_html( '_wpnonce=' . wp_create_nonce( 'spam-activity_' . $activity->id ) );
				$delete_url = $base_url . "&amp;action=delete&amp;$spam_nonce";
				$edit_url   = $base_url . '&amp;action=edit';
				$ham_url    = $base_url . "&amp;action=ham&amp;$spam_nonce";
				$spam_url   = $base_url . "&amp;action=spam&amp;$spam_nonce";

				if ( $activity->is_spam ) {
					$links[ esc_html__( 'Ham', 'stream' ) ] = $ham_url;
				} else {
					$links[ esc_html__( 'Edit', 'stream' ) ] = $edit_url;
					$links[ esc_html__( 'Spam', 'stream' ) ] = $spam_url;
				}
				$links[ esc_html__( 'Delete', 'stream' ) ] = $delete_url;
			}
		} elseif ( in_array( $record->context, array( 'profile_fields' ) ) ) {
			$field_id = $record->get_meta( 'field_id', true );
			$group_id = $record->get_meta( 'group_id', true );

			if ( empty( $field_id ) ) { // is a group action
				$links[ esc_html__( 'Edit', 'stream' ) ] = add_query_arg(
					array(
						'page' => 'bp-profile-setup',
						'mode' => 'edit_group',
						'group_id' => $group_id,
					),
					admin_url( 'users.php' )
				);
				$links[ esc_html__( 'Delete', 'stream' ) ] = add_query_arg(
					array(
						'page' => 'bp-profile-setup',
						'mode' => 'delete_group',
						'group_id' => $group_id,
					),
					admin_url( 'users.php' )
				);
			} else {
				$field = new \BP_XProfile_Field( $field_id );
				if ( empty( $field->type ) ) {
					return $links;
				}
				$links[ esc_html__( 'Edit', 'stream' ) ] = add_query_arg(
					array(
						'page' => 'bp-profile-setup',
						'mode' => 'edit_field',
						'group_id' => $group_id,
						'field_id' => $field_id,
					),
					admin_url( 'users.php' )
				);
				$links[ esc_html__( 'Delete', 'stream' ) ] = add_query_arg(
					array(
						'page' => 'bp-profile-setup',
						'mode' => 'delete_field',
						'field_id' => $field_id,
					),
					admin_url( 'users.php' )
				);
			}
		}

		return $links;
	}

	public function register() {
		parent::register();

		$this->options = array_merge(
			$this->options,
			array(
				'hide-loggedout-adminbar'       => array(
					'label' => esc_html_x( 'Toolbar', 'buddypress', 'stream' ),
					'page'  => 'bp-settings',
				),
				'_bp_force_buddybar'            => array(
					'label' => esc_html_x( 'Toolbar', 'buddypress', 'stream' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-account-deletion'   => array(
					'label' => esc_html_x( 'Account Deletion', 'buddypress', 'stream' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-profile-sync'       => array(
					'label' => esc_html_x( 'Profile Syncing', 'buddypress', 'stream' ),
					'page'  => 'bp-settings',
				),
				'bp_restrict_group_creation'    => array(
					'label' => esc_html_x( 'Group Creation', 'buddypress', 'stream' ),
					'page'  => 'bp-settings',
				),
				'bb-config-location'            => array(
					'label' => esc_html_x( 'bbPress Configuration', 'buddypress', 'stream' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-blogforum-comments' => array(
					'label' => _x( 'Blog &amp; Forum Comments', 'buddypress', 'stream' ),
					'page'  => 'bp-settings',
				),
				'_bp_enable_heartbeat_refresh'  => array(
					'label' => esc_html_x( 'Activity auto-refresh', 'buddypress', 'stream' ),
					'page'  => 'bp-settings',
				),
				'_bp_enable_akismet'            => array(
					'label' => esc_html_x( 'Akismet', 'buddypress', 'stream' ),
					'page'  => 'bp-settings',
				),
				'bp-disable-avatar-uploads'     => array(
					'label' => esc_html_x( 'Avatar Uploads', 'buddypress', 'stream' ),
					'page'  => 'bp-settings',
				),
			)
		);
	}

	public function callback_update_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

	public function callback_add_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

	public function callback_delete_option( $option ) {
		$this->check( $option, null, null );
	}

	public function callback_update_site_option( $option, $old, $new ) {
		$this->check( $option, $old, $new );
	}

	public function callback_add_site_option( $option, $val ) {
		$this->check( $option, null, $val );
	}

	public function callback_delete_site_option( $option ) {
		$this->check( $option, null, null );
	}

	public function check( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, $this->options ) ) {
			return;
		}

		$replacement = str_replace( '-', '_', $option );

		if ( method_exists( $this, 'check_' . $replacement ) ) {
			call_user_func( array( $this, 'check_' . $replacement ), $old_value, $new_value );
		} else {
			$data         = $this->options[ $option ];
			$option_title = $data['label'];
			$context      = isset( $data['context'] ) ? $data['context'] : 'settings';
			$page         = isset( $data['page'] ) ? $data['page'] : null;

			$this->log(
				__( '"%s" setting updated', 'stream' ),
				compact( 'option_title', 'option', 'old_value', 'new_value', 'page' ),
				null,
				$context,
				isset( $data['action'] ) ? $data['action'] : 'updated'
			);
		}
	}

	public function check_bp_active_components( $old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( $this->get_changed_keys( $old_value, $new_value, 0 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		$components = \bp_core_admin_get_components();

		$actions = array(
			true  => esc_html__( 'activated', 'stream' ),
			false => esc_html__( 'deactivated', 'stream' ),
		);

		foreach ( $options as $option => $option_value ) {
			if ( ! isset( $components[ $option ], $actions[ $option_value ] ) ) {
				continue;
			}

			$this->log(
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
				'components',
				$option_value ? 'activated' : 'deactivated'
			);
		}
	}

	public function check_bp_pages( $old_value, $new_value ) {
		$options = array();

		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		foreach ( $this->get_changed_keys( $old_value, $new_value, 0 ) as $field_key => $field_value ) {
			$options[ $field_key ] = $field_value;
		}

		$pages = array_merge(
			$this->bp_get_directory_pages(),
			array(
				'register' => esc_html_x( 'Register', 'buddypress', 'stream' ),
				'activate' => esc_html_x( 'Activate', 'buddypress', 'stream' ),
			)
		);

		foreach ( $options as $option => $option_value ) {
			if ( ! isset( $pages[ $option ] ) ) {
				continue;
			}

			$page = ! empty( $new_value[ $option ] ) ? get_post( $new_value[ $option ] )->post_title : esc_html__( 'No page', 'stream' );

			$this->log(
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
				'components',
				'updated'
			);
		}
	}

	public function callback_bp_before_activity_delete( $args ) {
		if ( empty( $args['id'] ) ) { // Bail if we're deleting in bulk
			$this->_delete_activity_args = $args;
			return;
		}

		$activity = new \BP_Activity_Activity( $args['id'] );

		$this->_deleted_activity = $activity;
	}

	public function callback_bp_activity_deleted_activities( $activities_ids ) {
		if ( 1 === count( $activities_ids ) && isset( $this->_deleted_activity ) ) { // Single activity deletion
			$activity = $this->_deleted_activity;
			$this->log(
				sprintf(
					__( '"%s" activity deleted', 'stream' ),
					strip_tags( $activity->action )
				),
				array(
					'id' => $activity->id,
					'item_id' => $activity->item_id,
					'type' => $activity->type,
					'author' => $activity->user_id,
				),
				$activity->id,
				$activity->component,
				'deleted'
			);
		} else { // Bulk deletion
			// Sometimes some objects removal are followed by deleting relevant
			// activities, so we probably don't need to track those
			if ( $this->ignore_activity_bulk_deletion ) {
				$this->ignore_activity_bulk_deletion = false;
				return;
			}
			$this->log(
				sprintf(
					__( '"%s" activities were deleted', 'stream' ),
					count( $activities_ids )
				),
				array(
					'count' => count( $activities_ids ),
					'args'  => $this->_delete_activity_args,
					'ids'   => $activities_ids,
				),
				null,
				'activity',
				'deleted'
			);
		}
	}

	public function callback_bp_activity_mark_as_spam( $activity, $by ) {
		unset( $by );

		$this->log(
			sprintf(
				__( 'Marked activity "%s" as spam', 'stream' ),
				strip_tags( $activity->action )
			),
			array(
				'id' => $activity->id,
				'item_id' => $activity->item_id,
				'type' => $activity->type,
				'author' => $activity->user_id,
			),
			$activity->id,
			$activity->component,
			'spammed'
		);
	}

	public function callback_bp_activity_mark_as_ham( $activity, $by ) {
		unset( $by );

		$this->log(
			sprintf(
				__( 'Unmarked activity "%s" as spam', 'stream' ),
				strip_tags( $activity->action )
			),
			array(
				'id' => $activity->id,
				'item_id' => $activity->item_id,
				'type' => $activity->type,
				'author' => $activity->user_id,
			),
			$activity->id,
			$activity->component,
			'unspammed'
		);
	}

	public function callback_bp_activity_admin_edit_after( $activity, $error ) {
		unset( $error );

		$this->log(
			sprintf(
				__( '"%s" activity updated', 'stream' ),
				strip_tags( $activity->action )
			),
			array(
				'id' => $activity->id,
				'item_id' => $activity->item_id,
				'type' => $activity->type,
				'author' => $activity->user_id,
			),
			$activity->id,
			'activity',
			'updated'
		);
	}

	public function group_action( $group, $action, $meta = array(), $message = null ) {
		if ( is_numeric( $group ) ) {
			$group = \groups_get_group( array( 'group_id' => $group ) );
		}

		$replacements = array(
			$group->name,
		);

		if ( ! $message ) {
			if ( 'created' === $action ) {
				$message = esc_html__( '"%s" group created', 'stream' );
			} elseif ( 'updated' === $action ) {
				$message = esc_html__( '"%s" group updated', 'stream' );
			} elseif ( 'deleted' === $action ) {
				$message = esc_html__( '"%s" group deleted', 'stream' );
			} elseif ( 'joined' === $action ) {
				$message = esc_html__( 'Joined group "%s"', 'stream' );
			} elseif ( 'left' === $action ) {
				$message = esc_html__( 'Left group "%s"', 'stream' );
			} elseif ( 'banned' === $action ) {
				$message = esc_html__( 'Banned "%2$s" from "%1$s"', 'stream' );
				$replacements[] = get_user_by( 'id', $meta['user_id'] )->display_name;
			} elseif ( 'unbanned' === $action ) {
				$message = esc_html__( 'Unbanned "%2$s" from "%1$s"', 'stream' );
				$replacements[] = get_user_by( 'id', $meta['user_id'] )->display_name;
			} elseif ( 'removed' === $action ) {
				$message = esc_html__( 'Removed "%2$s" from "%1$s"', 'stream' );
				$replacements[] = get_user_by( 'id', $meta['user_id'] )->display_name;
			} else {
				return;
			}
		}

		$this->log(
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
			'groups',
			$action
		);
	}

	public function callback_groups_create_group( $group_id, $member, $group ) {
		unset( $group_id );
		unset( $member );

		$this->group_action( $group, 'created' );
	}

	public function callback_groups_update_group( $group_id, $group ) {
		unset( $group_id );

		$this->group_action( $group, 'updated' );
	}

	public function callback_groups_before_delete_group( $group_id ) {
		$this->ignore_activity_bulk_deletion = true;
		$this->group_action( $group_id, 'deleted' );
	}

	public function callback_groups_details_updated( $group_id ) {
		$this->is_update = true;
		$this->group_action( $group_id, 'updated' );
	}

	public function callback_groups_settings_updated( $group_id ) {
		if ( $this->is_update ) {
			return;
		}
		$this->group_action( $group_id, 'updated' );
	}

	public function callback_groups_leave_group( $group_id, $user_id ) {
		$this->group_action( $group_id, 'left', compact( 'user_id' ) );
	}

	public function callback_groups_join_group( $group_id, $user_id ) {
		$this->group_action( $group_id, 'joined', compact( 'user_id' ) );
	}

	public function callback_groups_promote_member( $group_id, $user_id, $status ) {
		$group = \groups_get_group( array( 'group_id' => $group_id ) );
		$user = new \WP_User( $user_id );
		$roles = array(
			'admin' => esc_html_x( 'Administrator', 'buddypress', 'stream' ),
			'mod'   => esc_html_x( 'Moderator', 'buddypress', 'stream' ),
		);
		$message = sprintf(
			__( 'Promoted "%s" to "%s" in "%s"', 'stream' ),
			$user->display_name,
			$roles[ $status ],
			$group->name
		);
		$this->group_action( $group_id, 'promoted', compact( 'user_id', 'status' ), $message );
	}

	public function callback_groups_demote_member( $group_id, $user_id ) {
		$group = \groups_get_group( array( 'group_id' => $group_id ) );
		$user = new \WP_User( $user_id );
		$message = sprintf(
			__( 'Demoted "%s" to "%s" in "%s"', 'stream' ),
			$user->display_name,
			_x( 'Member', 'buddypress', 'stream' ),
			$group->name
		);
		$this->group_action( $group_id, 'demoted', compact( 'user_id' ), $message );
	}

	public function callback_groups_ban_member( $group_id, $user_id ) {
		$this->group_action( $group_id, 'banned', compact( 'user_id' ) );
	}

	public function callback_groups_unban_member( $group_id, $user_id ) {
		$this->group_action( $group_id, 'unbanned', compact( 'user_id' ) );
	}

	public function callback_groups_remove_member( $group_id, $user_id ) {
		$this->group_action( $group_id, 'removed', compact( 'user_id' ) );
	}

	public function field_action( $field, $action, $meta = array(), $message = null ) {
		$replacements = array(
			$field->name,
		);

		if ( ! $message ) {
			if ( 'created' === $action ) {
				$message = esc_html__( 'Created profile field "%s"', 'stream' );
			} elseif ( 'updated' === $action ) {
				$message = esc_html__( 'Updated profile field "%s"', 'stream' );
			} elseif ( 'deleted' === $action ) {
				$message = esc_html__( 'Deleted profile field "%s"', 'stream' );
			} else {
				return;
			}
		}

		$this->log(
			vsprintf(
				$message,
				$replacements
			),
			array_merge(
				array(
					'field_id'   => $field->id,
					'field_name' => $field->name,
					'group_id'   => $field->group_id,
				),
				$meta
			),
			$field->id,
			'profile_fields',
			$action
		);
	}

	public function callback_xprofile_field_after_save( $field ) {
		$action = isset( $field->id ) ? 'updated' : 'created';
		$this->field_action( $field, $action );
	}

	public function callback_xprofile_fields_deleted_field( $field ) {
		$this->field_action( $field, 'deleted' );
	}

	public function field_group_action( $group, $action, $meta = array(), $message = null ) {
		$replacements = array(
			$group->name,
		);

		if ( ! $message ) {
			if ( 'created' === $action ) {
				$message = esc_html__( 'Created profile field group "%s"', 'stream' );
			} elseif ( 'updated' === $action ) {
				$message = esc_html__( 'Updated profile field group "%s"', 'stream' );
			} elseif ( 'deleted' === $action ) {
				$message = esc_html__( 'Deleted profile field group "%s"', 'stream' );
			} else {
				return;
			}
		}

		$this->log(
			vsprintf(
				$message,
				$replacements
			),
			array_merge(
				array(
					'group_id'   => $group->id,
					'group_name' => $group->name,
				),
				$meta
			),
			$group->id,
			'profile_fields',
			$action
		);
	}

	public function callback_xprofile_group_after_save( $group ) {
		global $wpdb;
		// a bit hacky, due to inconsistency with BP action scheme, see callback_xprofile_field_after_save for correct behavior
		$action = ( $group->id === $wpdb->insert_id ) ? 'created' : 'updated';
		$this->field_group_action( $group, $action );
	}

	public function callback_xprofile_groups_deleted_group( $group ) {
		$this->field_group_action( $group, 'deleted' );
	}

	private function bp_get_directory_pages() {
		$bp              = \buddypress();
		$directory_pages = array();

		// Loop through loaded components and collect directories
		if ( is_array( $bp->loaded_components ) ) {
			foreach ( $bp->loaded_components as $component_slug => $component_id ) {
				// Only components that need directories should be listed here
				if ( isset( $bp->{$component_id} ) && ! empty( $bp->{$component_id}->has_directory ) ) {
					// component->name was introduced in BP 1.5, so we must provide a fallback
					$directory_pages[ $component_id ] = ! empty( $bp->{ $component_id }->name ) ? $bp->{ $component_id }->name : ucwords( $component_id );
				}
			}
		}

		return $directory_pages;
	}
}
