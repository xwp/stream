<?php
/**
 * Tests for Users connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Connector_Users_Test extends WP_StreamTestCase {

	/**
	 * Recorded log() arguments from mocked connector callbacks.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private static $recorded_log_calls = array();

	/**
	 * Records log() arguments for post-hoc assertions.
	 *
	 * @param mixed ...$args Log method arguments.
	 * @return void
	 */
	public static function record_log_call( ...$args ) {
		self::$recorded_log_calls[] = $args;
	}

	/**
	 * Runs before each test
	 */
	public function setUp(): void {
		parent::setUp();

		self::$recorded_log_calls = array();

		// Make partial of Connector_Users class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Users::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		$this->mock->register();
	}

	public function test_callback_user_register() {
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( esc_html__( 'New user registration', 'stream' ) ),
				$this->equalTo(
					array(
						'display_name' => 'TestGuy',
						'roles'        => 'Subscriber',
					)
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'users' ),
				$this->equalTo( 'created' ),
				$this->greaterThan( 0 )
			);

		self::factory()->user->create( array( 'display_name' => 'TestGuy' ) );

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_user_register' ) );
	}

	public function test_callback_user_register_by_logged_in_user() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'TestGuy' ) );
		wp_set_current_user( $user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'New user account created for %1$s (%2$s)',
						'1: User display name, 2: User role',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'display_name' => 'TestGuy2',
						'roles'        => 'Subscriber',
					)
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'users' ),
				$this->equalTo( 'created' ),
				$this->greaterThan( 0 )
			);

		self::factory()->user->create( array( 'display_name' => 'TestGuy2' ) );

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_user_register' ) );
	}

	public function test_callback_password_reset() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'TestGuy' ) );
		wp_set_current_user( $user_id );
		$user = get_user_by( 'id', $user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '%s\'s password was reset', 'stream' ) ),
				$this->equalTo( array( 'display_name' => 'TestGuy' ) ),
				$this->equalTo( $user_id ),
				$this->equalTo( 'profiles' ),
				$this->equalTo( 'password-reset' ),
				$this->equalTo( $user_id )
			);

		$new_pass = 'blahblahblah';
		reset_password( $user, $new_pass );

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_password_reset' ) );
	}

	public function test_callback_retrieve_password_and_profile_update() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'TestGuy' ) );
		$user    = get_user_by( 'id', $user_id );

		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'record_log_call' ) );

		get_password_reset_key( $user );

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_profile_update' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_retrieve_password' ) );

		$this->assertSame(
			__( '%s\'s password was requested to be reset', 'stream' ),
			self::$recorded_log_calls[0][0]
		);
		$this->assertSame( array( 'display_name' => 'TestGuy' ), self::$recorded_log_calls[0][1] );
		$this->assertSame( $user_id, self::$recorded_log_calls[0][2] );
		$this->assertSame( 'sessions', self::$recorded_log_calls[0][3] );
		$this->assertSame( 'forgot-password', self::$recorded_log_calls[0][4] );
		$this->assertSame( $user_id, self::$recorded_log_calls[0][5] );

		$this->assertSame(
			__( '%s\'s profile was updated', 'stream' ),
			self::$recorded_log_calls[1][0]
		);
		$this->assertSame( array( 'display_name' => 'TestGuy' ), self::$recorded_log_calls[1][1] );
		$this->assertSame( $user_id, self::$recorded_log_calls[1][2] );
		$this->assertSame( 'profiles', self::$recorded_log_calls[1][3] );
		$this->assertSame( 'updated', self::$recorded_log_calls[1][4] );
	}

	/**
	 * Email-shaped login that is not the user_email must still log.
	 *
	 * Core passes user_login to retrieve_password. Looking up by email first
	 * returns false when login looks like an email but differs from user_email.
	 */
	public function test_callback_retrieve_password_with_email_shaped_login() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'   => 'john@example.com',
				'user_email'   => 'different@example.com',
				'display_name' => 'EmailLoginGuy',
			)
		);
		$user    = get_user_by( 'id', $user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '%s\'s password was requested to be reset', 'stream' ) ),
				$this->equalTo( array( 'display_name' => 'EmailLoginGuy' ) ),
				$this->equalTo( $user_id ),
				$this->equalTo( 'sessions' ),
				$this->equalTo( 'forgot-password' ),
				$this->equalTo( $user_id )
			);

		do_action( 'retrieve_password', $user->user_login );

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_retrieve_password' ) );
	}

	/**
	 * Unknown login must not call log or trigger property-on-bool warnings.
	 */
	public function test_callback_retrieve_password_with_unknown_user() {
		$this->mock->expects( $this->never() )->method( 'log' );

		do_action( 'retrieve_password', 'nonexistent-user-xyz' );

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_retrieve_password' ) );
	}

	public function test_callback_set_logged_in_cookie() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'TestGuy' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '%s logged in', 'stream' ) ),
				$this->equalTo( array( 'display_name' => 'TestGuy' ) ),
				$this->equalTo( $user_id ),
				$this->equalTo( 'sessions' ),
				$this->equalTo( 'login' ),
				$this->equalTo( $user_id )
			);

		wp_set_auth_cookie( $user_id );

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_set_logged_in_cookie' ) );
	}

	public function test_callback_clear_auth_cookie() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'TestGuy' ) );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		add_filter( 'send_auth_cookies', '__return_false' );
		wp_clear_auth_cookie();

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_clear_auth_cookie' ) );
	}

	public function test_callback_delete_user() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'TestGuy' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'%1$s\'s account was deleted (%2$s)',
						'1: User display name, 2: User roles',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'display_name' => 'TestGuy',
						'roles'        => 'Subscriber',
					)
				),
				$this->equalTo( $user_id ),
				$this->equalTo( 'users' ),
				$this->equalTo( 'deleted' ),
				$this->equalTo( 0 )
			);

		wp_delete_user( $user_id );

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_delete_user' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_deleted_user' ) );
	}

	public function test_callback_deleted_user() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'TestGuy' ) );
		$user    = get_user_by( 'ID', $user_id );

		wp_delete_user( $user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( esc_html__( 'User account #%d was deleted', 'stream' ) ),
				$this->equalTo(
					array(
						'display_name' => $user_id,
						'roles'        => '',
					)
				),
				$this->equalTo( $user_id ),
				$this->equalTo( 'users' ),
				$this->equalTo( 'deleted' ),
				$this->equalTo( 0 )
			);

		do_action( 'deleted_user', $user_id, null, $user );

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_deleted_user' ) );
	}

	public function test_callback_set_user_role() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'TestGuy' ) );
		$user    = get_user_by( 'id', $user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'%1$s\'s role was changed from %2$s to %3$s',
						'1: User display name, 2: Old role, 3: New role',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'display_name' => 'TestGuy',
						'old_role'     => 'Subscriber',
						'new_role'     => 'Editor',
					)
				),
				$this->equalTo( $user_id ),
				$this->equalTo( 'profiles' ),
				$this->equalTo( 'updated' )
			);

		$user->set_role( 'editor' );

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_set_user_role' ) );
	}
}
