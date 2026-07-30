<?php
namespace WP_Stream;

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
}
