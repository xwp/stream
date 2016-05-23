<?php
namespace WP_Stream;

class Test_Alert extends WP_StreamTestCase {

	function test_construct() {
		$data	= $this->get_dummy_data();
		$alert = new Alert( $data, $this->plugin );

		$this->assertNotEmpty( $alert->ID );
		$this->assertNotEmpty( $alert->date );
		$this->assertNotEmpty( $alert->author );
		$this->assertNotEmpty( $alert->alert_type );
		$this->assertNotEmpty( $alert->alert_type_obj );
		$this->assertNotEmpty( $alert->alert_meta );
	}

	function test_save() {
		$data		 = $this->get_dummy_data();
		$data->ID = null;
		$alert		= new Alert( $data, $this->plugin );

		$post = get_post( $alert->ID );
		$this->assertEquals( $post, 0 );

		$status = $alert->save();
		$this->assertTrue( $status );

		$post = get_post( $alert->ID );
		$this->assertEquals( $alert->ID, $post->ID );
		$this->assertEquals( $alert->date, $post->post_date );
		$this->assertEquals( $alert->author, $post->post_author );

		$fields = array(
			'alert_type',
			'alert_meta',
		);
		foreach ( $fields as $field ) {
			$actual = get_post_meta( $alert->ID, $field, true );
			$this->assertEquals( $alert->$field, $actual );
		}

		$alert->date = date( 'Y-m-d H:i:s', 0 );
		$alert->save();

		$post = get_post( $alert->ID );
		$this->assertEquals( $post->post_date, $alert->date );
		$this->assertEquals( 'Highlight when Administrator activated an item in Plugins.', $post->post_title );
	}

	function get_dummy_data() {
		return (object) array(
			'ID' => 1,
			'date' => date( 'Y-m-d H:i:s' ),
			'author' => '1',
			'alert_type'		 => 'highlight',
			'alert_type_obj' => new Alert_Type_Highlight( $this->plugin ),
			'alert_meta'		 => array(
				'trigger_action'	=> 'activated',
				'trigger_author'	=> 'administrator',
				'trigger_context' => 'plugins',
			),
		);
	}
}
