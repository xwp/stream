<?php

class X_Stream_Context_User extends X_Stream_Context {

	/**
	 * Context name
	 * @var string
	 */
	public static $name = 'users';

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
	);

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Users', 'wp_stream' );
	}

	/**
	 * Return translated action term labels
	 *
	 * @return array Action terms label translation
	 */
	public static function get_action_term_labels() {
		return array(
			'updated'         => __( 'Updated', 'wp_stream' ),
			'created'         => __( 'Created', 'wp_stream' ),
			'deleted'         => __( 'Deleted', 'wp_stream' ),
			'password-reset'  => __( 'Password Reset', 'wp_stream' ),
			'forgot-password' => __( 'Forgot Password', 'wp_stream' ),
			'login'           => __( 'Login', 'wp_stream' ),
			'logout'          => __( 'Logout', 'wp_stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_users
	 * @param  array $links      Previous links registered
	 * @param  int   $stream_id  Stream drop id
	 * @param  int   $object_id  Object ( user ) id
	 * @return array             Action links
	 */
	public static function action_links( $links, $stream_id, $object_id ) {
		$links [ __( 'User profile', 'domain' ) ] = get_edit_user_link( $object_id );
		return $links;
	}

	/**
	 * Log user registrations
	 *
	 * @action user_register
	 */
	public static function callback_user_register( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		self::log(
			__( '%s has registered', 'wp_stream' ),
			array(
				'email' => $user->email,
			),
			$user->ID,
			'created',
			$user->ID
		);
	}

	/**
	 * Log profile update
	 *
	 * @action profile_update
	 */
	public static function callback_profile_update( $user_id, $user ) {
		self::log(
			__( 'Profile updated for %s', 'wp_stream' ),
			array(
				'display_name' => $user->display_name,
				),
			$user->ID,
			'updated',
			$user->ID
		);
	}

	/**
	 * Log password reset
	 *
	 * @action password_reset
	 */
	public static function callback_password_reset( $user ) {
		self::log(
			__( 'Password reset for %s', 'wp_stream' ),
			array(
				'email' => $user->email,
			),
			$user->ID,
			'password-reset',
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
			__( 'Password reset requested for %s', 'wp_stream' ),
			array(
				'display_name' => $user->display_name,
			),
			$user->ID,
			'forgot-password',
			$user->ID
		);
	}

	/**
	 * Log user login
	 *
	 * @action wp_login
	 */
	public static function callback_wp_login( $user_login, $user ) {
		self::log(
			__( '%s logged in from %s', 'wp_stream' ),
			array(
				'display_name' => $user->display_name,
				'ip'           => filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP ),
				),
			$user->ID,
			'login',
			$user->ID
			);
	}

	/**
	 * Log user logout
	 *
	 * @action clear_auth_cookie
	 */
	public static function callback_clear_auth_cookie() {
		$user = wp_get_current_user();
		self::log(
			__( '%s logged out', 'wp_stream' ),
			array(
				'display_name' => $user->display_name,
				),
			$user->ID,
			'logout',
			$user->ID
			);
	}

}