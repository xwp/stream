<?php
/**
 * Plugin Name: Stream
 * Plugin URI: https://wp-stream.com/
 * Description: Stream tracks logged-in user activity so you can monitor every change made on your WordPress site in beautifully organized detail. All activity is organized by context, action and IP address for easy filtering. Developers can extend Stream with custom connectors to log any kind of action.
 * Version: 2.0.5
 * Author: Stream
 * Author URI: https://wp-stream.com/
 * License: GPLv2+
 * Text Domain: stream
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2015 WP Stream Pty Ltd (https://wp-stream.com/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

class WP_Stream {

	/**
	 * Plugin version number
	 *
	 * @var string
	 */
	const VERSION = '2.0.5';

	/**
	 * WP-CLI command
	 *
	 * @var string
	 */
	const WP_CLI_COMMAND = 'stream';

	/**
	 * Hold Stream instance
	 *
	 * @access public
	 * @static
	 *
	 * @var string
	 */
	public static $instance;

	/**
	 * @access public
	 * @static
	 *
	 * @var WP_Stream_DB_Base
	 */
	public static $db;

	/**
	 * Admin notices, collected and displayed on proper action
	 *
	 * @access public
	 * @static
	 *
	 * @var array
	 */
	public static $notices = array();

	/**
	 * Class constructor
	 *
	 * @access private
	 */
	private function __construct() {
		$locate = $this->locate_plugin();

		define( 'WP_STREAM_PLUGIN', $locate['plugin_basename'] );
		define( 'WP_STREAM_DIR', $locate['dir_path'] );
		define( 'WP_STREAM_URL', $locate['dir_url'] );
		define( 'WP_STREAM_INC_DIR', WP_STREAM_DIR . 'includes/' );
		define( 'WP_STREAM_CLASS_DIR', WP_STREAM_DIR . 'classes/' );
		define( 'WP_STREAM_EXTENSIONS_DIR', WP_STREAM_DIR . 'extensions/' );

		spl_autoload_register( array( $this, 'autoload' ) );

		// Instantiate DB driver
		self::$db = new WP_Stream_DB;

		if ( ! self::$db ) {
			wp_die(
				__( 'Stream: Could not load chosen DB driver.', 'stream' ),
				__( 'Stream DB Error', 'stream' )
			);
		}

		// Check DB and display an admin notice if there are tables missing
		add_action( 'init', array( $this, 'verify_db' ) );

		// Install the plugin
		add_action( 'wp_stream_before_db_notices', array( __CLASS__, 'install' ) );

		// Load helper functions
		require_once WP_STREAM_INC_DIR . 'functions.php';

		// Load languages
		add_action( 'plugins_loaded', array( __CLASS__, 'i18n' ) );

		// Load settings, enabling extensions to hook in
		add_action( 'init', array( 'WP_Stream_Settings', 'load' ), 9 );

		// Load logger class
		add_action( 'plugins_loaded', array( 'WP_Stream_Log', 'load' ) );

		// Load connectors after widgets_init, but before the default of 10
		add_action( 'init', array( 'WP_Stream_Connectors', 'load' ), 9 );

		// Load extensions
		foreach ( glob( WP_STREAM_EXTENSIONS_DIR . '*' ) as $extension ) {
			require_once sprintf( '%s/class-wp-stream-%s.php', $extension, basename( $extension ) );
		}

		// Load support for feeds
		add_action( 'init', array( 'WP_Stream_Feeds', 'load' ) );

		// Add frontend indicator
		add_action( 'wp_head', array( $this, 'frontend_indicator' ) );

		// Load admin area classes
		if ( is_admin() ) {
			add_action( 'init', array( 'WP_Stream_Admin', 'load' ) );
			add_action( 'init', array( 'WP_Stream_Dashboard_Widget', 'load' ) );
			add_action( 'init', array( 'WP_Stream_Live_Update', 'load' ) );
			add_action( 'init', array( 'WP_Stream_Pointers', 'load' ) );
			add_action( 'init', array( 'WP_Stream_Unread', 'load' ) );
			add_action( 'init', array( 'WP_Stream_Migrate', 'load' ) );
		}

		// Disable logging during the content import process
		add_filter( 'wp_stream_record_array', array( __CLASS__, 'disable_logging_during_import' ), 10, 1 );

		// Load WP-CLI command
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once WP_STREAM_INC_DIR . 'wp-cli.php';

			WP_CLI::add_command( self::WP_CLI_COMMAND, 'WP_Stream_WP_CLI_Command' );
		}
	}

	/**
	 * Return an active instance of this class, and create one if it doesn't exist
	 *
	 * @access public
	 * @static
	 *
	 * @return WP_Stream
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Invoked when the PHP version check fails
	 *
	 * Load up the translations and add the error message to the admin notices.
	 *
	 * @access public
	 * @static
	 *
	 * @return void
	 */
	public static function fail_php_version() {
		add_action( 'plugins_loaded', array( __CLASS__, 'i18n' ) );
		self::notice( esc_html__( 'Stream requires PHP version 5.3+, plugin is currently NOT ACTIVE.', 'stream' ) );
	}

	/**
	 * Verify that the required DB tables exists
	 *
	 * @access public
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

		foreach ( self::$db->get_table_names() as $table_name ) {
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

		if ( is_plugin_active_for_network( WP_STREAM_PLUGIN ) && current_user_can( 'manage_network_plugins' ) ) {
			$uninstall_message = sprintf( __( 'Please <a href="%s">uninstall</a> the Stream plugin and activate it again.', 'stream' ), network_admin_url( 'plugins.php#stream' ) );
		} elseif ( current_user_can( 'activate_plugins' ) ) {
			$uninstall_message = sprintf( __( 'Please <a href="%s">uninstall</a> the Stream plugin and activate it again.', 'stream' ), admin_url( 'plugins.php#stream' ) );
		}

		/**
		 * Fires before admin notices are triggered for missing database tables.
		 */
		do_action( 'wp_stream_before_db_notices' );

		if ( ! empty( $database_message ) ) {
			self::notice( $database_message );

			if ( ! empty( $uninstall_message ) ) {
				self::notice( $uninstall_message );
			}
		}
	}

	/**
	 * DB installation and upgrades
	 *
	 * @action register_activation_hook
	 *
	 * @access public
	 * @static
	 *
	 * @return void
	 */
	public static function install() {
		WP_Stream_Install::get_instance();
	}

	/**
	 * DB installation and upgrades
	 *
	 * @action register_activation_hook
	 *
	 * @access public
	 * @static
	 *
	 * @return void
	 */
	public static function update_activation_hook() {
		self::register_update_hook(
			dirname( plugin_basename( __FILE__ ) ),
			array( __CLASS__, 'install' ),
			self::VERSION
		);
	}

	/**
	 * Register a routine to be called when stream or a stream connector has been updated
	 * It works by comparing the current version with the version previously stored in the database.
	 *
	 * @access public
	 * @static
	 *
	 * @param string $file     A reference to the main plugin file
	 * @param string $callback The function to run when the hook is called.
	 * @param string $version  The version to which the plugin is updating.
	 *
	 * @return void
	 */
	public static function register_update_hook( $file, $callback, $version ) {
		if ( ! is_admin() ) {
			return;
		}

		$plugin = plugin_basename( $file );

		if ( is_plugin_active_for_network( $plugin ) ) {
			$current_versions = get_site_option( WP_Stream_Install::KEY . '_connectors', array() );
			$network          = true;
		} elseif ( is_plugin_active( $plugin ) ) {
			$current_versions = get_option( WP_Stream_Install::KEY . '_connectors', array() );
			$network          = false;
		} else {
			return;
		}

		if ( version_compare( $version, $current_versions[ $plugin ], '>' ) ) {
			call_user_func( $callback, $current_versions[ $plugin ], $network );

			$current_versions[ $plugin ] = $version;
		}

		if ( $network ) {
			update_site_option( WP_Stream_Install::KEY . '_registered_connectors', $current_versions );
		} else {
			update_option( WP_Stream_Install::KEY . '_registered_connectors', $current_versions );
		}

		return;
	}

	/**
	 * Autoloader for classes
	 *
	 * @param string $class
	 *
	 * @return void
	 */
	function autoload( $class ) {
		$class      = strtolower( str_replace( '_', '-', $class ) );
		$class_file = sprintf( '%sclass-%s.php', WP_STREAM_CLASS_DIR, $class );

		if ( is_readable( $class_file ) ) {
			require_once $class_file;
		}
	}

	/**
	 * Loads the translation files.
	 *
	 * @action plugins_loaded
	 *
	 * @return void
	 */
	public static function i18n() {
		load_plugin_textdomain( 'stream', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Whether the current PHP version meets the minimum requirements
	 *
	 * @return bool
	 */
	public static function is_valid_php_version() {
		return version_compare( PHP_VERSION, '5.3', '>=' );
	}

	/**
	 * Check if Stream is running on WordPress.com VIP
	 *
	 * @return bool
	 */
	public static function is_vip() {
		return function_exists( 'wpcom_vip_load_plugin' );
	}

	/**
	 * True if native WP Cron is enabled, otherwise false
	 *
	 * @return bool
	 */
	public static function is_wp_cron_enabled() {
		return ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? false : true;
	}

	/**
	 * Disable logging during the content import process
	 *
	 * @filter wp_stream_record_array
	 *
	 * @param array $records
	 *
	 * @return array
	 */
	public static function disable_logging_during_import( $records ) {
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
	 *
	 * @return void
	 */
	public static function notice( $message, $is_error = true ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$message = strip_tags( $message );

			if ( $is_error ) {
				WP_CLI::warning( $message );
			} else {
				WP_CLI::success( $message );
			}
		} else {
			// Trigger admin notices late, so that any notices which occur during page load are displayed
			add_action( 'shutdown', array( __CLASS__, 'admin_notices' ) );

			$notice = compact( 'message', 'is_error' );

			if ( ! in_array( $notice, self::$notices ) ) {
				self::$notices[] = $notice;
			}
		}
	}

	/**
	 * Show an error or other message in the WP Admin
	 *
	 * @action shutdown
	 *
	 * @return void
	 */
	public static function admin_notices() {
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

		foreach ( self::$notices as $notice ) {
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
		$comment = sprintf( 'Stream WordPress user activity plugin v%s', esc_html( self::VERSION ) ); // Localization not needed

		/**
		 * Filter allows the HTML output of the frontend indicator comment
		 * to be altered or removed, if desired.
		 *
		 * @since 1.4.5
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

}

if ( WP_Stream::is_valid_php_version() ) {
	$GLOBALS['wp_stream'] = WP_Stream::get_instance();
	register_activation_hook( __FILE__, array( 'WP_Stream', 'install' ) );
} else {
	WP_Stream::fail_php_version();
}
