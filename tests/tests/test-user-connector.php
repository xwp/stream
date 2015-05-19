<?php
/**
 * Test: WP Stream Users Connector.
 *
 * Context: Users.
 * Actions: Created, Deleted.
 *
 * Context: Sessions.
 * Actions: Login, Logout, Forgot Password.
 *
 * Context: Profile.
 * Actions: Updated, Set User Role, Password Reset.
 *
 * @author WP Stream
 * @author Michele Ong <michele@wpstream.com>
 */
class Test_WP_Stream_Connector_Users extends WP_StreamTestCase {

	/**
	 * User Context: Action Create
	 */
	public function test_action_user_create() {

		// Create a user
		$time = microtime();
		$user_id = $this->factory->user->create( array( 'display_name' => 'User ' . $time ) );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_user_register' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $user_id,
				'context'   => 'users',
				'action'    => 'created',
				'author_meta' => array( 'display_name' => 'User ' . $time )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * User Context: Action Delete
	 */
	public function test_action_user_delete() {

		// Create a user
		$time = microtime();
		$user_id = $this->factory->user->create( array( 'display_name' => 'User ' . $time ) );

		// Delete the user
		wp_delete_user($user_id);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_deleted_user' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $user_id,
				'context'   => 'users',
				'action'    => 'deleted',
				'meta' => array( 'display_name' => 'User ' . $time )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Session Context: Action Login
	 *
	 * NOTE: Not implemented. `Headers already sent` error needs resolution.
	 */
	public function test_action_session_login() {
		$this->markTestSkipped('Investigate Headers already sent error.');

		// Create a user
		$user_id = $this->factory->user->create();

		// Set the authentication cookie
		ob_start();
		wp_set_auth_cookie($user_id);
		ob_end_clean();

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_set_logged_in_cookie' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $user_id,
				'context'   => 'sessions',
				'action'    => 'login'
			)
		);

		// Check if the DB entry is okay
		$this->assertGreaterThanOrEqual( 1, count( $result ) );
	}

	/**
	 * Session Context: Action Logout
	 *
	 * NOTE: Not implemented. `Headers already sent` error needs resolution.
	 */
	public function test_action_session_logout() {
		$this->markTestSkipped('Investigate Headers already sent error.');

		// Create a user
		$user_id = $this->factory->user->create();

		// Delete the user
		wp_clear_auth_cookie();

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_clear_auth_cookie' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $user_id,
				'context'   => 'sessions',
				'action'    => 'logout'
			)
		);

		// Check if the DB entry is okay
		$this->assertGreaterThanOrEqual( 1, count( $result ) );
	}

	/**
	 * Session Context: Action Forgot Password
	 *
	 * NOTE: Not implemented. Required handler is in `wp-login.php` and it not able to be loaded.
	 */
	public function test_action_session_forgot_password() {
		$this->markTestSkipped('Investigate loading wp-login.php, or UAT test.');

		$scope = function() {
			include( dirname( __FILE__ ) . '/../../../../../wp-login.php' );

			// Create a user
			$user = func_get_arg(0)->factory->user->create_and_get();

			$_POST['user_login'] = $user->get('email');

			// Retrieve password
			retrieve_password();

			// Check if there is a callback called
			func_get_arg(0)->assertGreaterThan( 0, did_action( func_get_arg(0)->action_prefix . 'callback_retrieve_password' ) );

			// Check if the entry is in the database
			sleep(2);
			$result = wp_stream_query(
				array(
					'object_id' => $user->get('id'),
					'context'   => 'sessions',
					'action'    => 'forgot-password'
				)
			);

			// Check if the DB entry is okay
			func_get_arg(0)->assertGreaterThanOrEqual( 1, count( $result ) );
		};
		$scope($this);
	}

	/**
	 * Profile Context: Action Update
	 */
	public function test_action_profile_update() {

		// Create a user
		$time = microtime();
		$user_id = $this->factory->user->create( array( 'display_name' => 'User ' . $time ) );

		// Delete the user
		wp_update_user(array( 'ID' => $user_id, 'display_name' => 'User 1 ' . $time ));

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_profile_update' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $user_id,
				'context'   => 'profiles',
				'action'    => 'updated',
				'meta' => array( 'display_name' => 'User 1 ' . $time )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Profile Context: Action Set User Role
	 */
	public function test_action_profile_set_user_role() {

		// Create a user
		$time = microtime();
		$user = $this->factory->user->create_and_get( array( 'display_name' => 'User ' . $time ) );

		// Set the User Role
		$user->set_role('contributor');

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_set_user_role' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $user->ID,
				'context'   => 'profiles',
				'action'    => 'updated',
				'meta' => array( 'display_name' => 'User ' . $time, 'new_role' => 'Contributor' )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Profile Context: Action Password Reset
	 */
	public function test_action_profile_password_reset() {

		// Create a user
		$time = microtime();
		$user = $this->factory->user->create_and_get( array( 'display_name' => 'User ' . $time ) );

		// Reset User Password
		reset_password($user, 'password1');

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_password_reset' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $user->ID,
				'context'   => 'profiles',
				'action'    => 'password-reset',
				'meta' => array( 'display_name' => 'User ' . $time )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}
}
