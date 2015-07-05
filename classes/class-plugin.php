<?php
namespace WP_Stream;

class Plugin {
	/**
	 * Plugin version number
	 *
	 * @const string
	 */
	const VERSION = '2.0.5';

	/**
	 * WP-CLI command
	 *
	 * @const string
	 */
	const WP_CLI_COMMAND = 'stream';

	/**
	 * @var API
	 */
	public $api;

	/**
	 * @var Admin
	 */
	public $admin;

	/**
	 * @var Connectors
	 */
	public $connectors;

	/**
	 * @var DB
	 */
	public $db;

	/**
	 * @var Feeds
	 */
	public $feeds;

	/**
	 * @var Log
	 */
	public $log;

	/**
	 * @var Settings
	 */
	public $settings;

	/**
	 * Admin notices, collected and displayed on proper action
	 *
	 * @var array
	 */
	public $notices = array();

	/**
	 * URLs and Paths used by the plugin
	 *
	 * @var array
	 */
	public $locations = array();

	/**
	 * Class constructor
	 */
	public function __construct() {
		$locate = $this->locate_plugin();

		$this->locations = array(
			'plugin'    => $locate['plugin_basename'],
			'dir'       => $locate['dir_path'],
			'url'       => $locate['dir_url'],
			'inc_dir'   => $locate['dir_path'] . 'includes/',
			'class_dir' => $locate['dir_path'] . 'classes/',
		);

		spl_autoload_register( array( $this, 'autoload' ) );

		// Load helper functions
		require_once $this->locations['inc_dir'] . 'functions.php';

		// Load DB helper interface/class
		$driver = '\WP_Stream\DB';
		if ( class_exists( $driver ) ) {
			$this->db = new DB( $this );
		}

		if ( ! $this->db ) {
			wp_die( esc_html__( 'Stream: Could not load chosen DB driver.', 'stream' ), 'Stream DB Error' );
		}

		/**
		 * Filter allows a custom Stream API class to be instantiated
		 *
		 * @return object  The API class object
		 */
		$this->api = apply_filters( 'wp_stream_api_class', new API( $this ) );

		// Install the plugin
		add_action( 'wp_stream_before_db_notices', array( $this, 'install' ) );

		// Load languages
		add_action( 'plugins_loaded', array( $this, 'i18n' ) );

		// Load settings, enabling extensions to hook in
		add_action( 'init', function() {
			$this->settings = new Settings( $this );
		}, 9 );

		// Load logger class
		add_action( 'plugins_loaded', function() {
			$this->log = new Log( $this );
		} );

		// Load connectors after widgets_init, but before the default of 10
		add_action( 'init', function() {
			$this->connectors = new Connectors( $this );
		}, 9 );

		// Load support for feeds
		add_action( 'init', function() {
			$this->feeds = new Feeds( $this );
		} );

		// Add frontend indicator
		add_action( 'wp_head', array( $this, 'frontend_indicator' ) );

		// Load admin area classes
		if ( is_admin() ) {
			$this->admin = new Admin( $this );
		}

		// Disable logging during the content import process
		add_filter( 'wp_stream_record_array', array( $this, 'disable_logging_during_import' ), 10, 1 );

		// Load WP-CLI command
		if ( defined( '\WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( self::WP_CLI_COMMAND, 'CLI' );
		}
	}

	/**
	 * @return \ReflectionObject
	 */
	function get_object_reflection() {
		static $reflection;
		if ( empty( $reflection ) ) {
			$reflection = new \ReflectionObject( $this );
		}
		return $reflection;
	}

	/**
	 * Autoloader for classes
	 *
	 * @param string $class
	 */
	function autoload( $class ) {
		if ( ! preg_match( '/^(?P<namespace>.+)\\\\(?P<autoload>[^\\\\]+)$/', $class, $matches ) ) {
			return;
		}
		if ( $this->get_object_reflection()->getNamespaceName() !== $matches['namespace'] ) {
			return;
		}

		$autoload_name = $matches['autoload'];
		$autoload_dir  = \trailingslashit( $this->locations['class_dir'] );
		$autoload_path = sprintf( '%sclass-%s.php', $autoload_dir, strtolower( str_replace( '_', '-', $autoload_name ) ) );

		if ( is_readable( $autoload_path ) ) {
			require_once $autoload_path;
		}
	}

	/**
	 * Loads the translation files.
	 *
	 * @action plugins_loaded
	 */
	public function i18n() {
		load_plugin_textdomain( 'stream', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Prepend $stem with $this->prefix followed by $delimiter
	 *
	 * @param string $stem
	 * @param string $delimiter
	 *
	 * @return string
	 */
	function prefix( $stem = '', $delimiter = '_' ) {
		$prefix = strtolower( str_replace( '\\', '_', $this->get_object_reflection()->getNamespaceName() ) );
		return $prefix . $delimiter . $stem;
	}

	/**
	 * Check if Stream is running on WordPress.com VIP
	 *
	 * @return bool
	 */
	public function is_vip() {
		return function_exists( 'wpcom_vip_load_plugin' );
	}

	/**
	 * True if native WP Cron is enabled, otherwise false
	 *
	 * @return bool
	 */
	public function is_wp_cron_enabled() {
		return ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? false : true;
	}

	/**
	 * Disable logging during the content import process
	 *
	 * @param array $records
	 *
	 * @filter wp_stream_record_array
	 *
	 * @return array
	 */
	public function disable_logging_during_import( $records ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			$records = array();
		}

		return $records;
	}

	/**
	 * Handle notice messages according to the appropriate context (WP-CLI or the WP Admin)
	 *
	 * @param string $message
	 * @param bool $is_error
	 */
	public function notice( $message, $is_error = true ) {
		if ( defined( '\WP_CLI' ) && \WP_CLI ) {
			$message = strip_tags( $message );

			if ( $is_error ) {
				\WP_CLI::warning( $message );
			} else {
				\WP_CLI::success( $message );
			}
		} else {
			// Trigger admin notices late, so that any notices which occur during page load are displayed
			add_action( 'shutdown', array( __CLASS__, 'admin_notices' ) );

			$notice = compact( 'message', 'is_error' );

			if ( ! in_array( $notice, $this->notices ) ) {
				$this->notices[] = $notice;
			}
		}
	}

	/**
	 * Show an error or other message in the WP Admin
	 *
	 * @action shutdown
	 */
	public function admin_notices() {
		global $allowedposttags;

		$custom = array(
			'progress' => array(
				'class' => true,
				'id'    => true,
				'max'   => true,
				'style' => true,
				'value' => true,
			),
		);

		$allowed_html = array_merge( $allowedposttags, $custom );

		ksort( $allowed_html );

		foreach ( $this->notices as $notice ) {
			$class_name   = empty( $notice['is_error'] ) ? 'updated' : 'error';
			$html_message = sprintf( '<div class="%s">%s</div>', esc_attr( $class_name ), wpautop( $notice['message'] ) );

			echo wp_kses( $html_message, $allowed_html );
		}
	}

	/**
	 * Displays an HTML comment in the frontend head to indicate that Stream is activated,
	 * and which version of Stream is currently in use.
	 *
	 * @action wp_head
	 *
	 * @return string|void An HTML comment, or nothing if the value is filtered out.
	 */
	public function frontend_indicator() {
		$comment = sprintf( 'Stream WordPress user activity plugin v%s', esc_html( $this->get_version() ) ); // Localization not needed

		/**
		 * Filter allows the HTML output of the frontend indicator comment
		 * to be altered or removed, if desired.
		 *
		 * @return string  The content of the HTML comment
		 */
		$comment = apply_filters( 'wp_stream_frontend_indicator', $comment );

		if ( ! empty( $comment ) ) {
			echo sprintf( "<!-- %s -->\n", esc_html( $comment ) ); // xss ok
		}
	}

	/**
	 * Version of plugin_dir_url() which works for plugins installed in the plugins directory,
	 * and for plugins bundled with themes.
	 *
	 * @throws \Exception
	 *
	 * @return array
	 */
	private function locate_plugin() {
		$reflection = new \ReflectionObject( $this );
		$file_name  = $reflection->getFileName();

		if ( '/' !== \DIRECTORY_SEPARATOR ) {
			$file_name = str_replace( \DIRECTORY_SEPARATOR, '/', $file_name ); // Windows compat
		}

		$plugin_dir = preg_replace( '#(.*plugins[^/]*/[^/]+)(/.*)?#', '$1', $file_name, 1, $count );

		if ( 0 === $count ) {
			throw new \Exception( "Class not located within a directory tree containing 'plugins': $file_name" );
		}

		// Make sure that we can reliably get the relative path inside of the content directory
		$content_dir = trailingslashit( WP_CONTENT_DIR );

		if ( '/' !== \DIRECTORY_SEPARATOR ) {
			$content_dir = str_replace( \DIRECTORY_SEPARATOR, '/', $content_dir ); // Windows compat
		}

		if ( 0 !== strpos( $plugin_dir, $content_dir ) ) {
			throw new \Exception( 'Plugin dir is not inside of WP_CONTENT_DIR' );
		}

		$content_sub_path = substr( $plugin_dir, strlen( $content_dir ) );
		$dir_url          = content_url( trailingslashit( $content_sub_path ) );
		$dir_path         = trailingslashit( $plugin_dir );
		$dir_basename     = basename( $plugin_dir );
		$plugin_basename  = trailingslashit( $dir_basename ) . basename( __FILE__ );

		return compact( 'dir_url', 'dir_path', 'dir_basename', 'plugin_basename' );
	}

	/**
	 * Getter for the version number.
	 *
	 * @return string
	 */
	public function get_version() {
		return self::VERSION;
	}
}