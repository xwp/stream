<?php
namespace WP_Stream;

/**
 * Tests for Settings connector class callbacks.
 *
 * @package WP_Stream
 */
class Connector_Settings_Test extends WP_StreamTestCase {
	/**
	 * Runs before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		// Make partial of Connector_Settings class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Settings::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

	public function test_is_option_ignored() {
		$this->assertTrue( $this->mock->is_option_ignored( '_transient_option_name' ) );
		$this->assertTrue( $this->mock->is_option_ignored( '_site_transient_option_name' ) );
		$this->assertTrue( $this->mock->is_option_ignored( 'option_name$' ) );
		$this->assertTrue( $this->mock->is_option_ignored( 'image_default_link_type' ) );
		$this->assertTrue( $this->mock->is_option_ignored( 'medium_large_size_w' ) );
		$this->assertTrue( $this->mock->is_option_ignored( 'medium_large_size_h' ) );

		$this->assertFalse( $this->mock->is_option_ignored( 'option_site_transient_name' ) );
		$this->assertFalse( $this->mock->is_option_ignored( 'option_transient_name' ) );
		$this->assertFalse( $this->mock->is_option_ignored( 'option_$_name' ) );
		$this->assertFalse( $this->mock->is_option_ignored( 'not_ignored' ) );

		// Test custom ignores.
		$this->assertFalse( $this->mock->is_option_ignored( 'ignore_me' ) );

		add_filter(
			'wp_stream_is_option_ignored',
			array( self::class, 'filter_ignore_me_option' ),
			10,
			3
		);

		$this->assertTrue( $this->mock->is_option_ignored( 'ignore_me' ) );
	}

	/**
	 * Mark ignore_me as an ignored option name.
	 *
	 * @param bool   $is_ignored       Whether the option is ignored.
	 * @param string $option_name      Option name.
	 * @param array  $default_ignored  Default ignored option names.
	 * @return bool
	 */
	public static function filter_ignore_me_option( $is_ignored, $option_name, $default_ignored ): bool {
		return in_array( $option_name, array_merge( array( 'ignore_me' ), $default_ignored ), true );
	}

	public function test_callback_updated_option_users_can_register() {
		$add_method    = is_multisite() ? 'add_site_option' : 'add_option';
		$update_method = is_multisite() ? 'update_site_option' : 'update_option';

		call_user_func( $add_method, 'users_can_register', 0 );
		$this->simulate_customize_save();

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%s" setting was updated', 'stream' ) ),
				$this->equalTo(
					array(
						'label'     => 'Membership',
						'option'    => 'users_can_register',
						'context'   => 'settings',
						'old_value' => '0',
						'value'     => '1',
					)
				),
				$this->equalTo( null ),
				$this->equalTo( 'settings' ),
				$this->equalTo( 'updated' )
			);

		call_user_func( $update_method, 'users_can_register', 1 );

		if ( is_multisite() ) {
			$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_site_option' ) );
		} else {
			$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_updated_option' ) );
			$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option' ) );
		}
	}

	public function test_callback_updated_option_permalink_structure() {
		$add_method    = is_multisite() ? 'add_site_option' : 'add_option';
		$update_method = is_multisite() ? 'update_site_option' : 'update_option';

		call_user_func( $add_method, 'permalink_structure', '' );
		$this->simulate_customize_save();

		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%s" setting was updated', 'stream' ) ),
				$this->equalTo(
					array(
						'label'     => 'Permalink Settings',
						'option'    => 'permalink_structure',
						'context'   => 'permalink',
						'old_value' => '',
						'value'     => '/%year%/%postname%/',
					)
				),
				$this->equalTo( null ),
				$this->equalTo( 'permalink' ),
				$this->equalTo( 'updated' )
			);

		call_user_func( $update_method, 'permalink_structure', '/%year%/%postname%/' );

		if ( is_multisite() ) {
			$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_site_option' ) );
		} else {
			$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option_permalink_structure' ) );
		}
	}

	public function test_callback_updated_option_category_base() {
		$add_method    = is_multisite() ? 'add_site_option' : 'add_option';
		$update_method = is_multisite() ? 'update_site_option' : 'update_option';

		call_user_func( $add_method, 'category_base', '' );
		$this->simulate_customize_save();

		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%s" setting was updated', 'stream' ) ),
				$this->equalTo(
					array(
						'label'     => 'Category base',
						'option'    => 'category_base',
						'context'   => 'permalink',
						'old_value' => '',
						'value'     => 'cat/',
					)
				),
				$this->equalTo( null ),
				$this->equalTo( 'permalink' ),
				$this->equalTo( 'updated' )
			);

		call_user_func( $update_method, 'category_base', 'cat/' );

		if ( is_multisite() ) {
			$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_site_option' ) );
		} else {
			$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option_category_base' ) );
		}
	}

	public function test_callback_updated_option_tag_base() {
		$add_method    = is_multisite() ? 'add_site_option' : 'add_option';
		$update_method = is_multisite() ? 'update_site_option' : 'update_option';

		call_user_func( $add_method, 'tag_base', '' );
		$this->simulate_customize_save();

		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%s" setting was updated', 'stream' ) ),
				$this->equalTo(
					array(
						'label'     => 'Tag base',
						'option'    => 'tag_base',
						'context'   => 'permalink',
						'old_value' => '',
						'value'     => 'tag/',
					)
				),
				$this->equalTo( null ),
				$this->equalTo( 'permalink' ),
				$this->equalTo( 'updated' )
			);

		call_user_func( $update_method, 'tag_base', 'tag/' );

		if ( is_multisite() ) {
			$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_site_option' ) );
		} else {
			$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_option_tag_base' ) );
		}
	}

	/**
	 * The mailserver_pass core setting is tracked, so both its previous and new
	 * value would otherwise be persisted verbatim in record metadata --
	 * sanitize_value() only casts to string. The change must still be recorded,
	 * but without the mailbox password.
	 */
	public function test_mailserver_pass_is_redacted() {
		$update_method = is_multisite() ? 'update_site_option' : 'update_option';

		// Core seeds this option as an empty string, so add_option() would be a
		// no-op; write the prior value before the logging expectation is set so
		// both sides of the observed change are non-empty.
		call_user_func( $update_method, 'mailserver_pass', 'old-secret-password' );

		$this->register_writing_options();

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->anything(),
				$this->callback( array( self::class, 'assert_mailserver_pass_redacted' ) )
			)
			->willReturn( true );

		call_user_func( $update_method, 'mailserver_pass', 'new-secret-password' );
	}

	/**
	 * Assert mailserver_pass values are redacted in log context.
	 *
	 * @param mixed $logged Log context argument.
	 * @return bool
	 */
	public static function assert_mailserver_pass_redacted( $logged ): bool {
		if ( ! is_array( $logged ) || 'mailserver_pass' !== ( $logged['option'] ?? null ) ) {
			return false;
		}
		if ( Connector::REDACTED_PLACEHOLDER !== ( $logged['old_value'] ?? null ) ) {
			return false;
		}
		if ( Connector::REDACTED_PLACEHOLDER !== ( $logged['value'] ?? null ) ) {
			return false;
		}
		$serialized = maybe_serialize( $logged );
		return false === strpos( $serialized, 'old-secret-password' )
			&& false === strpos( $serialized, 'new-secret-password' );
	}

	/**
	 * Redaction is keyed on the setting name, so ordinary settings are logged
	 * with their values intact.
	 */
	public function test_non_secret_setting_is_not_redacted() {
		$add_method    = is_multisite() ? 'add_site_option' : 'add_option';
		$update_method = is_multisite() ? 'update_site_option' : 'update_option';

		$this->register_writing_options();

		// This option is seeded by the WP test fixture, so read the existing
		// value rather than assuming add_option() sets it.
		call_user_func( $add_method, 'mailserver_login', 'old-login' );
		$previous = is_multisite() ? get_site_option( 'mailserver_login' ) : get_option( 'mailserver_login' );

		self::$expected_mailserver_login_old = $previous;

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->anything(),
				$this->callback( array( self::class, 'assert_mailserver_login_not_redacted' ) )
			)
			->willReturn( true );

		call_user_func( $update_method, 'mailserver_login', 'new-login' );
	}

	/**
	 * Prior mailserver_login value for non-redaction assertion.
	 *
	 * @var mixed
	 */
	private static $expected_mailserver_login_old;

	/**
	 * Assert mailserver_login values remain readable.
	 *
	 * @param mixed $logged Log context argument.
	 * @return bool
	 */
	public static function assert_mailserver_login_not_redacted( $logged ): bool {
		return is_array( $logged )
			&& ( $logged['old_value'] ?? null ) === self::$expected_mailserver_login_old
			&& 'new-login' === ( $logged['value'] ?? null )
			&& '' !== ( $logged['value'] ?? null );
	}

	/**
	 * Simulate a Customizer settings save request.
	 *
	 * @return void
	 */
	private function simulate_customize_save(): void {
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		do_action( 'customize_save', new \WP_Customize_Manager( array() ) );
	}

	/**
	 * Connector_Settings::callback_update_option() only forwards to the logging
	 * path when the request looks like a real settings save -- either WP-CLI or
	 * inside customize_save. Mirrors the setup already used by
	 * test_callback_updated_option().
	 *
	 * Also registers the mail server options under the "writing" group the way
	 * wp-admin/options.php does, so callback_updated_option() resolves a context
	 * for them.
	 *
	 * @return void
	 */
	private function register_writing_options() {
		global $whitelist_options;

		if ( ! is_array( $whitelist_options ) ) {
			$whitelist_options = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$whitelist_options['writing'] = array_merge( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			isset( $whitelist_options['writing'] ) ? (array) $whitelist_options['writing'] : array(),
			array( 'mailserver_pass', 'mailserver_login' )
		);

		$this->simulate_customize_save();
	}
}
