<?php
/**
 * Tests for Media Connector class callbacks.
 */

namespace WP_Stream;

class Test_WP_Stream_Connector_Media extends WP_StreamTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		// Make partial of Connector_Media class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Media::class )
			->setMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();

		// Require image editor classes.
		require_once ABSPATH . 'wp-includes/class-wp-image-editor.php';
		require_once ABSPATH . 'wp-includes/class-wp-image-editor-gd.php';
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';
	}

	public function test_callback_add_attachment() {
		// Create post for later use.
		$post_id = self::factory()->post->create( array( 'post_title' => 'Test post' ) );

		// Expected log calls.
		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( esc_html__( 'Added "%s" to Media library', 'stream' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array(
								'name'         => 'Document one',
								'parent_title' => null,
								'parent_id'    => 0,
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					$this->equalTo( 'document' ),
					$this->equalTo( 'uploaded' ),
				),
				array(
					$this->equalTo(
						_x(
							'Attached "%1$s" to "%2$s"',
							'1: Attachment title, 2: Parent post title',
							'stream'
						)
					),
					$this->callback(
						function( $subject ) use ( $post_id ) {
							$expected = array(
								'name'         => 'Document one',
								'parent_title' => 'Test post',
								'parent_id'    => $post_id,
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					$this->equalTo( 'document' ),
					$this->equalTo( 'attached' ),
				)
			);

		// Create attachment to trigger callback.
		self::factory()->post->create(
			array(
				'post_title' => 'Document one',
				'post_type'  => 'attachment',
			)
		);

		self::factory()->post->create(
			array(
				'post_title'   => 'Document one',
				'post_type'    => 'attachment',
				'post_content' => 'some description',
				'post_parent'  => $post_id
			)
		);

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_add_attachment' ) );
	}

	public function test_callback_edit_attachment() {
		// Create attachment for later use.
		$attachment_id = self::factory()->post->create(
			array(
				'post_title' => 'Attachment one',
				'post_type'  => 'attachment',
			)
		);

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( esc_html__( 'Updated "%s"', 'stream' ) ),
				$this->equalTo( array( 'name' => 'Document one' ) ),
				$this->equalTo( $attachment_id ),
				$this->equalTo( 'document' ),
				$this->equalTo( 'updated' )
			);

		// Update attachment to trigger callback.
		self::factory()->post->update_object(
			$attachment_id,
			array( 'post_title' => 'Document one' )
		);


		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_edit_attachment' ) );
	}

	public function test_callback_delete_attachment() {
		// Create attachment for later use.
		$attachment_id = self::factory()->post->create(
			array(
				'post_title' => 'Attachment one',
				'post_type'  => 'attachment',
			)
		);

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( esc_html__( 'Deleted "%s"', 'stream' ) ),
				$this->callback(
					function( $subject ) {
						$expected = array(
							'name'      => 'Attachment one',
							'parent_id' => null,
						);
						return $expected === array_intersect_key( $expected, $subject );
					}
				),
				$this->equalTo( $attachment_id ),
				$this->equalTo( 'document' ),
				$this->equalTo( 'deleted' )
			);

		// Delete attachment to trigger callback.
		wp_delete_attachment( $attachment_id, true );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_delete_attachment' ) );
	}

	public function test_callback_wp_save_image_editor_file() {
		// Create attachment for later use.
		$attachment_id = self::factory()->post->create(
			array(
				'post_title' => 'Attachment one',
				'post_type'  => 'attachment',
			)
		);

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( 'Edited image "%s"', 'stream' ) ),
				$this->equalTo(
					array(
						'name'     => 'file.jpg',
						'filename' => 'file.jpg',
						'post_id'  => $attachment_id,
					)
				),
				$this->equalTo( $attachment_id ),
				$this->equalTo( 'document' ),
				$this->equalTo( 'edited' )
			);

		// Simulate editor page save to trigger callback.
		\wp_save_image_file(
			'file.jpg',
			new \WP_Image_Editor_GD( sys_get_temp_dir() . 'file.jpg' ),
			'image/jpeg',
			$attachment_id
		);

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_wp_save_image_editor_file' ) );
	}
}
