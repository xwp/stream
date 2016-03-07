<?php
namespace WP_Stream;

class Test_Export extends WP_StreamTestCase {
	/**
	 * Holds the export base class
	 *
	 * @var Export
	 */
	protected $export;

	public function setUp() {
		parent::setUp();

		$this->export = $this->plugin->admin->export;
		$this->assertNotEmpty( $this->export );
	}

	public function test_init() {
		$exporters = $this->export->exporters;
		$this->assertEmpty( $exporters );
	}

	/**
	 * Test registering a invalid class type produces an error
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function test_register_invalid_class() {
		$this->export->register_exporter( new \stdClass );
	}

	/**
	 * Test default classes are only registered on WP_Stream page
	 */
	public function test_register_default_class() {

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
	 * Test for present columns returning
	 */
	 public function test_expand_columns() {
		 $test_data = array(
			 'date'    => '',
			 'summary' => '',
			 'user_id' => '',
			 'context' => '',
			 'action'  => '',
			 'ip'      => '',
		 );
		 $columns = $this->export->render_csv_expand_columns( $test_data );

		 $this->assertArrayHasKey( 'date', $columns );
		 $this->assertArrayHasKey( 'summary', $columns );
		 $this->assertArrayHasKey( 'user_id', $columns );
		 $this->assertArrayHasKey( 'connector', $columns );
		 $this->assertArrayHasKey( 'context', $columns );
		 $this->assertArrayHasKey( 'action', $columns );
		 $this->assertArrayHasKey( 'ip', $columns );
	 }

	 /**
	  * Test no output on normal page load
		*/
		public function test_render_output_blank() {
			$this->export->render_download();
		}

}
