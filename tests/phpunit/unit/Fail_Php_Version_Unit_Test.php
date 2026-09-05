<?php
/**
 * Unit tests for the PHP minimum version gate in stream.php.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Tests wp_stream_fail_php_version() and admin-notice hook registration.
 */
class Fail_Php_Version_Unit_Test extends TestCase {

	/**
	 * Whether stream.php has been loaded for callback tests in this process.
	 *
	 * @var bool
	 */
	private static $stream_loaded = false;

	protected function set_up() {
		parent::set_up();
		$this->stubTranslationFunctions();
	}

	/**
	 * Ensure the fail gate hooks admin notices, not shutdown.
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_fail_gate_registers_admin_notices_not_shutdown() {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/wordpress/' );
		}

		define( 'WP_STREAM_MIN_PHP_VERSION', '99.0' );

		Actions\expectAdded( 'admin_notices' )
			->once()
			->with( 'wp_stream_fail_php_version' );

		Actions\expectAdded( 'network_admin_notices' )
			->once()
			->with( 'wp_stream_fail_php_version' );

		require dirname( __DIR__, 3 ) . '/stream.php';
	}

	public function test_fail_php_version_outputs_notice_for_capable_user() {
		self::load_stream_with_fail_gate();

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
		Functions\when( 'plugin_basename' )->justReturn( 'stream/stream.php' );
		Functions\when( 'wpautop' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();

		ob_start();
		wp_stream_fail_php_version();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice notice-error', $output );
		$this->assertStringContainsString( WP_STREAM_MIN_PHP_VERSION, $output );
		$this->assertStringContainsString( 'Stream requires PHP version', $output );
	}

	public function test_fail_php_version_is_silent_for_incapable_user() {
		self::load_stream_with_fail_gate();

		Functions\when( 'current_user_can' )->justReturn( false );

		ob_start();
		wp_stream_fail_php_version();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Load stream.php with a minimum PHP version above the runtime so the fail gate runs.
	 *
	 * @return void
	 */
	private static function load_stream_with_fail_gate() {
		if ( self::$stream_loaded ) {
			return;
		}

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/wordpress/' );
		}

		if ( ! defined( 'WP_STREAM_MIN_PHP_VERSION' ) ) {
			define( 'WP_STREAM_MIN_PHP_VERSION', '99.0' );
		}

		require dirname( __DIR__, 3 ) . '/stream.php';

		self::$stream_loaded = true;
	}
}
