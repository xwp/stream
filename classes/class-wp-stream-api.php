<?php

class WP_Stream_API {

	/**
	 * API Key key/identifier
	 */
	const API_KEY_OPTION_KEY = 'wp_stream_api_master_key';

	/**
	 * Site UUID key/identifier
	 */
	const SITE_UUID_OPTION_KEY = 'wp_stream_site_uuid';

	/**
	 * The site's API Key
	 *
	 * @var string
	 */
	public $api_key = false;

	/**
	 * The site's unique identifier
	 *
	 * @var string
	 */
	protected $site_uuid = false;

	/**
	 * The API URL
	 *
	 * @var string
	 */
	protected $api_url = 'http://api.wp-stream.com';

	/**
	 * Error messages
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * Public constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->api_key   = get_option( self::API_KEY_OPTION_KEY, 0 );
		$this->site_uuid = get_option( self::SITE_UUID_OPTION_KEY, 0 );

		if ( isset( $_GET['api_key'] ) ) {
			add_action( 'admin_init', array( $this, 'update_api_authentication' ) );
		}
	}

	public function update_api_authentication() {
		$site_url           = str_replace( array( 'http://', 'https://' ), '', get_site_url() );
		$connect_nonce_name = 'stream_connect_site-' . sanitize_key( $site_url );

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], $connect_nonce_name ) ) {
			wp_die( 'Doing it wrong.' );
		}

		$this->api_key = $_GET['api_key'];

		update_option( self::API_KEY_OPTION_KEY, $this->api_key );

		$site_uuid_request = $this->remote_request( $this->api_url . '/validate-key' );

		if ( isset( $site_uuid_request->site_id ) ) {
			$this->site_uuid = $site_uuid_request->site_id;
		}

		update_option( self::SITE_UUID_OPTION_KEY, $this->site_uuid );

		do_action( 'wp_stream_site_connected', $this->api_key, $this->site_uuid );

		if ( ! $this->api_key || ! $this->site_uuid ) {
			wp_die( __( 'There was a problem connecting to Stream. Please try again later.', 'stream' ) );
		}

		$redirect_url = add_query_arg(
			array(
				'page'      => WP_Stream_Admin::RECORDS_PAGE_SLUG,
				'connected' => 1,
			),
			admin_url( 'admin.php' )
		);
		wp_redirect( $redirect_url );
	}

	/**
	 * Set cache with the Transients API.
	 *
	 * @param string Transient ID.
	 * @param int    Set transient timeout. Default 300 seconds (5 minutes).
	 *
	 * @return    mixed
	 */
	public function set_cache( $transient, $url, $timeout = 300 ) {
		$results = get_transient( $transient );

		if ( false === $results ) {
			$results = apply_filters( 'wp_stream_api_set_cache', $this->remote_request( $url ), $transient );
			set_transient( $transient, $results, $timeout );
		}

		return $results;
	}

	/**
	 * Clear cache with the Transients API.
	 *
	 * @param string Transient ID.
	 *
	 * @return    void
	 */
	public function clear_cache( $transient ) {
		delete_transient( $transient );
	}

	/**
	 * Helper function to query the marketplace API via wp_remote_request.
	 *
	 * @param string The url to access.
	 * @param string The method of the request.
	 * @param array  The headers sent during the request.
	 *
	 * @return object The results of the wp_remote_request request.
	 */
	protected function remote_request( $url, $method = 'GET', $headers = array(), $body = null ) {
		if ( empty( $url ) ) {
			return false;
		}

		if ( ! isset( $headers['stream-api-master-key'] ) ) {
			$headers['stream-api-master-key'] = $this->api_key;
		}

		$args = array(
			'headers' => $headers,
			'method' => $method,
			'body' => $body
		);
		$request = wp_remote_request( $url, $args );

		if ( is_wp_error( $request ) ) {
			echo $request->get_error_message();
			return false;
		}

		$data = json_decode( $request['body'] );

		if ( $request['response']['code'] == 200 ) {
			return $data;
		} else {
			$this->errors['errors']['http_code'] = $request['response']['code'];
		}

		if ( isset( $data->error ) ) {
			$this->errors['errors']['api_error'] = $data->error;
		}

		return false;
	}
}