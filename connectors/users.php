<?php

class WP_Stream_Connector_Users extends WP_Stream_Connector {

	/**
	 * Context name
	 * @var string
	 */
	public static $name = 'users';

	/**
	 * Stores users object before the user being deleted.
	 *
	 * @var array
	 * @access protected
	 */
	protected static $_users_object_pre_deleted = array();

	/**
	 * Actions registered for this context
	 * @var array
	 */
	public static $actions = array(
		'user_register',
		'profile_update',
		'password_reset',
		'retrieve_password',
		'wp_login',
		'clear_auth_cookie',
		'delete_user',
		'deleted_user',
		'wp_login_failed',
		'set_user_role',
	);

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Users', 'stream' );
	}

	/**
	 * Return translated action term labels
	 *
	 * @return array Action terms label translation
	 */
	public static function get_action_labels() {
		return array(
			'updated'         => __( 'Updated', 'stream' ),
			'created'         => __( 'Created', 'stream' ),
			'deleted'         => __( 'Deleted', 'stream' ),
			'password-reset'  => __( 'Password Reset', 'stream' ),
			'forgot-password' => __( 'Forgot Password', 'stream' ),
			'login'           => __( 'Login', 'stream' ),
			'logout'          => __( 'Logout', 'stream' ),
			'failed_login'    => __( 'Failed Login', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'users' => __( 'Users', 'stream' ),
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
			if ( $link = get_edit_user_link( $record->object_id ) ) {
				$links [ __( 'Edit Profile', 'stream' ) ] = $link;
			}
		}
		return $links;
	}

	/**
	 * Get an array of role lables assigned to a specific user.
	 *
	 * @param  object|int $user    User object or user ID to get roles for
	 * @return array      $labels  An array of role labels
	 */
	public static function get_role_labels( $user ) {
		if ( is_int( $user ) ) {
			$user = get_user_by( 'id', $user );
		}

		if ( ! is_a( $user, 'WP_User' ) ) {
			return;
		}

		global $wp_roles;

		$roles  = $wp_roles->get_names();
		$labels = array();

		foreach ( $roles as $role => $label ) {
			if ( in_array( $role, (array) $user->roles ) ) {
				$labels[] = translate_user_role( $label );
			}
		}

		return $labels;
	}

	/**
	 * Log user registrations
	 *
	 * @action user_register
	 * @param int $user_id Newly registered user ID
	 */
	public static function callback_user_register( $user_id ) {
		$current_user    = wp_get_current_user();
		$registered_user = get_user_by( 'id', $user_id );

		if ( ! $current_user->ID ) { // Non logged-in user registered themselves
			$message     = __( 'New user registration', 'stream' );
			$user_to_log = $registered_user->ID;
		} else { // Current logged-in user created a new user
			$message     = __( 'New user account created for %s (%s)', 'stream' );
			$user_to_log = $current_user->ID;
		}

		self::log(
			$message,
			array(
				'display_name' => ( $registered_user->display_name ) ? $registered_user->display_name : $registered_user->user_login,
				'roles'        => implode( ', ', self::get_role_labels( $user_id ) ),
			),
			$registered_user->ID,
			array(
				'users' => 'created',
			),
			$user_to_log
		);
	}

	/**
	 * Log profile update
	 *
	 * @action profile_update
	 */
	public static function callback_profile_update( $user_id, $user ) {
		self::log(
			__( '%s\'s profile was updated', 'stream' ),
			array(
				'display_name' => $user->display_name,
			),
			$user->ID,
			array(
				'users' => 'updated',
			)
		);
	}

	/**
	 * Log role transition
	 *
	 * @action set_user_role
	 */
	public static function callback_set_user_role( $user_id, $new_role, $old_roles ) {
		if ( empty( $old_roles ) ) {
			return;
		}

		global $wp_roles;
		self::log(
			__( '%s\'s role was changed from %s to %s', 'stream' ),
			array(
				'display_name' => get_user_by( 'id', $user_id )->display_name,
				'old_role'     => translate_user_role( $wp_roles->role_names[ $old_roles[0] ] ),
				'new_role'     => translate_user_role( $wp_roles->role_names[ $new_role ] ),
			),
			$user_id,
			array(
				'users' => 'updated',
			)
		);
	}

	/**
	 * Log password reset
	 *
	 * @action password_reset
	 */
	public static function callback_password_reset( $user ) {
		self::log(
			__( '%s\'s password was reset', 'stream' ),
			array(
				'email' => $user->display_name,
			),
			$user->ID,
			array(
				'users' => 'password-reset',
			),
			$user->ID
		);
	}

	/**
	 * Log user requests to retrieve passwords
	 *
	 * @action retrieve_password
	 */
	public static function callback_retrieve_password( $user_login ) {
		if ( filter_var( $user_login, FILTER_VALIDATE_EMAIL ) ) {
			$user = get_user_by( 'email', $user_login );
		} else {
			$user = get_user_by( 'login', $user_login );
		}
		self::log(
			__( '%s\'s password was requested to be reset', 'stream' ),
			array(
				'display_name' => $user->display_name,
			),
			$user->ID,
			array(
				'users' => 'forgot-password',
			),
			$user->ID
		);
	}

	/**
	 * Log user login
	 *
	 * @action wp_login
	 */
	public static function callback_wp_login( $user_login, $user ) {
		if ( self::is_logging_enabled_for_user( $user ) ) {
			self::log(
				__( '%s logged in', 'stream' ),
				array(
					'display_name' => $user->display_name,
				),
				$user->ID,
				array(
					'users' => 'login',
				),
				$user->ID
			);
		}
	}

	/**
	 * Log user logout
	 *
	 * @action clear_auth_cookie
	 */
	public static function callback_clear_auth_cookie() {
		$user = wp_get_current_user();
		// For some reason, ignito mode calls clear_auth_cookie on failed login attempts
		if ( empty( $user ) || ! $user->exists() ) {
			return;
		}
		self::log(
			__( '%s logged out', 'stream' ),
			array(
				'display_name' => $user->display_name,
			),
			$user->ID,
			array(
				'users' => 'logout',
			),
			$user->ID
		);
	}

	/**
	 * There's no logging in this callback's action, the reason
	 * behind this hook is so that we can store user objects before
	 * being deleted. During `deleted_user` hook, our callback
	 * receives $user_id param but it's useless as the user record
	 * was already removed from DB.
	 *
	 * @action delete_user
	 * @param int $user_id User ID that maybe deleted
	 */
	public static function callback_delete_user( $user_id ) {
		if ( ! isset( self::$_users_object_pre_deleted[ $user_id ] ) ) {
			self::$_users_object_pre_deleted[ $user_id ] = get_user_by( 'id', $user_id );
		}
	}

	/**
	 * Log deleted user.
	 *
	 * @action deleted_user
	 * @param int $user_id Deleted user ID
	 */
	public static function callback_deleted_user( $user_id ) {
		$user = wp_get_current_user();

		if ( isset( self::$_users_object_pre_deleted[ $user_id ] ) ) {
			$message      = __( '%s\'s account was deleted (%s)', 'stream' );
			$display_name = self::$_users_object_pre_deleted[ $user_id ]->display_name;
			$deleted_user = self::$_users_object_pre_deleted[ $user_id ];
			unset( self::$_users_object_pre_deleted[ $user_id ] );
		} else {
			$message      = __( 'User account #%d was deleted', 'stream' );
			$display_name = $user_id;
			$deleted_user = $user_id;
		}

		self::log(
			$message,
			array(
				'display_name' => $display_name,
				'roles'        => implode( ', ', self::get_role_labels( $deleted_user ) ),
			),
			$user_id,
			array(
				'users' => 'deleted',
			),
			$user->ID
		);
	}

	/**
	 * Track failed login attempts
	 * @param  arary  $errors
	 * @param  string $redirect_to
	 * @return array
	 */
	public static function callback_wp_login_failed( $username ) {
		$user = get_user_by( 'login', $username );
		self::log(
			__( 'Invalid login attempt for %s', 'stream' ),
			compact( 'username' ),
			$user ? $user->ID : 0,
			array( 'users' => 'failed_login' ),
			$user ? $user->ID : 0
		);
	}

}
