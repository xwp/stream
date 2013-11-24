<?php

class X_Stream_Context_User extends X_Stream_Context {

	public static $name;

	public static $actions = array(
		'user_register',
		'profile_update',
		'password_reset',
		'retrieve_password',
		'wp_login',
		'clear_auth_cookie', // logout
	);

	public static function get_name() {
		return __( 'Users', 'wp_stream' );
	}

	public static function callback_user_register( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		self::log(
			__( '%s has registered', 'wp_stream' ),
			array(
				$user->email,
				$user->ID,
			),
			$user->ID,
			__( 'Created', 'wp_stream' ),
			$user->ID
		);
	}

	public static function callback_profile_update( $user_id, $user ) {
		self::log(
			__( '%s has updated his profile', 'wp_stream' ),
			array(
				$user->display_name,
				$user->ID,
				),
			$user->ID,
			__( 'Updated', 'wp_stream' ),
			$user->ID
		);
	}

	public static function callback_password_reset( $user ) {
		self::log(
			__( '%s has reset his password', 'wp_stream' ),
			array(
				$user->email,
				$user->ID,
			),
			$user->ID,
			__( 'Password Reset', 'wp_reset' ),
			$user->ID
		);
	}

	public static function callback_retrieve_password( $user_login ) {
		if ( filter_var( $user_login, FILTER_VALIDATE_EMAIL ) ) {
			$user = get_user_by( 'email', $user_login );
		} else {
			$user = get_user_by( 'login', $user_login );
		}
		self::log(
			__( '%s has requested to reset his password', 'wp_stream' ),
			array(
				$user->display_name,
				$user->ID,
			),
			$user->ID,
			__( 'Forgot password', 'wp_stream' ),
			$user->ID
		);
	}

	public static function callback_wp_login( $user_login, $user ) {
		self::log(
			__( '%s logged in from %s', 'wp_stream' ),
			array(
				$user->display_name,
				filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP ),
				),
			$user->ID,
			__( 'Login', 'wp_stream' ),
			$user->ID
			);
	}

	public static function callback_clear_auth_cookie() {
		$user = wp_get_current_user();
		self::log(
			__( '%s logged out', 'wp_stream' ),
			array(
				$user->display_name,
				),
			$user->ID,
			__( 'Logout', 'wp_stream' ),
			$user->ID
			);
	}

	public static function action_links( $links, $stream_id, $object_id ) {
		$links [ __( 'User profile', 'domain' ) ] = get_edit_user_link( $object_id );
		return $links;
	}
}