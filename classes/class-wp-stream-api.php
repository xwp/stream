<?php

class WP_Stream_API {

	/**
	 * API Key key/identifier
	 */
	const API_KEY_OPTION_KEY = 'wp_stream_site_api_key';

	/**
	 * Site UUID key/identifier
	 */
	const SITE_UUID_OPTION_KEY = 'wp_stream_site_uuid';

	/**
	 * Site Retricted key/identifier
	 */
	const RESTRICTED_OPTION_KEY = 'wp_stream_site_restricted';

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
	 * The site's restriction status
	 *
	 * @var bool
	 */
	public static $restricted = true;

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
	protected $api_version = '0.0.2';

	/**
	 * Error messages
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * Total API calls made per page load
	 * Used for debugging and optimization
	 *
	 * @var array
	 */
	public $count = 0;

	/**
	 * Public constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->api_key    = get_option( self::API_KEY_OPTION_KEY, 0 );
		$this->site_uuid  = get_option( self::SITE_UUID_OPTION_KEY, 0 );
		self::$restricted = get_option( self::RESTRICTED_OPTION_KEY, 1 );
	}

	/**
	 * Check if the current site is restricted
	 *
	 * @param bool Force the API to send a request to check the site's plan type
	 *
	 * @return bool
	 */
	public static function is_restricted( $force_check = false ) {
		if ( $force_check ) {
			$site = WP_Stream::$api->get_site();

			self::$restricted = ( ! isset( $site->plan->type ) || 'free' === $site->plan->type );
		}

		return self::$restricted;
	}

	/**
	 * Used to filter transport method checks and disable them
	 *
	 * @filter use_curl_transport
	 * @filter use_streams_transport
	 *
	 * @return bool
	 */
	public static function disable_transport() {
		return false;
	}

	/**
	 * Get the details for a specific site.
	 *
	 * @param array Returns specified fields only.
	 * @param bool  Allow API calls to be cached.
	 * @param int   Set transient expiration in seconds.
	 *
	 * @return mixed
	 */
	public function get_site( $fields = array(), $allow_cache = true, $expiration = 30 ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		$params = array();

		if ( ! empty( $fields ) ) {
			$params['fields'] = implode( ',', $fields );
		}

		$url  = $this->request_url( sprintf( '/sites/%s', esc_attr( $this->site_uuid ) ), $params );
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
	public function get_records( $fields = array(), $allow_cache = true, $expiration = 30 ) {
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
	 * Create new records.
	 *
	 * @param array Record data.
	 * @param array Returns specified fields only.
	 *
	 * @return void
	 */
	public function new_records( $records, $fields = array() ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		$args = array();

		if ( ! empty( $fields ) ) {
			$args['fields'] = implode( ',', $fields );
		}

		$url  = $this->request_url( sprintf( '/sites/%s/records', esc_attr( $this->site_uuid ) ), $args );
		$args = array( 'method' => 'POST', 'body' => json_encode( array( 'records' => $records ) ), 'blocking' => false );

		$this->remote_request( $url, $args );
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
	public function search( $query = array(), $fields = array(), $sites = array(), $allow_cache = false, $expiration = 120 ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		if ( empty( $sites ) ) {
			$sites[] = $this->site_uuid;
		}

		$url  = $this->request_url( sprintf( '/search', esc_attr( $this->site_uuid ) ) );

		$body = array();

		if ( ! empty( $query ) ) {
			$body['query'] = $query;
		}
		if ( ! empty( $fields ) ) {
			$body['fields'] = $fields;
		}
		if ( ! empty( $sites ) ) {
			$body['sites'] = $sites;
		}

		$args = array( 'method' => 'POST', 'body' => json_encode( (object) $body ) );

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

		$this->count++;

		$args = wp_parse_args( $args, $defaults );

		$args['headers']['Stream-Site-API-Key'] = $this->api_key;
		$args['headers']['Accept-Version']      = $this->api_version;
		$args['headers']['Content-Type']        = 'application/json';

		$blocking = isset( $args['blocking'] ) ? $args['blocking'] : true;

		if ( function_exists( 'fsockopen' ) && ! $blocking ) {
			add_filter( 'use_curl_transport', array( __CLASS__, 'disable_transport' ) );
			add_filter( 'use_streams_transport', array( __CLASS__, 'disable_transport' ) );
		}

		$transient = 'wp_stream_' . md5( $url );

		if ( 'GET' === $args['method'] && $allow_cache ) {
			if ( false === ( $request = get_transient( $transient ) ) ) {
				$request = wp_remote_request( $url, $args );
				set_transient( $transient, $request, $expiration );
			}
		} else {
			$request = wp_remote_request( $url, $args );
		}

		if ( function_exists( 'fsockopen' ) && ! $blocking ) {
			remove_filter( 'use_curl_transport', array( __CLASS__, 'disable_transport' ) );
			remove_filter( 'use_streams_transport', array( __CLASS__, 'disable_transport' ) );
		}

		if ( ! $blocking ) {
			return true;
		}

		if ( ! is_wp_error( $request ) ) {
			$data = apply_filters( 'wp_stream_api_request_data', json_decode( $request['body'] ), $url, $args );

			if ( 200 === $request['response']['code'] || 201 === $request['response']['code'] ) {
				return $data;
			} else {
				// Disconnect if unauthorized or no longer exists
				if ( 403 === $request['response']['code'] || 410 === $request['response']['code'] ) {
					WP_Stream_Admin::remove_api_authentication();
				}
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