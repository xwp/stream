<?php
/**
 * WP Integration Test w/ Two Factor
 *
 * Tests for Two Factor connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_WP_Stream_Connector_Two_Factor extends WP_StreamTestCase {

	/**
	 * Our user's id.
	 *
	 * @var string
	 */
	public $user_id;

	/**
	 * Our user.
	 *
	 * @var \WP_User
	 */
	public $user;

	/**
	 * Runs before each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		// Make partial of Connector_Two_Factor class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Two_Factor::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();

		// Allow us to have the Two_Factor_Dummy option.
		remove_all_filters( 'two_factor_providers' );

		if ( empty( $this->user_id ) ) {
			$this->user_id = self::factory()->user->create(
				array(
					'user_login'   => 'testuser',
					'user_role'    => 'administrator',
					'display_name' => 'testuserdisplay',
				)
			);

			$this->user = get_user_by( 'ID', $this->user_id );

			\Two_Factor_Core::enable_provider_for_user( $this->user_id, 'Two_Factor_Dummy' );
		}
	}

	/**
	 * Confirm that Two Factor is installed and active.
	 */
	public function test_two_factor_installed_and_activated() {
		$this->assertTrue( class_exists( 'Two_Factor_Core' ) );
	}

	/**
	 * Test that adding a provider triggers the log.
	 */
	public function test_callback_added_user_meta() {

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					__(
						'Enabled provider: %s',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'provider' => 'Two_Factor_Email',
					)
				),
				$this->user_id,
				'user-settings',
				'enabled'
			);

			\Two_Factor_Core::enable_provider_for_user( $this->user_id, 'Two_Factor_Email' );
	}

	/**
	 * Tests the "callback_save_two_factor_user_authenticated" callback.
	 * This tests the log via doing the action.
	 */
	public function test_callback_two_factor_user_authenticated() {

		wp_set_current_user( $this->user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					__(
						'Authenticated via %s',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'provider' => 'Two_Factor_Dummy',
					)
				),
				$this->user_id,
				'auth',
				'authenticated',
				$this->user_id
			);

			$provider = \Two_Factor_Core::get_provider_for_user( $this->user, 'Two_Factor_Dummy' );

			// We can't test the method so we'll trigger the action.
			do_action( 'two_factor_user_authenticated', $this->user, \Two_Factor_Core::get_provider_for_user( $this->user, $provider ) );
	}

	/**
	 * Test that adding a provider triggers the log.
	 */
	public function test_callback_updated_user_meta() {

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					__(
						'Disabled provider: %s',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'provider' => 'Two_Factor_Dummy',
					),
				),
				$this->user_id,
				'user-settings',
				'disabled'
			);

			\Two_Factor_Core::disable_provider_for_user( $this->user_id, 'Two_Factor_Dummy' );
	}

	/**
	 * Older Two Factor fires this action with only the user argument.
	 */
	public function test_callback_two_factor_user_authenticated_without_provider_logs_unknown_method() {
		// Arrange
		wp_set_current_user( $this->user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					__(
						'Authenticated via %s',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'provider' => __( 'unknown Two Factor method', 'stream' ),
					)
				),
				$this->user_id,
				'auth',
				'authenticated',
				$this->user_id
			);

		// Act
		do_action( 'two_factor_user_authenticated', $this->user );
	}

	/**
	 * Failed 2FA for a known login is logged against that user.
	 */
	public function test_callback_wp_login_failed_known_user_logs_failed_2fa() {
		// Arrange
		$error = new \WP_Error(
			'two_factor_invalid',
			'ERROR: Invalid verification code.'
		);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					__(
						'%1$s Failed 2FA: %2$s %3$s',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'display_name' => $this->user->display_name,
						'code'         => 'two_factor_invalid',
						'error'        => 'ERROR: Invalid verification code.',
					)
				),
				$this->user_id,
				'auth',
				'failed',
				$this->user_id
			);

		// Act
		$this->mock->callback_wp_login_failed( $this->user->user_login, $error );
	}

	/**
	 * Failed 2FA submitted as an email still resolves the user.
	 */
	public function test_callback_wp_login_failed_email_login_logs_failed_2fa() {
		// Arrange
		$email   = 'twofactor-login@example.com';
		$user_id = self::factory()->user->create(
			array(
				'user_email'   => $email,
				'display_name' => 'emailuserdisplay',
			)
		);
		$user    = get_user_by( 'id', $user_id );

		$error = new \WP_Error(
			'two_factor_invalid',
			'ERROR: Invalid verification code.'
		);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					__(
						'%1$s Failed 2FA: %2$s %3$s',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'display_name' => $user->display_name,
						'code'         => 'two_factor_invalid',
						'error'        => 'ERROR: Invalid verification code.',
					)
				),
				$user_id,
				'auth',
				'failed',
				$user_id
			);

		// Act
		$this->mock->callback_wp_login_failed( $email, $error );
	}

	/**
	 * Unknown login must not dereference a false $user (PHP 8 property-on-bool warning).
	 */
	public function test_callback_wp_login_failed_unknown_user_does_not_log() {
		// Arrange
		$error = new \WP_Error(
			'two_factor_invalid',
			'ERROR: Invalid verification code.'
		);

		$this->mock->expects( $this->never() )
			->method( 'log' );

		// Act
		$this->mock->callback_wp_login_failed( 'does-not-exist-xyz', $error );
	}

	/**
	 * Non-Two-Factor login failures are ignored by this connector.
	 */
	public function test_callback_wp_login_failed_non_two_factor_error_does_not_log() {
		// Arrange
		$error = new \WP_Error(
			'incorrect_password',
			'The password you entered is incorrect.'
		);

		$this->mock->expects( $this->never() )
			->method( 'log' );

		// Act
		$this->mock->callback_wp_login_failed( $this->user->user_login, $error );
	}
}
