<?php
/**
 * Unit tests for the docs/examples noop connector sample.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Tests Connector_Noop and stream_noop_connector_register().
 */
class Connector_Noop_Unit_Test extends TestCase {

	/**
	 * Absolute path to the sample plugin directory.
	 *
	 * @var string
	 */
	private static $sample_dir;

	protected function set_up() {
		parent::set_up();
		$this->stubTranslationFunctions();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/wordpress/' );
		}

		self::$sample_dir = dirname( __DIR__, 3 ) . '/docs/examples/stream-noop-connector';

		require_once self::$sample_dir . '/class-connector-noop.php';
		require_once self::$sample_dir . '/stream-noop-connector.php';
	}

	public function test_connector_matches_minimum_contract() {
		$connector = new Connector_Noop();

		$this->assertInstanceOf( Connector::class, $connector );
		$this->assertSame( 'noop', $connector->name );
		$this->assertSame( array( 'stream_noop_demo_clicked' ), $connector->actions );
		$this->assertFalse( $connector->register_frontend );
		$this->assertTrue( $connector->register_admin );
		$this->assertSame( 'Noop (example)', $connector->get_label() );
		$this->assertSame( array( 'demo' => 'Demo' ), $connector->get_context_labels() );
		$this->assertSame( array( 'clicked' => 'Clicked' ), $connector->get_action_labels() );
		$this->assertTrue( is_callable( array( $connector, 'callback_stream_noop_demo_clicked' ) ) );
	}

	public function test_register_function_appends_instance_keyed_by_name() {
		$instances = \stream_noop_connector_register( array() );

		$this->assertArrayHasKey( 'noop', $instances );
		$this->assertInstanceOf( Connector_Noop::class, $instances['noop'] );
		$this->assertSame( 'noop', $instances['noop']->name );
	}

	public function test_register_function_preserves_existing_instances() {
		$existing       = $this->createStub( Connector::class );
		$existing->name = 'posts';
		$instances      = \stream_noop_connector_register( array( 'posts' => $existing ) );

		$this->assertSame( $existing, $instances['posts'] );
		$this->assertInstanceOf( Connector_Noop::class, $instances['noop'] );
	}
}
