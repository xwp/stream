<?php
namespace WP_Stream;

require_once __DIR__ . '/../fake-gflogging.php';

/**
 * Tests for the Gravity Forms connector.
 *
 * Gravity Forms itself is not installed in the test environment, so these
 * exercise the logging entry points directly rather than through the plugin's
 * hooks. That is sufficient for the redaction contract, which depends only on
 * the option name.
 *
 * @package WP_Stream
 */
class Test_Connector_GravityForms extends WP_StreamTestCase {

	/**
	 * Mocked connector with log() stubbed.
	 *
	 * @var Connector_GravityForms
	 */
	protected $mock;

	/**
	 * Runs before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		$this->mock = $this->getMockBuilder( Connector_GravityForms::class )
			->setMethods( array( 'log' ) )
			->getMock();

		// Populates $options, which check() consults. Safe without the plugin
		// present: register() only builds that array.
		$this->mock->register();

		// Empty by default, so get_logging_plugin_label() falls back to the
		// raw slug in every test except the one that populates this.
		\GFLogging::$supported_plugins = array();
	}

	/**
	 * The reCAPTCHA private key is a tracked option, so check() would otherwise
	 * persist both the previous and replacement secret in record metadata,
	 * where the record-detail ability exposes it to any view_stream caller.
	 */
	public function test_captcha_private_key_is_redacted() {
		$logged = array();
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args ) use ( &$logged ) {
					$logged = $args;
					return true;
				}
			);

		$this->mock->check( 'rg_gforms_captcha_private_key', 'old-private-key', 'new-private-key' );

		$this->assertSame(
			'rg_gforms_captcha_private_key',
			$logged['option'],
			'The setting change must still be recorded.'
		);
		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $logged['old_value'] );
		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $logged['new_value'] );

		$serialized = maybe_serialize( $logged );
		$this->assertStringNotContainsString( 'old-private-key', $serialized );
		$this->assertStringNotContainsString( 'new-private-key', $serialized );
	}

	/**
	 * The matching public key is not a credential and must stay readable, so
	 * the audit log remains useful.
	 */
	public function test_captcha_public_key_is_not_redacted() {
		$logged = array();
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args ) use ( &$logged ) {
					$logged = $args;
					return true;
				}
			);

		$this->mock->check( 'rg_gforms_captcha_public_key', 'old-public', 'new-public' );

		$this->assertSame( 'old-public', $logged['old_value'] );
		$this->assertSame( 'new-public', $logged['new_value'] );
	}

	/**
	 * The license key is a reusable vendor credential. Only the fact of the
	 * change is recorded; the message still distinguishes set from removed.
	 */
	public function test_license_key_is_redacted_on_update() {
		$message = '';
		$logged  = array();
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $msg, $args ) use ( &$logged, &$message ) {
					$message = $msg;
					$logged  = $args;
					return true;
				}
			);

		$this->mock->check_rg_gforms_key( 'old-license', 'new-license' );

		$this->assertSame( 'rg_gforms_key', $logged['option'] );
		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $logged['old_value'] );
		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $logged['new_value'] );

		// The update/delete distinction is derived before redaction, so it must
		// still report an update here.
		$this->assertStringContainsString( 'updated', $message );

		$serialized = maybe_serialize( $logged );
		$this->assertStringNotContainsString( 'old-license', $serialized );
		$this->assertStringNotContainsString( 'new-license', $serialized );
	}

	/**
	 * Clearing the license key must still be reported as a deletion, proving
	 * redaction did not disturb the status derivation.
	 */
	public function test_license_key_deletion_still_reported() {
		$message = '';
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $msg ) use ( &$message ) {
					$message = $msg;
					return true;
				}
			);

		$this->mock->check_rg_gforms_key( 'old-license', '' );

		$this->assertStringContainsString( 'deleted', $message );
	}

	/**
	 * Toggling the main "Enable Logging" setting must be reported, so an
	 * audit log user can tell who enabled it and when.
	 */
	public function test_logging_main_toggle_is_reported() {
		$action = '';
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args, $object_id, $context, $logged_action ) use ( &$action ) {
					$action = $logged_action;
					return true;
				}
			);

		$this->mock->check_gform_enable_logging( false, true );

		$this->assertSame( 'activated', $action );
	}

	/**
	 * Gravity Forms enables every add-on's logger as a side effect of the
	 * main toggle, in the same request. That cascade must not also be
	 * reported, or one user action would produce a confusing pile of
	 * per-add-on records alongside the single main-toggle record.
	 */
	public function test_addon_cascade_from_main_toggle_is_not_reported() {
		$this->mock->expects( $this->once() )->method( 'log' );

		$this->mock->check_gform_enable_logging( false, true );

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			array(
				'gravityforms'       => array( 'enable' => '0' ),
				'gravityformsstripe' => array( 'enable' => '0' ),
			),
			array(
				'gravityforms'       => array( 'enable' => '1' ),
				'gravityformsstripe' => array( 'enable' => '1' ),
			)
		);
	}

	/**
	 * Enabling logging for one add-on must be reported as 'activated'.
	 */
	public function test_addon_logging_enabled_is_reported() {
		$action = '';
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args, $object_id, $context, $logged_action ) use ( &$action ) {
					$action = $logged_action;
					return true;
				}
			);

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			array( 'gravityforms' => array( 'enable' => '0' ) ),
			array( 'gravityforms' => array( 'enable' => '1' ) )
		);

		$this->assertSame( 'activated', $action );
	}

	/**
	 * Disabling logging for one add-on must be reported as 'deactivated'.
	 */
	public function test_addon_logging_disabled_is_reported() {
		$action = '';
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args, $object_id, $context, $logged_action ) use ( &$action ) {
					$action = $logged_action;
					return true;
				}
			);

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			array( 'gravityforms' => array( 'enable' => '1' ) ),
			array( 'gravityforms' => array( 'enable' => '0' ) )
		);

		$this->assertSame( 'deactivated', $action );
	}

	/**
	 * An unchanged add-on entry must not produce a record, or resubmitting
	 * the whole settings form would spam the log for every untouched add-on.
	 */
	public function test_addon_logging_unchanged_is_not_reported() {
		$this->mock->expects( $this->never() )->method( 'log' );

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			array( 'gravityforms' => array( 'enable' => '1' ) ),
			array( 'gravityforms' => array( 'enable' => '1' ) )
		);
	}

	/**
	 * Each changed add-on must be its own record, since the whole settings
	 * form (Core plus every add-on) saves together in one option update.
	 */
	public function test_addon_logging_multiple_changes_are_reported_separately() {
		$this->mock->expects( $this->exactly( 2 ) )->method( 'log' );

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			array(
				'gravityforms'       => array( 'enable' => '0' ),
				'gravityformsstripe' => array( 'enable' => '0' ),
			),
			array(
				'gravityforms'       => array( 'enable' => '1' ),
				'gravityformsstripe' => array( 'enable' => '1' ),
			)
		);
	}

	/**
	 * The option's first-ever save (add_option, so the old value is entirely
	 * absent) must still be reported, without a PHP notice from the missing
	 * old entry.
	 */
	public function test_addon_logging_first_save_is_reported_without_notice() {
		$this->mock->expects( $this->once() )->method( 'log' );

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			null,
			array( 'gravityforms' => array( 'enable' => '1' ) )
		);
	}

	/**
	 * The suppression flag must only skip the write right after a
	 * main-toggle change, not a later unrelated one in the same process.
	 */
	public function test_addon_logging_suppression_is_consumed_once() {
		$this->mock->expects( $this->exactly( 2 ) )->method( 'log' );

		$this->mock->check_gform_enable_logging( false, true );

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			array( 'gravityforms' => array( 'enable' => '0' ) ),
			array( 'gravityforms' => array( 'enable' => '1' ) )
		);

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			array( 'gravityformsstripe' => array( 'enable' => '1' ) ),
			array( 'gravityformsstripe' => array( 'enable' => '0' ) )
		);
	}

	/**
	 * Deleting the whole logging configuration must still be reported.
	 */
	public function test_addon_logging_deletion_is_reported() {
		$action = '';
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args, $object_id, $context, $logged_action ) use ( &$action ) {
					$action = $logged_action;
					return true;
				}
			);

		$this->mock->check_gravityformsaddon_gravityformslogging_settings( null, null );

		$this->assertSame( 'deleted', $action );
	}

	/**
	 * An unmapped slug must fall back to the raw slug.
	 */
	public function test_addon_logging_label_falls_back_to_slug_when_unmapped() {
		$message = '';
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $msg ) use ( &$message ) {
					$message = $msg;
					return true;
				}
			);

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			array( 'gravityformsstripe' => array( 'enable' => '0' ) ),
			array( 'gravityformsstripe' => array( 'enable' => '1' ) )
		);

		$this->assertStringContainsString( 'gravityformsstripe', $message );
	}

	/**
	 * A mapped slug must be reported by its real name, not the raw slug.
	 */
	public function test_addon_logging_label_uses_supported_plugin_name() {
		\GFLogging::$supported_plugins = array( 'gravityformsstripe' => 'Gravity Forms Stripe Add-On' );

		$message = '';
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $msg ) use ( &$message ) {
					$message = $msg;
					return true;
				}
			);

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			array( 'gravityformsstripe' => array( 'enable' => '0' ) ),
			array( 'gravityformsstripe' => array( 'enable' => '1' ) )
		);

		$this->assertStringContainsString( 'Gravity Forms Stripe Add-On', $message );
		$this->assertStringNotContainsString( 'gravityformsstripe', $message );
	}

	/**
	 * A plugin name containing "%" must reach the message escaped.
	 */
	public function test_addon_logging_label_is_escaped() {
		\GFLogging::$supported_plugins = array( 'gravityformsstripe' => 'Stripe 50% Off Add-On' );

		$message = '';
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $msg ) use ( &$message ) {
					$message = $msg;
					return true;
				}
			);

		$this->mock->check_gravityformsaddon_gravityformslogging_settings(
			array( 'gravityformsstripe' => array( 'enable' => '0' ) ),
			array( 'gravityformsstripe' => array( 'enable' => '1' ) )
		);

		$this->assertStringContainsString( 'Stripe 50%% Off Add-On', $message );
		$this->assertStringNotContainsString( 'Stripe 50% Off', $message );
	}
}
