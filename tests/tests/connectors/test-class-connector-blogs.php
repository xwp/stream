<?php
namespace WP_Stream;

class Test_WP_Stream_Connector_Blogs extends WP_StreamTestCase {

    /**
	 * Holds the connector blogs base class
	 *
	 * @var Connector_Blogs
	 */
	protected $connector_blogs;

	public function setUp() {
		parent::setUp();

        $this->connector_blogs = new Connector_Blogs;
		$this->assertNotEmpty( $this->connector_blogs );
    }
	
	/**
	 * Test for get_context_labels()
	 */
    public function test_get_context_labels() {
		//Validate this works for foreign characters as well.
		$id = $this->factory->blog->create( array( 'title' => 'ובזכויותיהם' ) );
        $labels = $this->connector_blogs->get_context_labels();
		$this->assertArrayHasKey( 'ובזכויותיהם', $labels );
		$this->assertArrayHasKey( 'Test Blog', $labels );
	}
}
