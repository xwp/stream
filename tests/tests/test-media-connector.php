<?php
/**
 * Test: WP Stream Media Connector.
 *
 * Contexts: Audio, Video, Document, Spreadsheet, Interactive, Text, Archive, Code.
 * Actions: Attached, Uploaded, Updated, Deleted.
 *
 * @author WP Stream
 * @author Michele Ong <michele@wpstream.com>
 */
class Test_WP_Stream_Connector_Media extends WP_StreamTestCase {

	private $urls = array(
		'audio' => 'files/test.mp3',
		'video' => 'files/test.mp4',
		'document' => 'files/test.docx',
		'spreadsheet' => 'files/test.xlsx',
		'interactive' => '', // TODO
		'text' => 'files/test.txt',
		'archive' => 'files/test.zip',
		'code' => '' // TODO
	);

	/**
	 * Media Context: Action Upload
	 */
	public function test_action_media_upload() {
		foreach ($this->urls as $key => $value) {
			if ($value !== '') {
				list($attachment_id, $name) = $this->upload_file($value, 0);

				// Check if there is a callback called
				$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_add_attachment' ) );

				// Check if the entry is in the database
				sleep(2);
				$result = wp_stream_query(
					array(
						'object_id' => $attachment_id,
						'connector' => 'media',
						'context'   => $key,
						'action'    => 'uploaded',
						'meta'      => array('name' => $name)
					)
				);

				// Check if the DB entry is okay
				$this->assertEquals( 1, count( $result ) );
			}
		}
	}

	/**
	 * Media Context: Action Attach
	 */
	public function test_action_media_attach() {

		// Create a post
		$post_date = current_time( 'mysql' );
		$post_id = $this->factory->post->create( array( 'post_status' => 'auto-draft', 'post_date' => $post_date, 'post_date_gmt' => get_gmt_from_date( $post_date ) ) );

		foreach ($this->urls as $key => $value) {
			if ($value !== '') {
				list($attachment_id, $name) = $this->upload_file($value, $post_id);

				// Check if there is a callback called
				$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_add_attachment' ) );

				// Check if the entry is in the database
				sleep(2);
				$result = wp_stream_query(
					array(
						'object_id' => $attachment_id,
						'connector' => 'media',
						'context'   => $key,
						'action'    => 'attached',
						'meta'      => array('name' => $name)
					)
				);

				// Check if the DB entry is okay
				$this->assertEquals( 1, count( $result ) );
			}
		}
	}

	/**
	 * Media Context: Action Updated
	 */
	public function test_action_media_updated() {
		$time = time();

		foreach ($this->urls as $key => $value) {
			if ($value !== '') {
				list($attachment_id, $name) = $this->upload_file($value, 0);

				// Update the attachment
				$attachment = get_post( $attachment_id );
				$attachment->post_title .= ' ' . $time;

				// Save attachment
				wp_update_post( $attachment );

				// Check if there is a callback called
				$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_edit_attachment' ) );

				// Check if the entry is in the database
				sleep(2);
				$result = wp_stream_query(
					array(
						'object_id' => $attachment_id,
						'connector' => 'media',
						'context'   => $key,
						'action'    => 'updated',
						'meta'      => array('name' => $attachment->post_title)
					)
				);

				// Check if the DB entry is okay
				$this->assertEquals( 1, count( $result ) );
			}
		}
	}

	/**
	 * Media Context: Action Deleted
	 */
	public function test_action_media_deleted() {
		foreach ($this->urls as $key => $value) {
			if ($value !== '') {
				list($attachment_id, $name) = $this->upload_file($value, 0);

				// Delete the attachment
				wp_delete_attachment($attachment_id);

				// Check if there is a callback called
				$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_delete_attachment' ) );

				// Check if the entry is in the database
				sleep(2);
				$result = wp_stream_query(
					array(
						'object_id' => $attachment_id,
						'connector' => 'media',
						'context'   => $key,
						'action'    => 'deleted',
						'meta'      => array('name' => $name)
					)
				);

				// Check if the DB entry is okay
				$this->assertEquals( 1, count( $result ) );
			}
		}
	}

	private function upload_file($file, $post_id) {
		$time = time();
		$filename = basename($file);
		$file = dirname(__FILE__) . '/' . $file;
		$name = $filename . ' ' . $time;

		$upload_file = wp_upload_bits($filename, null, file_get_contents($file));
		if (!$upload_file['error']) {
			$wp_filetype = wp_check_filetype($filename, null );
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_parent' => $post_id,
				'post_title' => $name,
				'post_content' => '',
				'post_status' => 'inherit'
			);
			$attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $post_id );
			if (!is_wp_error($attachment_id)) {
				require_once(ABSPATH . "wp-admin" . '/includes/image.php');
				$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
				wp_update_attachment_metadata( $attachment_id,  $attachment_data );
			}

			return array($attachment_id, $name);
		}

		return null;
	}
}
