<?php
/**
 * Tests for Media Connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Connector_Media_Test extends WP_StreamTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp(): void {
		parent::setUp();

		// Make partial of Connector_Media class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Media::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();

		// Require image editor classes.
		require_once ABSPATH . 'wp-includes/class-wp-image-editor.php';
		require_once ABSPATH . 'wp-includes/class-wp-image-editor-gd.php';
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';
	}

	public function test_callback_add_attachment() {
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( esc_html__( 'Added "%s" to Media library', 'stream' ) ),
				$this->callback( array( self::class, 'assert_add_attachment_no_parent_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'document' ),
				$this->equalTo( 'uploaded' )
			);

		self::factory()->post->create(
			array(
				'post_title' => 'Document one',
				'post_type'  => 'attachment',
			)
		);

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_add_attachment' ) );
	}

	/**
	 * Assert uploaded-attachment context without a parent.
	 *
	 * @param mixed $context Log context argument.
	 * @return bool
	 */
	public static function assert_add_attachment_no_parent_context( $context ): bool {
		return is_array( $context )
			&& 'Document one' === ( $context['name'] ?? null )
			&& 'Unidentifiable post' === ( $context['parent_title'] ?? null )
			&& 0 === ( $context['parent_id'] ?? null );
	}

	public function test_callback_add_attachment_with_parent() {
		$post_id                  = self::factory()->post->create( array( 'post_title' => 'Test post' ) );
		self::$expected_parent_id = $post_id;

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'Attached "%1$s" to "%2$s"',
						'1: Attachment title, 2: Parent post title',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_add_attachment_with_parent_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'document' ),
				$this->equalTo( 'attached' )
			);

		self::factory()->post->create(
			array(
				'post_title'   => 'Document one',
				'post_type'    => 'attachment',
				'post_content' => 'some description',
				'post_parent'  => $post_id,
			)
		);

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_add_attachment' ) );
	}

	/**
	 * Expected parent post ID for attachment context assertions.
	 *
	 * @var int
	 */
	private static $expected_parent_id = 0;

	/**
	 * Assert attached-to-parent context.
	 *
	 * @param mixed $context Log context argument.
	 * @return bool
	 */
	public static function assert_add_attachment_with_parent_context( $context ): bool {
		return is_array( $context )
			&& 'Document one' === ( $context['name'] ?? null )
			&& 'Test post' === ( $context['parent_title'] ?? null )
			&& ( $context['parent_id'] ?? null ) === self::$expected_parent_id;
	}

	public function test_callback_add_attachment_with_invalid_parent() {
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'Attached "%1$s" to "%2$s"',
						'1: Attachment title, 2: Parent post title',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_add_attachment_invalid_parent_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'document' ),
				$this->equalTo( 'attached' )
			);

		self::factory()->post->create(
			array(
				'post_title'   => 'Document one',
				'post_type'    => 'attachment',
				'post_content' => 'some description',
				'post_parent'  => 42,
			)
		);

		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_add_attachment' ) );
	}

	/**
	 * Assert attached-to-invalid-parent context.
	 *
	 * @param mixed $context Log context argument.
	 * @return bool
	 */
	public static function assert_add_attachment_invalid_parent_context( $context ): bool {
		return is_array( $context )
			&& 'Document one' === ( $context['name'] ?? null )
			&& 'Unidentifiable post' === ( $context['parent_title'] ?? null )
			&& 42 === ( $context['parent_id'] ?? null );
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
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_edit_attachment' ) );
	}

	public function test_callback_delete_attachment() {
		// Create attachment for later use.
		$attachment_id = self::factory()->post->create(
			array(
				'post_title' => 'Attachment one',
				'post_type'  => 'attachment',
			)
		);
		$attachment    = get_post( $attachment_id );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( esc_html__( 'Deleted "%s"', 'stream' ) ),
				$this->equalTo(
					array(
						'name'      => 'Attachment one',
						'parent_id' => null,
						'url'       => $attachment->guid,
					)
				),
				$this->equalTo( $attachment_id ),
				$this->equalTo( 'document' ),
				$this->equalTo( 'deleted' )
			);

		// Delete attachment to trigger callback.
		wp_delete_attachment( $attachment_id, true );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_delete_attachment' ) );
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
						'name'     => 'icon-128x128.png',
						'filename' => WP_STEAM_TESTDATA . '/tmp/icon-128x128.png',
						'post_id'  => $attachment_id,
					)
				),
				$this->equalTo( $attachment_id ),
				$this->equalTo( 'document' ),
				$this->equalTo( 'edited' )
			);

		$image = new \WP_Image_Editor_GD( WP_STEAM_TESTDATA . '/images/icon-128x128.png' );
		$image->load();

		// Simulate editor page save to trigger callback.
		\wp_save_image_file(
			WP_STEAM_TESTDATA . '/tmp/icon-128x128.png',
			$image,
			'image/png',
			$attachment_id
		);

		// Check callback test action.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_wp_save_image_editor_file' ) );
	}
}
