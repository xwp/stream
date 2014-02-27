<?php
/*                                                                              */

if ( ! class_exists( 'WP_Stream_Updater_0_1' ) ) {
	class WP_Stream_Updater_0_1 {

		const VERSION = 0.1;

		static $instance;

		public $plugins = array();

		private $api_url = 'https://wp-stream.com/api/';

		public static function instance() {
			if ( empty( self::$instance ) ) {
				$class = get_called_class();
				self::$instance = new $class;
			}
			return self::$instance;
		}

		public function __construct() {
			$this->api_url = apply_filters( 'stream-api-url', $this->api_url );
			$this->setup();
		}

		public function setup() {
			// Override requests for plugin information
			add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
			// Check for updates
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check' ), 20, 3 );

			// License validation and storage
			add_action( 'wp_ajax_stream-license-check', array( $this, 'license_check' ) );
			add_action( 'wp_ajax_stream-license-remove', array( $this, 'license_remove' ) );
		}

		public function register( $plugin_file ) {
			$this->plugins[$plugin_file] = preg_match( '#([a-z\-]+).php#', $plugin_file, $match ) ? $match[1] : null;

			// Plugin activation link
			$plugin_basename = plugin_basename( $plugin_file );
			add_action( 'plugin_action_links_' . $plugin_basename, array( $this, 'plugin_action_links' ) );
		}

		public function info( $result, $action = null, $args = null ) {
			if ( $action != 'plugin_information' || ! in_array( $args->slug, $this->plugins )  ) {
				return $result;
			}

			$url     = apply_filters( 'stream-api-url', $this->api_url . $action, $action );
			$options = array(
				'body' => array(
					'slug'   => $args->slug,
				),
			);
			$response = wp_remote_post( $url, $options );

			if ( wp_remote_retrieve_response_code( $response ) != 200 ) {
				wp_die( __( 'Could not connect to Stream update center.', 'stream' ) );
			}

			$body = wp_remote_retrieve_body( $response );
			do_action( 'stream-update-api-response', $body, $response, $url, $options );

			$info = (object) json_decode( $body, true );
			return $info;
		}

		public function check( $transient ) {
			if ( empty( $transient->checked ) ) {
				return $transient;
			}
			$response = (array) $this->request( array_intersect_key( $transient->checked, $this->plugins ) );
			$license  = get_option( 'stream-license' );
			$site     = parse_url( get_site_url(), PHP_URL_HOST );
			if ( $response ) {
				foreach ( $response as $key => $value ) {
					$value->package .= '&license=' . $license . '&site=' . $site;
				}
				$transient->response = array_merge( $transient->response, $response );
			}
			return $transient;
		}

		public function request( $plugins ) {
			$action  = 'update';
			$url     = apply_filters( 'stream-api-url', $this->api_url . $action, $action );
			$options = array(
				'body' => array(
					'a'       => $action,
					'plugins' => $plugins,
					'name'    => get_bloginfo( 'name' ),
					'url'     => get_bloginfo( 'url' ),
					'license' => get_option( 'stream-license' ),
				),
			);

			$response = wp_remote_post( $url, $options );

			if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
				$error = __( 'Could not connect to Stream update center.', 'stream' );
				add_action( 'all_admin_notices', function() use ( $error ) { echo wp_kses_post( $error ); } );
				return;
			}

			$body = wp_remote_retrieve_body( $response );
			do_action( 'stream-update-api-response', $body, $response, $url, $options );

			$body = json_decode( $body );

			if ( empty( $body ) ) {
				return;
			}

			return $body;
		}

		public function license_check() {
			$license = wp_stream_filter_input( INPUT_POST, 'license' );

			if ( ! wp_verify_nonce( wp_stream_filter_input( INPUT_POST, 'nonce' ), 'license_check' ) ) {
				wp_die( __( 'Invalid security check.', 'stream' ) );
			}

			$action = 'license-verify';
			$args   = array(
				'body' => array(
					'a' => $action,
					'l' => $license,
				),
			);

			$url      = apply_filters( 'stream-api-url', $this->api_url . $action, $action );
			$response = wp_remote_post( $url, $args );

			if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
				wp_send_json_error( __( 'Could not connect to Stream license server to verify license details.', 'stream' ) );
			}

			$data = json_decode( wp_remote_retrieve_body( $response ) );
			if ( ! $data->success ) {
				wp_send_json_error( $data );
			}

			update_option( 'stream-license', $license );
			update_option( 'stream-licensee', $data->data->user );
			wp_send_json( $data );
		}

		public function license_remove() {
			if ( ! wp_verify_nonce( wp_stream_filter_input( INPUT_POST, 'nonce' ), 'license_remove' ) ) {
				wp_die( __( 'Invalid security check.', 'stream' ) );
			}

			delete_option( 'stream-license' );
			delete_option( 'stream-licensee' );
			wp_send_json_success( array( 'message' => __( 'Site disconnected successfully from your Stream account.', 'stream' ) ) );
		}

		public function plugin_action_links( $links ) {
			if ( ! get_option( 'stream-license' ) ) {
				$links[ 'activation' ] = sprintf(
					'<a href="%1$s">%2$s</a>',
					admin_url(
						add_query_arg(
							'page',
							WP_Stream_Admin::EXTENSIONS_PAGE_SLUG,
							WP_Stream_Admin::ADMIN_PARENT_PAGE
						)
					),
					__( 'Activate', 'stream' )
				);
			} else {
				$links[ 'activation' ] = __( 'Activated', 'stream' );
			}
			return $links;
		}

		public function get_api_url() {
			return $this->api_url;
		}

	}
}

if ( ! class_exists( 'WP_Stream_Updater' ) ) {
	class WP_Stream_Updater {

		private static $versions = array();

		public static function instance() {
			$latest = max( array_keys( self::$versions ) );
			return new self::$versions[$latest];
		}

		public static function register( $class ) {
			self::$versions[ $class::VERSION ] = $class;
		}
	}
}

WP_Stream_Updater::register( 'WP_Stream_Updater_0_1' );
