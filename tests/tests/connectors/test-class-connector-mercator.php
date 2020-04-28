<?php
/**
 * PHP Unit Tests for Connector_Mercator class
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Test_WP_Stream_Connector_Mercator
 *
 * @package WP_Stream
 * @group connectors
 */
class Test_WP_Stream_Connector_Mercator extends WP_StreamTestCase {

	/**
	 * Holds the connector mercator base class
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
	 * Test for get_context_labels()
	 */
	public function test_get_context_labels() {
		//Validate this works for foreign characters as well.
		$this->factory->blog->create( array( 'title' => 'ובזכויותיהם' ) );
		$labels = $this->connector_mercator->get_context_labels();
		$this->assertArrayHasKey( 'ובזכויותיהם', $labels );
		$this->assertArrayHasKey( 'Test Blog', $labels );
	}
}
