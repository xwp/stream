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
class Connector_GravityForms_Test extends WP_StreamTestCase {

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
			->onlyMethods( array( 'log' ) )
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
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->anything(),
				$this->callback( array( self::class, 'assert_captcha_private_key_redacted' ) )
			)
			->willReturn( true );

		$this->mock->check( 'rg_gforms_captcha_private_key', 'old-private-key', 'new-private-key' );
	}

	/**
	 * Assert reCAPTCHA private key log context is redacted.
	 *
	 * @param mixed $logged Log context argument.
	 * @return bool
	 */
	public static function assert_captcha_private_key_redacted( $logged ): bool {
		if ( ! is_array( $logged ) || 'rg_gforms_captcha_private_key' !== ( $logged['option'] ?? null ) ) {
			return false;
		}
		if ( Connector::REDACTED_PLACEHOLDER !== ( $logged['old_value'] ?? null ) ) {
			return false;
		}
		if ( Connector::REDACTED_PLACEHOLDER !== ( $logged['new_value'] ?? null ) ) {
			return false;
		}
		$serialized = maybe_serialize( $logged );
		return false === strpos( $serialized, 'old-private-key' )
			&& false === strpos( $serialized, 'new-private-key' );
	}

	/**
	 * The matching public key is not a credential and must stay readable, so
	 * the audit log remains useful.
	 */
	public function test_captcha_public_key_is_not_redacted() {
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->anything(),
				$this->callback( array( self::class, 'assert_captcha_public_key_not_redacted' ) )
			)
			->willReturn( true );

		$this->mock->check( 'rg_gforms_captcha_public_key', 'old-public', 'new-public' );
	}

	/**
	 * Assert reCAPTCHA public key values remain readable.
	 *
	 * @param mixed $logged Log context argument.
	 * @return bool
	 */
	public static function assert_captcha_public_key_not_redacted( $logged ): bool {
		return is_array( $logged )
			&& 'old-public' === ( $logged['old_value'] ?? null )
			&& 'new-public' === ( $logged['new_value'] ?? null );
	}

	/**
	 * The license key is a reusable vendor credential. Only the fact of the
	 * change is recorded; the message still distinguishes set from removed.
	 */
	public function test_license_key_is_redacted_on_update() {
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->callback( array( self::class, 'assert_message_contains_updated' ) ),
				$this->callback( array( self::class, 'assert_license_key_redacted' ) )
			)
			->willReturn( true );

		$this->mock->check_rg_gforms_key( 'old-license', 'new-license' );
	}

	/**
	 * Assert log message reports an update.
	 *
	 * @param mixed $message Log message argument.
	 * @return bool
	 */
	public static function assert_message_contains_updated( $message ): bool {
		return is_string( $message ) && false !== strpos( $message, 'updated' );
	}

	/**
	 * Assert license key log context is redacted.
	 *
	 * @param mixed $logged Log context argument.
	 * @return bool
	 */
	public static function assert_license_key_redacted( $logged ): bool {
		if ( ! is_array( $logged ) || 'rg_gforms_key' !== ( $logged['option'] ?? null ) ) {
			return false;
		}
		if ( Connector::REDACTED_PLACEHOLDER !== ( $logged['old_value'] ?? null ) ) {
			return false;
		}
		if ( Connector::REDACTED_PLACEHOLDER !== ( $logged['new_value'] ?? null ) ) {
			return false;
		}
		$serialized = maybe_serialize( $logged );
		return false === strpos( $serialized, 'old-license' )
			&& false === strpos( $serialized, 'new-license' );
	}

	/**
	 * Clearing the license key must still be reported as a deletion, proving
	 * redaction did not disturb the status derivation.
	 */
	public function test_license_key_deletion_still_reported() {
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->callback( array( self::class, 'assert_message_contains_deleted' ) )
			)
			->willReturn( true );

		$this->mock->check_rg_gforms_key( 'old-license', '' );
	}

	/**
	 * Assert log message reports a deletion.
	 *
	 * @param mixed $message Log message argument.
	 * @return bool
	 */
	public static function assert_message_contains_deleted( $message ): bool {
		return is_string( $message ) && false !== strpos( $message, 'deleted' );
	}
}
