<?php
/**
 * Tests for Admin_Screen_Records.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Admin_Screen_Records_Test extends WP_StreamTestCase {

	/**
	 * Holds the admin base class.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Records screen collaborator under test.
	 *
	 * @var Admin_Screen_Records
	 */
	protected $records;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->plugin->admin;
		$this->assertNotEmpty( $this->admin );
		$this->records = $this->admin->records;
	}

	public function test_render_list_table() {
		$this->records->register_list_table();

		ob_start();
		$this->records->render_list_table();
		$html = ob_get_clean();

		$this->assertStringContainsString( '<div class="wrap">', $html );
		$this->assertStringContainsString( 'record-filter-form', $html );
	}

	public function test_register_list_table() {
		$this->records->register_list_table();

		$this->assertNotEmpty( $this->admin->list_table );
		$this->assertInstanceOf( '\WP_Stream\List_Table', $this->admin->list_table );
	}
}
