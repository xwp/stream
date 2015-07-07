<?php
namespace WP_Stream;

class Plugin {
	/**
	 * Plugin version number
	 *
	 * @const string
	 */
	const VERSION = '3.0.0';

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
	 * @var Install
	 */
	public $install;

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
			wp_die(
				esc_html__( 'Stream: Could not load chosen DB driver.', 'stream' ),
				esc_html__( 'Stream DB Error', 'stream' )
			);
		}

		// Check DB and display an admin notice if there are tables missing
		add_action( 'init', array( $this, 'verify_db' ) );

		// Install the plugin
		add_action( 'wp_stream_before_db_notices', array( $this, 'install' ) );

		// Load languages
		add_action( 'plugins_loaded', array( $this, 'i18n' ) );

		// Load settings, enabling extensions to hook in
		add_action( 'init', function() {
			$this->settings = new Settings( $this );
		}, 9 );

		// Load logger class
		$this->log = apply_filters( 'wp_stream_log_handler', new Log( $this ) );

		// Load connectors after widgets_init, but before the default of 10
		add_action( 'init', function() {
			$this->connectors = new Connectors( $this );
		}, 9 );

		// Load support for feeds
		$this->feeds = new Feeds( $this );

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
	 * Autoloader for classes
	 *
	 * @param string $class
	 */
	function autoload( $class ) {
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
		load_plugin_textdomain( 'stream', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
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
			add_action( 'shutdown', array( $this, 'admin_notices' ) );

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
	 * Verify that the required DB tables exists
	 *
	 * @return void
	 */
	public function verify_db() {
		/**
		 * Filter will halt install() if set to true
		 *
		 * @param bool
		 *
		 * @return bool
		 */
		if ( apply_filters( 'wp_stream_no_tables', false ) ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		global $wpdb;

		$database_message  = '';
		$uninstall_message = '';

		// Check if all needed DB is present
		$missing_tables = array();

		foreach ( $this->db->get_table_names() as $table_name ) {
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
				$missing_tables[] = $table_name;
			}
		}

		if ( $missing_tables ) {
			$database_message .= sprintf(
				'%s <strong>%s</strong>',
				_n(
					'The following table is not present in the WordPress database:',
					'The following tables are not present in the WordPress database:',
					count( $missing_tables ),
					'stream'
				),
				esc_html( implode( ', ', $missing_tables ) )
			);
		}

		if ( is_plugin_active_for_network( $this->locations['plugin'] ) && current_user_can( 'manage_network_plugins' ) ) {
			$uninstall_message = sprintf( __( 'Please <a href="%s">uninstall</a> the Stream plugin and activate it again.', 'stream' ), network_admin_url( 'plugins.php#stream' ) );
		} elseif ( current_user_can( 'activate_plugins' ) ) {
			$uninstall_message = sprintf( __( 'Please <a href="%s">uninstall</a> the Stream plugin and activate it again.', 'stream' ), admin_url( 'plugins.php#stream' ) );
		}

		/**
		 * Fires before admin notices are triggered for missing database tables.
		 */
		do_action( 'wp_stream_before_db_notices' );

		if ( ! empty( $database_message ) ) {
			$this->notice( $database_message );

			if ( ! empty( $uninstall_message ) ) {
				$this->notice( $uninstall_message );
			}
		}
	}

	/**
	 * DB installation and upgrades
	 *
	 * @action register_activation_hook
	 *
	 * @return void
	 */
	public function install() {
		if ( is_admin() ) {
			$this->install = new Install( $this );
		}
	}

	/**
	 * DB installation and upgrades
	 *
	 * @action register_activation_hook
	 *
	 * @return void
	 */
	public function update_activation_hook() {
		$this->register_update_hook(
			dirname( plugin_basename( __FILE__ ) ),
			array( $this, 'install' ),
			$this->get_version()
		);
	}

	/**
	 * Register a routine to be called when stream or a stream connector has been updated
	 * It works by comparing the current version with the version previously stored in the database.
	 *
	 * @param string $file     A reference to the main plugin file
	 * @param string $callback The function to run when the hook is called.
	 * @param string $version  The version to which the plugin is updating.
	 *
	 * @return void
	 */
	public function register_update_hook( $file, $callback, $version ) {
		if ( ! is_admin() ) {
			return;
		}

		$plugin = plugin_basename( $file );

		if ( is_plugin_active_for_network( $plugin ) ) {
			$current_versions = get_site_option( $this->install->key . '_connectors', array() );
			$network          = true;
		} elseif ( is_plugin_active( $plugin ) ) {
			$current_versions = get_option( $this->install->key . '_connectors', array() );
			$network          = false;
		} else {
			return;
		}

		if ( version_compare( $version, $current_versions[ $plugin ], '>' ) ) {
			call_user_func( $callback, $current_versions[ $plugin ], $network );

			$current_versions[ $plugin ] = $version;
		}

		if ( $network ) {
			update_site_option( $this->install->key . '_registered_connectors', $current_versions );
		} else {
			update_option( $this->install->key . '_registered_connectors', $current_versions );
		}

		return;
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