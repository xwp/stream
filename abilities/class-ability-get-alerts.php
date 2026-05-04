<?php
/**
 * Ability: stream/get-alerts — list configured alert rules.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability_Get_Alerts
 */
class Ability_Get_Alerts extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/get-alerts';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Get Stream Alerts', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'List all configured Stream alert rules. Use the status filter to narrow to enabled or disabled alerts only.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'   => true,
			'idempotent' => true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by alert status.',
					'enum'        => array( 'enabled', 'disabled', 'any' ),
					'default'     => 'any',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_output_schema() {
		return array(
			'type'        => 'array',
			'description' => 'Configured alert rules.',
			'items'       => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'id'         => array( 'type' => 'integer' ),
					'status'     => array(
						'type' => 'string',
						'enum' => array( 'wp_stream_enabled', 'wp_stream_disabled' ),
					),
					'title'      => array( 'type' => 'string' ),
					'alert_type' => array( 'type' => array( 'string', 'null' ) ),
					'alert_meta' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( $input ) {
		$requested = isset( $input['status'] ) ? $input['status'] : 'any';

		switch ( $requested ) {
			case 'enabled':
				$statuses = array( 'wp_stream_enabled' );
				break;
			case 'disabled':
				$statuses = array( 'wp_stream_disabled' );
				break;
			default:
				$statuses = array( 'wp_stream_enabled', 'wp_stream_disabled' );
		}

		$posts = get_posts(
			array(
				'post_type'      => Alerts::POST_TYPE,
				'post_status'    => $statuses,
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'id'         => (int) $post->ID,
				'status'     => (string) $post->post_status,
				'title'      => (string) $post->post_title,
				'alert_type' => get_post_meta( $post->ID, 'alert_type', true ),
				'alert_meta' => (array) get_post_meta( $post->ID, 'alert_meta', true ),
			);
		}

		return $out;
	}
}
