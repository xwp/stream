<?php

class X_Stream_Context_User extends X_Stream_Context {

	public $actions = array(
		'user_register',
		'profile_update',
		'password_reset',
		'retrieve_password',
	);

	public function callback_user_register( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		self::log(
			__( '%s #%d has registered', 'x-stream' ),
			array(
				$user->email,
				$user->ID,
			),
			$user->ID,
			'register',
			$user->ID
		);
	}

	public function callback_profile_update( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		self::log(
			__( '%s #%d has updated his profile', 'x-stream' ),
			array(
				$user->email,
				$user->ID,
			),
			$user->ID,
			'update-profile'
		);
	}

	public function callback_password_reset( $user ) {
		self::log(
			__( '%s #%d has reset his password', 'x-stream' ),
			array(
				$user->email,
				$user->ID,
			),
			$user->ID,
			'reset-password-request',
			$user->ID
		);
	}

	public function callback_retrieve_password( $user_login ) {
		$user = get_user_by( 'login', $user_login );
		self::log(
			__( '%s #%d has requested to reset his password', 'x-stream' ),
			array(
				$user->email,
				$user->ID,
			),
			$user->ID,
			'reset-password-request',
			$user->ID
		);
	}
}