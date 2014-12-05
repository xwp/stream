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
	);

	/**
	 * Return translated connector label
	 *
	 * @return string
	 */
	public static function get_label() {
		return __( 'Stream', 'stream' );
	}

	/**
	 * Return translated context labels
	 *
	 * @return array
	 */
	public static function get_context_labels() {
		return array(
			'site' => __( 'Site', 'stream' ),
		);
	}

	/**
	 * Return translated action labels
	 *
	 * @return array
	 */
	public static function get_action_labels() {
		return array(
			'connected'    => __( 'Connected', 'stream' ),
			'disconnected' => __( 'Disconnected', 'stream' ),
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

}
