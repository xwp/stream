<?php
namespace WP_Stream;

class Plugin {
	/**
	 * Plugin version number
	 *
	 * @const string
	 */
	const VERSION = '3.2.3';

	/**
	 * WP-CLI command
	 *
	 * @const string
	 */
	const WP_CLI_COMMAND = 'stream';

	/**
	 * @var Admin
	 */
	public $admin;

	/**
	 * @var Alerts
	 */
	public $alerts;

	/**
	 * @var Alerts_List
	 */
	public $alerts_list;

	/**
	 * @var Connectors
	 */
	public $connectors;

	/**
	 * @var DB
	 */
	public $db;

	/**
	 * @var Log
	 */
	public $log;

	/**
	 * @var Settings
	 */
	public $settings;

	/**
	 * @var Install
	 */
	public $install;

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
		$driver_class = apply_filters( 'wp_stream_db_driver', '\WP_Stream\DB_Driver_WPDB' );
		$driver       = null;

		if ( class_exists( $driver_class ) ) {
			$driver   = new $driver_class();
			$this->db = new DB( $driver );
		}

		$error = false;
		if ( ! $this->db ) {
			$error = esc_html__( 'Stream: Could not load chosen DB driver.', 'stream' );
		} elseif ( ! $driver instanceof DB_Driver ) {
			$error = esc_html__( 'Stream: DB driver must implement DB Driver interface.', 'stream' );
		}

		if ( $error ) {
			wp_die(
				esc_html( $error ),
				esc_html__( 'Stream DB Error', 'stream' )
			);
		}

		// Load languages
		add_action( 'plugins_loaded', array( $this, 'i18n' ) );

		// Load logger class
		$this->log = apply_filters( 'wp_stream_log_handler', new Log( $this ) );

		// Load settings and connectors after widgets_init and before the default init priority
		add_action( 'init', array( $this, 'init' ), 9 );

		// Add frontend indicator
		add_action( 'wp_head', array( $this, 'frontend_indicator' ) );

		// Change DB driver after plugin loaded if any add-ons want to replace
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 20 );

		// Load admin area classes
		if ( is_admin() || ( defined( 'WP_STREAM_DEV_DEBUG' ) && WP_STREAM_DEV_DEBUG ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$this->admin   = new Admin( $this );
			$this->install = $driver->setup_storage( $this );
		} elseif ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			$this->admin = new Admin( $this, $driver );
		}

		// Load WP-CLI command
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( self::WP_CLI_COMMAND, 'WP_Stream\CLI' );
		}
	}

	/**
	 * Autoloader for classes
	 *
	 * @param string $class
	 */
	public function autoload( $class ) {
		if ( ! preg_match( '/^(?P<namespace>.+)\\\\(?P<autoload>[^\\\\]+)$/', $class, $matches ) ) {
			return;
		}

		static $reflection;

		if ( empty( $reflection ) ) {
			$reflection = new \ReflectionObject( $this );
		}

		if ( $reflection->getNamespaceName() !== $matches['namespace'] ) {
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
		load_plugin_textdomain( 'stream', false, dirname( $this->locations['plugin'] ) . '/languages/' );
	}

	/*
	 * Load Settings, Notifications, and Connectors
	 *
	 * @action init
	 */
	public function init() {
		$this->settings    = new Settings( $this );
		$this->connectors  = new Connectors( $this );
		$this->alerts      = new Alerts( $this );
		$this->alerts_list = new Alerts_List( $this );

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
		$dir_url         = trailingslashit( plugins_url( '', dirname( __FILE__ ) ) );
		$dir_path        = plugin_dir_path( dirname( __FILE__ ) );
		$dir_basename    = basename( $dir_path );
		$plugin_basename = trailingslashit( $dir_basename ) . $dir_basename . '.php';

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

	/**
	 * Change plugin database driver in case driver plugin loaded after stream
	 */
	public function plugins_loaded() {
		// Load DB helper interface/class
		$driver_class = apply_filters( 'wp_stream_db_driver', '\WP_Stream\DB_Driver_WPDB' );

		if ( class_exists( $driver_class ) ) {
			$driver   = new $driver_class();
			$this->db = new DB( $driver );
		}
	}
}
