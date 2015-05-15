<?php

class WP_Stream_Connector_Stream extends WP_Stream_Connector {

	/**
	 * Connector name/slug
	 *
	 * @var string
	 */
	public static $name = 'stream';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'wp_stream_site_connected',
		'wp_stream_site_disconnected',
		'wp_stream_log_data',
	);

	/**
	 * Return translated connector label
	 *
	 * @return string
	 */
	public static function get_label() {
		return esc_html__( 'Stream', 'stream' );
	}

	/**
	 * Return translated context labels
	 *
	 * @return array
	 */
	public static function get_context_labels() {
		return array(
			'site' => esc_html__( 'Site', 'stream' ),
		);
	}

	/**
	 * Return translated action labels
	 *
	 * @return array
	 */
	public static function get_action_labels() {
		return array(
			'connected'    => esc_html__( 'Connected', 'stream' ),
			'disconnected' => esc_html__( 'Disconnected', 'stream' ),
		);
	}

	/**
	 * Site is connected
	 *
	 * @param string $site_uuid
	 * @param string $api_key
	 * @param int    $blog_id
	 *
	 * @return void
	 */
	public static function callback_wp_stream_site_connected( $site_uuid, $api_key, $blog_id ) {
		self::log(
			__( 'Site connected to Stream', 'stream' ),
			array(
				'site_uuid' => $site_uuid,
				'api_key'   => $api_key,
			),
			$blog_id,
			'site',
			'connected'
		);
	}

	/**
	 * Site is disconnected
	 *
	 * @param string $site_uuid
	 * @param string $api_key
	 * @param int    $blog_id
	 *
	 * @return void
	 */
	public static function callback_wp_stream_site_disconnected( $site_uuid, $api_key, $blog_id ) {
		self::log(
			__( 'Site disconnected from Stream', 'stream' ),
			array(
				'site_uuid' => $site_uuid,
				'api_key'   => $api_key,
			),
			$blog_id,
			'site',
			'disconnected'
		);
	}

	/**
	 * Updates to certain Stream options can skip being logged
	 *
	 * @filter wp_stream_log_data
	 *
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public static function callback_wp_stream_log_data( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$options_override = array(
			'wp_stream_status',
			'wp_stream_site_uuid',
			'wp_stream_site_api_key',
			'wp_stream_site_restricted',
		);

		if (
			! empty( $data['connector'] )
			&&
			'settings' === $data['connector']
			&&
			! empty( $data['args']['option'] )
			&&
			in_array( $data['args']['option'], $options_override )
		) {
			return false;
		}

		return $data;
	}

}
