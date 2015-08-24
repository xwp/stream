<?php
namespace WP_Stream;

class Test_Connectors extends WP_StreamTestCase {
	/**
	 * Holds the connectors base class
	 *
	 * @var Connectors
	 */
	protected $connectors;

	public function setUp() {
		parent::setUp();

		$this->connectors = $this->plugin->connectors;
		$this->assertNotEmpty( $this->connectors );
	}

	public function test_construct() {
		$this->assertNotEmpty( $this->connectors->plugin );
		$this->assertInstanceOf( '\WP_Stream\Plugin', $this->connectors->plugin );
	}

	public function test_load_connectors() {
		$this->connectors->load_connectors();
		$this->assertNotEmpty( $this->connectors->connectors );
		$this->assertNotEmpty( $this->connectors->contexts );
		$this->assertNotEmpty( $this->connectors->term_labels['stream_connector'] );
		$this->assertNotEmpty( $this->connectors->term_labels['stream_context'] );
		$this->assertNotEmpty( $this->connectors->term_labels['stream_action'] );

		ob_start();
		$this->plugin->admin->admin_notices();
		$notices = ob_get_clean();

		$this->assertEmpty( $notices );
	}
}
