<?php
/**
 * Tests for Mercator connector class callbacks.
 *
 * @package WP_Stream
 */
namespace WP_Stream;

class Test_WP_Stream_Connector_Mercator extends WP_StreamTestCase {

	/**
	 * Holds the connector mercator base class.
	 *
	 * @var Connector_Mercator
	 */
	protected $connector_mercator;

	public function setUp() {
		parent::setUp();

		$this->connector_mercator = new Connector_Mercator;
		$this->assertNotEmpty( $this->connector_mercator );
	}

	/**
	 * Test for get_context_labels().
	 *
	 * @group ms-required
	 */
	public function test_get_context_labels() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}
		// Validate this works for foreign characters as well.
		$id = $this->factory->blog->create( array( 'title' => 'ובזכויותיהם' ) );
		$labels = $this->connector_mercator->get_context_labels();
		$this->assertArrayHasKey( 'blog-1', $labels );
		$this->assertArrayHasKey( 'blog-' . $id , $labels );
	}
}
