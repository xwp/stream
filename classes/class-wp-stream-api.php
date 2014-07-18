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
	 * @param bool   Allow API calls to be cached.
	 * @param int    Set transient expiration in seconds.
	 *
	 * @return mixed
	 */
	public function validate_key( $allow_cache = false, $expiration = 300 ) {
		$url  = $this->request_url( '/validate-key' );
		$args = array( 'method' => 'GET' );

		return $this->remote_request( $url, $args, $allow_cache, $expiration );
	}

	/**
	 * Get the details for a specific user.
	 *
	 * @param int  A user ID.
	 * @param bool Allow API calls to be cached.
	 * @param int  Set transient expiration in seconds.
	 *
	 * @return mixed
	 */
	public function get_user( $user_id = false, $allow_cache = true, $expiration = 300 ) {
		if ( false === $user_id ) {
			return false;
		}

		$url  = $this->request_url( sprintf( '/users/%s', esc_attr( intval( $user_id ) ) ) );
		$args = array( 'method' => 'GET' );

		return $this->remote_request( $url, $args, $allow_cache, $expiration );
	}

	/**
	 * Get a specific record.
	 *
	 * @param string A record ID.
	 * @param array  Returns specified fields only.
	 * @param bool   Allow API calls to be cached.
	 * @param int    Set transient expiration in seconds.
	 *
	 * @return mixed
	 */
	public function get_record( $record_id = false, $fields = array(), $allow_cache = true, $expiration = 30 ) {
		if ( false === $record_id ) {
			return false;
		}

		if ( ! $this->site_uuid ) {
			return false;
		}

		$params = array();

		if ( ! empty( $fields ) ) {
			$params['fields'] = implode( ',', $fields );
		}

		$url  = $this->request_url( sprintf( '/sites/%s/records/%s', esc_attr( $this->site_uuid ), esc_attr( $record_id ) ), $params );
		$args = array( 'method' => 'GET' );

		return $this->remote_request( $url, $args, $allow_cache, $expiration );
	}

	/**
	 * Get all records.
	 *
	 * @param array Returns specified fields only.
	 * @param bool  Allow API calls to be cached.
	 * @param int   Set transient expiration in seconds.
	 *
	 * @return mixed
	 */
	public function get_records( $fields = array(), $allow_cache = true, $expiration = 120 ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		$params = array();

		if ( ! empty( $fields ) ) {
			$params['fields'] = implode( ',', $fields );
		}

		$url  = $this->request_url( sprintf( '/sites/%s/records', esc_attr( $this->site_uuid ) ), $params );
		$args = array( 'method' => 'GET' );

		return $this->remote_request( $url, $args, $allow_cache, $expiration );
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

		$args = array();

		if ( ! empty( $fields ) ) {
			$args['fields'] = implode( ',', $fields );
		}

		$url  = $this->request_url( sprintf( '/sites/%s/records', esc_attr( $this->site_uuid ) ), $args );
		$args = array( 'method' => 'POST', 'body' => json_encode( $record, JSON_FORCE_OBJECT ) );

		return $this->remote_request( $url, $args );
	}

	/**
	 * Search all records.
	 *
	 * @param array Elasticsearch's Query DSL query object.
	 * @param array Returns specified fields only.
	 * @param bool  Allow API calls to be cached.
	 * @param int   Set transient expiration in seconds.
	 *
	 * @return mixed
	 */
	public function search( $query_dsl = array(), $fields = array(), $sites = array(), $allow_cache = false, $expiration = 120 ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		if ( empty( $sites ) ) {
			$sites[] = $this->site_uuid;
		}

		$params = array();

		if ( ! empty( $fields ) ) {
			$params['fields'] = implode( ',', $fields );
		}

		$url  = $this->request_url( sprintf( '/search', esc_attr( $this->site_uuid ) ), $params );

		$query_dsl['sites'] = (array) $sites;

		$body = (object) $query_dsl;

		$args = array( 'method' => 'POST', 'body' => json_encode( $body ) );

		return $this->remote_request( $url, $args, $allow_cache, $expiration );
	}

	/**
	 * Helper function to create and escape a URL for an API request.
	 *
	 * @param string The endpoint path, with a starting slash.
	 * @param array  The $_GET parameters.
	 *
	 * @return string A properly escaped URL.
	 */
	protected function request_url( $path, $params = array() ) {
		return esc_url_raw(
			add_query_arg(
				$params,
				untrailingslashit( $this->api_url ) . $path
			)
		);
	}

	/**
	 * Helper function to query the marketplace API via wp_remote_request.
	 *
	 * @param string The url to access.
	 * @param string The method of the request.
	 * @param array  The headers sent during the request.
	 * @param bool   Allow API calls to be cached.
	 * @param int    Set transient expiration in seconds.
	 *
	 * @return object The results of the wp_remote_request request.
	 */
	protected function remote_request( $url = '', $args = array(), $allow_cache = true, $expiration = 300 ) {
		if ( empty( $url ) ) {
			return false;
		}

		$defaults = array(
			'headers' => array(),
			'method'  => 'GET',
			'body'    => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$args['headers']['stream-api-master-key'] = $this->api_key;
		$args['headers']['Content-Type']          = 'application/json';

		$transient = 'wp_stream_' . md5( $url );

		if ( 'GET' === $args['method'] && $allow_cache ) {
			if ( false === ( $request = get_transient( $transient ) ) ) {
				$request = wp_remote_request( $url, $args );
				set_transient( $transient, $request, $expiration );
			}
		} else {
			$request = wp_remote_request( $url, $args );
		}

		if ( ! is_wp_error( $request ) ) {
			$data = apply_filters( 'wp_stream_api_request_data', json_decode( $request['body'] ), $url, $args );

			if ( 200 === $request['response']['code'] || 201 === $request['response']['code'] ) {
				return $data;
			} else {
				$this->errors['errors']['http_code'] = $request['response']['code'];
			}

			if ( isset( $data->error ) ) {
				$this->errors['errors']['api_error'] = $data->error;
			}
		} else {
			$this->errors['errors']['remote_request_error'] = $request->get_error_message();
			WP_Stream::admin_notices( sprintf( '<strong>%s</strong> %s.', __( 'Stream API Error.', 'stream' ), $this->errors['errors']['remote_request_error'] ) );
		}

		if ( ! empty( $this->errors ) ) {
			delete_transient( $transient );
		}

		return false;
	}
}