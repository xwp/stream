<?php
namespace WP_Stream;
/**
 * Class Test_Connector_Settings
 *
 * @package WP_Stream
 */
class Test_Connector_Settings extends WP_StreamTestCase {

	/**
	 * Holds the connector settings base class.
	 *
	 * @var Connector_Settings
	 */
	protected $connector;

	public function setUp() {
		parent::setUp();
		$this->connector = new Connector_Settings();

		$this->assertNotEmpty( $this->connector );
	}

	public function test_is_option_ignored() {
		$this->assertTrue( $this->connector->is_option_ignored( '_transient_option_name' ) );
		$this->assertTrue( $this->connector->is_option_ignored( '_site_transient_option_name' ) );
		$this->assertTrue( $this->connector->is_option_ignored( 'option_name$' ) );
		$this->assertTrue( $this->connector->is_option_ignored( 'image_default_link_type' ) );
		$this->assertTrue( $this->connector->is_option_ignored( 'medium_large_size_w' ) );
		$this->assertTrue( $this->connector->is_option_ignored( 'medium_large_size_h' ) );

		$this->assertFalse( $this->connector->is_option_ignored( 'option_site_transient_name' ) );
		$this->assertFalse( $this->connector->is_option_ignored( 'option_transient_name' ) );
		$this->assertFalse( $this->connector->is_option_ignored( 'option_$_name' ) );
		$this->assertFalse( $this->connector->is_option_ignored( 'not_ignored' ) );

		// Test custom ignores.
		$this->assertFalse( $this->connector->is_option_ignored( 'ignore_me' ) );

		add_filter( 'wp_stream_is_option_ignored', function( $is_ignored, $option_name, $default_ignored ) {
			return in_array( $option_name, array_merge( [ 'ignore_me' ], $default_ignored ), true );
		}, 10, 3 );

		$this->assertTrue( $this->connector->is_option_ignored( 'ignore_me' ) );
	}

}
