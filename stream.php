<?php
/**
 * Plugin Name: Stream
 * Plugin URI: https://wp-stream.com/
 * Description: Stream tracks logged-in user activity so you can monitor every change made on your WordPress site in beautifully organized detail. All activity is organized by context, action and IP address for easy filtering. Developers can extend Stream with custom connectors to log any kind of action.
 * Version: 2.0.3
 * Author: Stream
 * Author URI: https://wp-stream.com/
 * License: GPLv2+
 * Text Domain: stream
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2014 WP Stream Pty Ltd (https://wp-stream.com/)
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
	 * @const string
	 */
	const VERSION = '2.0.3';

	/**
	 * WP-CLI command
	 *
	 * @const string
	 */
	const WP_CLI_COMMAND = 'stream';

	/**
	 * Hold Stream instance
	 *
	 * @var string
	 */
	public static $instance;

	/**
	 * @var WP_Stream_DB_Base
	 */
	public static $db;

	/**
	 * @var WP_Stream_API
	 */
	public static $api;

	/**
	 * Admin notices, collected and displayed on proper action
	 *
	 * @var array
	 */
	public static $notices = array();

	/**
	 * An array of deprecated extension plugin class/dir pairs
	 *
	 * @var array
	 */
	public static $deprecated_extensions = array(
		'WP_Stream_Cherry_Pick'   => 'stream-cherry-pick/stream-cherry-pick.php',
		'WP_Stream_Data_Exporter' => 'stream-data-exporter/stream-data-exporter.php',
		'WP_Stream_Notifications' => 'stream-notifications/stream-notifications.php',
		'WP_Stream_Reports'       => 'stream-reports/stream-reports.php',
	);

	/**
	 * Class constructor
	 */
	private function __construct() {
		define( 'WP_STREAM_PLUGIN', plugin_basename( __FILE__ ) );
		define( 'WP_STREAM_DIR', plugin_dir_path( __FILE__ ) );
		define( 'WP_STREAM_URL', plugin_dir_url( __FILE__ ) );
		define( 'WP_STREAM_INC_DIR', WP_STREAM_DIR . 'includes/' );
		define( 'WP_STREAM_CLASS_DIR', WP_STREAM_DIR . 'classes/' );
		define( 'WP_STREAM_EXTENSIONS_DIR', WP_STREAM_DIR . 'extensions/' );

		spl_autoload_register( array( $this, 'autoload' ) );

		// Load helper functions
		require_once WP_STREAM_INC_DIR . 'functions.php';

		// Load DB helper interface/class
		$driver = 'WP_Stream_DB';
		if ( class_exists( $driver ) ) {
			self::$db = new $driver;
		}

		if ( ! self::$db ) {
			wp_die( __( 'Stream: Could not load chosen DB driver.', 'stream' ), 'Stream DB Error' );
		}

		/**
		 * Filter allows a custom Stream API class to be instantiated
		 *
		 * @since 2.0.2
		 *
		 * @return object  The API class object
		 */
		self::$api = apply_filters( 'wp_stream_api_class', new WP_Stream_API );

		// Install the plugin
		add_action( 'wp_stream_before_db_notices', array( __CLASS__, 'install' ) );

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
	 * Invoked when the PHP version check fails
	 *
	 * Load up the translations and add the error message to the admin notices.
	 *
	 * @return void
	 */
	public static function fail_php_version() {
		add_action( 'plugins_loaded', array( __CLASS__, 'i18n' ) );
		self::notice( __( 'Stream requires PHP version 5.3+, plugin is currently NOT ACTIVE.', 'stream' ) );
	}

	/**
	 * Check for deprecated extension plugins
	 *
	 * @return bool
	 */
	public static function deprecated_plugins_exist() {
		foreach ( self::$deprecated_extensions as $class => $dir ) {
			if ( class_exists( $class ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Display admin notices when deprecated extension plugins exist
	 *
	 * @return void
	 */
	public static function deprecated_plugins_notice() {
		add_action( 'plugins_loaded', array( __CLASS__, 'i18n' ) );

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$message = null;

		foreach ( self::$deprecated_extensions as $class => $dir ) {
			if ( class_exists( $class ) ) {
				$data     = get_plugin_data( sprintf( '%s/%s', WP_PLUGIN_DIR, $dir ) );
				$name     = isset( $data['Name'] ) ? $data['Name'] : $dir;
				$message .= sprintf( '<li><strong>%s</strong></li>', esc_html( $name ) );

				deactivate_plugins( $dir );
			}
		}

		if ( ! empty( $message ) ) {
			ob_start();
			?>
			<h3><?php _e( 'Deprecated Plugins Found', 'stream' ) ?></h3>
			<p><?php _e( 'The following plugins are deprecated and will be deactivated in order to activate', 'stream' ) ?> <strong>Stream <?php echo esc_html( self::VERSION ) ?></strong>:</p>
			<ul>
			<?php
			$start = ob_get_clean();

			ob_start();
			?>
			</ul>
			<p>
				<a href='#' onclick="location.reload(true); return false;" class="button button-large"><?php _e( 'Continue', 'stream' ) ?></a>
			</p>
			<?php
			$end = ob_get_clean();

			wp_die( $start . $message . $end, __( 'Deprecated Plugins Found', 'stream' ) );
		}
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
	 * Is Stream connected?
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return ( self::$api->api_key && self::$api->site_uuid );
	}

	/**
	 * Is Stream in development mode?
	 *
	 * @return bool
	 */
	public static function is_development_mode() {
		$development_mode = false;

		if ( defined( 'WP_STREAM_DEV_DEBUG' ) ) {
			$development_mode = WP_STREAM_DEV_DEBUG;
		} else if ( site_url() && false === strpos( site_url(), '.' ) ) {
			$development_mode = true;
		}

		/**
		 * Filter allows development mode to be overridden
		 *
		 * @since 2.0.0
		 *
		 * @return bool
		 */
		return apply_filters( 'wp_stream_development_mode', $development_mode );
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
		if ( defined( 'WP_CLI' ) ) {
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
	 * Return active instance of WP_Stream, create one if it doesn't exist
	 *
	 * @return WP_Stream
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

}

if ( ! WP_Stream::is_valid_php_version() ) {
	WP_Stream::fail_php_version();
} elseif ( WP_Stream::deprecated_plugins_exist() ) {
	WP_Stream::deprecated_plugins_notice();
} else {
	$GLOBALS['wp_stream'] = WP_Stream::get_instance();
}

register_deactivation_hook( __FILE__, array( 'WP_Stream_Admin', 'remove_api_authentication' ) );
