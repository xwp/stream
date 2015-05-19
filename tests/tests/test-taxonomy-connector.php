<?php
/**
 * Test: WP Stream Taxonomies Connector.
 *
 * Contexts: Taxonomy
 * Actions: Created, Updated, Deleted.
 *
 * @author WP Stream
 * @author Michele Ong <michele@wpstream.com>
 */
class Test_WP_Stream_Connector_Taxonomies extends WP_StreamTestCase {

	/**
	 * Taxonomy Context: Action Create
	 */
	public function test_action_taxonomy_create() {
		$time = time();
		$term_id = 'Term ' . $time;

		// Create a taxonomy term
		$taxonomy = $this->factory->term->create_and_get( array( 'name' => $term_id ) );

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_created_term' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $taxonomy->term_id,
				'connector' => 'taxonomies',
				'context'   => 'post_tag',
				'action'    => 'created',
				'meta'      => array( 'term_name' => $term_id )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Taxonomy Context: Action Update
	 */
	public function test_action_taxonomy_update() {
		$time = time();
		$term_id = 'Term ' . $time;

		// Create a taxonomy term
		$taxonomy = $this->factory->term->create_and_get( array( 'name' => $term_id ) );

		// Update the term
		wp_update_term($taxonomy->term_id, $taxonomy->taxonomy, array('description' => 'Test Description'));

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_edited_term' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $taxonomy->term_id,
				'connector' => 'taxonomies',
				'context'   => 'post_tag',
				'action'    => 'created',
				'meta'      => array( 'term_name' => $term_id )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}

	/**
	 * Taxonomy Context: Action Delete
	 */
	public function test_action_taxonomy_delete() {
		$time = time();
		$term_id = 'Term ' . $time;

		// Create a taxonomy as auto-draft
		$taxonomy = $this->factory->term->create_and_get( array( 'name' => $term_id ) );

		// Delete the term
		wp_delete_term($taxonomy->term_id, $taxonomy->taxonomy);

		// Check if there is a callback called
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_delete_term' ) );

		// Check if the entry is in the database
		sleep(2);
		$result = wp_stream_query(
			array(
				'object_id' => $taxonomy->term_id,
				'connector' => 'taxonomies',
				'context'   => 'post_tag',
				'action'    => 'deleted',
				'meta'      => array( 'term_name' => $term_id )
			)
		);

		// Check if the DB entry is okay
		$this->assertEquals( 1, count( $result ) );
	}
}
