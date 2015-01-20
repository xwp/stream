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
	public $api_url = 'https://api.wp-stream.com';

	/**
	 * The API Version
	 *
	 * @var string
	 */
	public $api_version = '0.0.2';

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
	 * Used to prioritise the streams transport which support non-blocking
	 *
	 * @filter http_api_transports
	 *
	 * @return bool
	 */
	public static function http_api_transport_priority( $request_order, $args, $url ) {
		if ( isset( $args['blocking'] ) && false === $args['blocking'] ) {
			$request_order = array( 'streams', 'curl' );
		}

		return $request_order;
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

		$url  = $this->request_url( sprintf( '/sites/%s', urlencode( $this->site_uuid ) ), $params );
		$args = array( 'method' => 'GET' );
		$site = $this->remote_request( $url, $args, $allow_cache, $expiration );

		if ( $site && ! is_wp_error( $site ) ) {
			$is_restricted = ( ! isset( $site->plan->type ) || 'free' === $site->plan->type ) ? 1 : 0;

			if ( self::$restricted !== (bool) $is_restricted ) {
				self::$restricted = $is_restricted;

				update_option( self::RESTRICTED_OPTION_KEY, $is_restricted );
			}
		}

		return $site;
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

		$url  = $this->request_url( sprintf( '/sites/%s/records/%s', urlencode( $this->site_uuid ), urlencode( $record_id ) ), $params );
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

		$url  = $this->request_url( sprintf( '/sites/%s/records', urlencode( $this->site_uuid ) ), $params );
		$args = array( 'method' => 'GET' );

		return $this->remote_request( $url, $args, $allow_cache, $expiration );
	}

	/**
	 * Create new records.
	 *
	 * @param array $records
	 * @param bool  $blocking
	 *
	 * @return mixed
	 */
	public function new_records( $records, $blocking = false ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		$url  = $this->request_url( sprintf( '/sites/%s/records', urlencode( $this->site_uuid ) ) );
		$args = array( 'method' => 'POST', 'body' => json_encode( array( 'records' => $records ) ), 'blocking' => (bool) $blocking );

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
	public function search( $query = array(), $fields = array(), $sites = array(), $search_type = '', $allow_cache = false, $expiration = 120 ) {
		if ( ! $this->site_uuid ) {
			return false;
		}

		$body = array();

		$body['query']       = ! empty( $query ) ? $query : array();
		$body['fields']      = ! empty( $fields ) ? $fields : array();
		$body['sites']       = ! empty( $sites ) ? $sites : array( $this->site_uuid );
		$body['search_type'] = ! empty( $search_type ) ? $search_type : '';

		$url  = $this->request_url( '/search' );
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
	public function request_url( $path, $params = array() ) {
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
		if ( empty( $url ) || empty( $this->api_key ) ) {
			return false;
		}

		$defaults = array(
			'headers'   => array(),
			'method'    => 'GET',
			'body'      => '',
			'sslverify' => true,
		);

		$this->count++;

		$args = wp_parse_args( $args, $defaults );

		$args['headers']['Stream-Site-API-Key'] = $this->api_key;
		$args['headers']['Accept-Version']      = $this->api_version;
		$args['headers']['Content-Type']        = 'application/json';

		if ( WP_Stream::is_development_mode() ) {
			$args['blocking'] = true;
		}

		add_filter( 'http_api_transports', array( __CLASS__, 'http_api_transport_priority' ), 10, 3 );

		$transient = 'wp_stream_' . md5( $url );

		if ( 'GET' === $args['method'] && $allow_cache ) {
			if ( false === ( $request = get_transient( $transient ) ) ) {
				$request = wp_remote_request( $url, $args );

				set_transient( $transient, $request, $expiration );
			}
		} else {
			$request = wp_remote_request( $url, $args );
		}

		remove_filter( 'http_api_transports', array( __CLASS__, 'http_api_transport_priority' ), 10 );

		// Return early if the request is non blocking
		if ( isset( $args['blocking'] ) && false === $args['blocking'] ) {
			return true;
		}

		if ( ! is_wp_error( $request ) ) {
			/**
			 * Filter the request data of the API response.
			 *
			 * Does not fire on non-blocking requests.
			 *
			 * @since 2.0.0
			 *
			 * @param string $url
			 * @param array  $args
			 *
			 * @return array
			 */
			$data = apply_filters( 'wp_stream_api_request_data', json_decode( $request['body'] ), $url, $args );

			// Loose comparison needed
			if ( 200 == $request['response']['code'] || 201 == $request['response']['code'] ) {
				return $data;
			} else {
				// Disconnect if unauthorized or no longer exists, loose comparison needed
				if ( 403 == $request['response']['code'] || 410 == $request['response']['code'] ) {
					WP_Stream_Admin::remove_api_authentication();
				}

				$this->errors['errors']['http_code'] = $request['response']['code'];
			}

			if ( isset( $data->error ) ) {
				$this->errors['errors']['api_error'] = $data->error;
			}
		} else {
			$this->errors['errors']['remote_request_error'] = $request->get_error_message();

			WP_Stream::notice( sprintf( '<strong>%s</strong> %s.', __( 'Stream API Error.', 'stream' ), $this->errors['errors']['remote_request_error'] ) );
		}

		if ( ! empty( $this->errors ) ) {
			delete_transient( $transient );
		}

		return false;
	}

}
