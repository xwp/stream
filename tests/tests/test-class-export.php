<?php
namespace WP_Stream;

class Test_Export extends WP_StreamTestCase {
	/**
	 * Holds the export base class
	 *
	 * @var Export
	 */
	protected $export;

	/**
	 * Set up for tests
	 */
	public function setUp() {
		parent::setUp();
		$this->export = $this->plugin->admin->export;
		$this->assertNotEmpty( $this->export );
		$this->assertEmpty( $this->export->exporters );
	}

	/**
	 * Test class constructor
	 */
	public function test_construct() {
		$this->assertNotEmpty( $this->export->plugin );
		$this->assertNotEmpty( $this->export->admin );

		$exporters = $this->export->exporters;
		$this->assertEmpty( $exporters );
	}

	/**
	 * Test that render download uses selected renderer
	 */
	public function test_render_download() {

		$_GET['output'] = 'csv';
		$this->export->register_exporter( new Exporter_CSV );

		ob_start();
		$this->export->render_download();
		$output = ob_get_clean();

		$this->assertNotEmpty( $output );
		$this->assertStringStartsWith( 'Date,Summary,User,Connector,Context,Action,IP Address', $output );

		$this->export->exporters = array(); // Clean up.
		unset( $_GET['output'] );
	}

	/**
	 * Test no output on normal page load
	 */
	public function test_render_output_blank() {
		ob_start();
		$this->export->render_download();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test that record building grab correct columns
	 */
	public function test_build_record() {

		$columns = array( 'connector' => '' );
		$data    = $this->dummy_stream_data;
		$output  = $this->export->build_record( $data, $columns );

		$this->assertNotEmpty( $output );
		$this->assertArrayHasKey( 'connector', $output );
		$this->assertEquals( $data['connector'], $output['connector'] );

		$columns = array( 'context' => '' );
		$output  = $this->export->build_record( $data, $columns );

		$this->assertNotEmpty( $output );
		$this->assertArrayNotHasKey( 'connector', $output );
		$this->assertArrayHasKey( 'context', $output );
		$this->assertEquals( $data['context'], $output['context'] );

	}

	/**
	 * Test pagination limit is increased
	 */
	public function test_disable_paginate() {
		$limit = $this->export->disable_paginate( 0 );
		$this->assertEquals( $limit, 10000 );
	}

	/**
	 * Test for present columns returning
	 */
	public function test_expand_columns() {
		$test_data = array(
		 'date'		=> '',
		 'summary' => '',
		 'user_id' => '',
		 'context' => '',
		 'action'	=> '',
		 'ip'			=> '',
		);
		$columns = $this->export->expand_columns( $test_data );

		$this->assertArrayHasKey( 'date', $columns );
		$this->assertArrayHasKey( 'summary', $columns );
		$this->assertArrayHasKey( 'user_id', $columns );
		$this->assertArrayHasKey( 'connector', $columns );
		$this->assertArrayHasKey( 'context', $columns );
		$this->assertArrayHasKey( 'action', $columns );
		$this->assertArrayHasKey( 'ip', $columns );
	}

	/**
	 * Test register a valid class adds it to the list.
	 */
	public function test_register_exporter() {
		$this->assertEmpty( $this->export->exporters );
		$this->export->register_exporter( new Exporter_CSV );
		$this->assertNotEmpty( $this->export->exporters );

		$this->export->exporters = array(); // Clean up.
	}

	/**
	 * Test registering a invalid class type produces an error
	 *
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function test_register_exporter_invalid_class() {
		$this->export->register_exporter( new \stdClass );
	}

	/**
	 * Valid classes added are returned.
	 */
	public function test_get_exporters() {
		$this->assertEmpty( $this->export->get_exporters() );
		$this->export->register_exporter( new Exporter_CSV );

		$exporters = $this->export->get_exporters();
		$this->assertNotEmpty( $exporters );
		$this->assertArrayHasKey( 'csv', $exporters );

		$this->export->exporters = array(); // Clean up.
	}

	/**
	 * Test default classes are only registered on WP_Stream page
	 */
	public function test_register_default_exporters() {

		// Test registration on stream page.
		$_GET['page'] = 'wp_stream';
		$export = new Export( $this->plugin );

		$exporters = $export->get_exporters();
		$this->assertNotEmpty( $exporters );
		remove_all_actions( 'register_stream_exporters' ); // Clean up.

		// Test no registration on other pages.
		$_GET['page'] = '';
		$export = new Export( $this->plugin );

		$exporters = $export->get_exporters();
		$this->assertEmpty( $exporters );
	}

	/**
	 * Return dummy stream data
	 */
	private function dummy_stream_data() {
		return array(
			'object_id' => null,
			'site_id' => '1',
			'blog_id' => get_current_blog_id(),
			'user_id' => '1',
			'user_role' => 'administrator',
			'created' => date( 'Y-m-d H:i:s' ),
			'summary' => '"Hello Dave" plugin activated',
			'ip' => '192.168.0.1',
			'connector' => 'installer',
			'context' => 'plugins',
			'action' => 'activated',
		);
	}
}
