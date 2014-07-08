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
	public $site_uuid = false;

	/**
	 * The API URL
	 *
	 * @var string
	 */
	protected $api_url = 'http://api.wp-stream.com';

	/**
	 * The API Version
	 *
	 * @var string
	 */
	protected $api_version = 'v1';

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
	}

	/**
	 * Validate a site API key.
	 *
	 * @param string The API Key.
	 *
	 * @return mixed
	 */
	public function validate_key( $api_key = null ) {
		if ( ! $api_key ) {
			$api_key = $this->api_key;
		}

		$url     = $this->request_url( '/validate-key' );
		$method  = 'GET';
		$headers = array( 'stream-api-master-key' => $api_key );

		return $this->remote_request( $url, $method, null, $headers );
	}

	/**
	 * Invalidate a site API key.
	 *
	 * @param string The API Key.
	 *
	 * @return mixed
	 */
	public function invalidate_key( $api_key = null ) {
		if ( ! $api_key ) {
			$api_key = $this->api_key;
		}

		$url     = $this->request_url( '/invalidate-key' );
		$method  = 'DELETE';
		$headers = array( 'stream-api-master-key' => $api_key );

		return $this->remote_request( $url, $method, null, $headers );
	}

	/**
	 * Get the details for a specific user.
	 *
	 * @param int A user ID.
	 *
	 * @return mixed
	 */
	public function get_user( $user_id = false ) {
		if ( false === $user_id ) {
			return false;
		}

		$url    = $this->request_url( '/users/' . intval( $user_id ) );
		$method = 'GET';

		return $this->remote_request( $url, $method );
	}

	/**
	 * Get a specific record.
	 *
	 * @param string A record ID.
	 * @param array  Returns specified fields only.
	 *
	 * @return mixed
	 */
	public function get_record( $record_id = false, $fields = array() ) {
		if ( false === $record_id ) {
			return false;
		}

		if ( ! $this->site_uuid ) {
			return false;
		}

		$args = array();

		if ( ! empty( $fields ) ) {
			$args['fields'] = implode( ',', $fields );
		}

		$url    = $this->request_url( '/sites/' . $this->site_uuid . '/records/' . $record_id, $args );
		$method = 'GET';

		return $this->remote_request( $url, $method );
	}

	/**
	 * Get all records.
	 *
	 * @param array Returns specified fields only.
	 *
	 * @return mixed
	 */
	public function get_records( $fields = array() ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		$args = array();

		if ( ! empty( $fields ) ) {
			$args['fields'] = implode( ',', $fields );
		}

		$url    = $this->request_url( '/sites/' . $this->site_uuid . '/records', $args );
		$method = 'GET';

		return $this->remote_request( $url, $method );
	}

	/**
	 * Create a new record.
	 *
	 * @param array Record data.
	 * @param array Returns specified fields only.
	 *
	 * @return mixed
	 */
	public function new_record( $record, $fields = array() ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		$record['site_id'] = $this->site_uuid;

		$args = array();

		if ( ! empty( $fields ) ) {
			$args['fields'] = implode( ',', $fields );
		}

		$url    = $this->request_url( '/sites/' . $this->site_uuid . '/records', array(), $record );
		$method = 'POST';

		return $this->remote_request( $url, $method, $record );
	}

	/**
	 * Set cache with the Transients API.
	 *
	 * @param string Transient ID.
	 * @param int    Set transient timeout. Default 300 seconds (5 minutes).
	 *
	 * @return mixed
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
	 * Helper function to create and escape a URL for an API request.
	 *
	 * @param string The endpoint path, with a starting slash.
	 * @param array  The $_GET args.
	 *
	 * @return string A properly escaped URL.
	 */
	protected function request_url( $path, $args = array() ) {
		return esc_url_raw(
			add_query_arg(
				$args,
				untrailingslashit( $this->api_url ) . $path //use this when versions implemented: trailingslashit( $this->api_url ) . $this->api_version . $path
			)
		);
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
	protected function remote_request( $url = '', $method = 'GET', $body = null, $headers = array() ) {
		if ( empty( $url ) ) {
			return false;
		}

		if ( ! isset( $headers['stream-api-master-key'] ) ) {
			$headers['stream-api-master-key'] = $this->api_key;
		}

		$args = array(
			'headers' => $headers,
			'method' => $method,
			'body' => isset( $body ) ? json_encode( $body ) : '',
		);

		$request = wp_remote_request( $url, $args );

		if ( is_wp_error( $request ) ) {
			echo $request->get_error_message();
			return false;
		}

		$data = json_decode( $request['body'] );

		if ( $request['response']['code'] >= 200 && $request['response']['code'] <= 204 ) {
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