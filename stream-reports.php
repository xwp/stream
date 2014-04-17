<?php
/**
 * Plugin Name: Stream Reports
 * Depends: Stream
 * Plugin URI: http://x-team.com
 * Description: TBD
 * Version: 0.1.0
 * Author: X-Team
 * Author URI: http://x-team.com/wordpress/
 * License: GPLv2+
 * Text Domain: stream-reports
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2014 X-Team (http://x-team.com/)
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

class WP_Stream_Reports {

	/**
	 * Holds plugin minimum version
	 *
	 * @const string
	 */
	const STREAM_MIN_VERSION = '1.3.2';

	/**
	 * Holds this plugin version
	 * Used in assets cache
	 *
	 * @const string
	 */
	const VERSION = '0.1.0';

	/**
	 * Hold Stream Reports instance
	 *
	 * @var string
	 */
	public static $instance;

	/**
	 * Screen ID for my admin page
	 * @var string
	 */
	public static $screen_id;

	/**
	 * Holds admin notices messages
	 *
	 * @var array
	 */
	public static $messages = array();

	/**
	 * Page slug for notifications list table screen
	 *
	 * @const string
	 */
	const REPORTS_PAGE_SLUG = 'wp_stream_reports';

	/**
	 * Capability for the Notifications to be viewed
	 *
	 * @const string
	 */
	const VIEW_CAP = 'view_stream_reports';

	/**
	 * Hold the nonce name
	 */
	public static $nonce;

	/**
	 * Class constructor
	 */
	private function __construct() {
		define( 'WP_STREAM_REPORTS_DIR', plugin_dir_path( __FILE__ ) );
		define( 'WP_STREAM_REPORTS_URL', plugin_dir_url( __FILE__ ) );
		define( 'WP_STREAM_REPORTS_INC_DIR', WP_STREAM_REPORTS_DIR . 'includes/' );
		define( 'WP_STREAM_REPORTS_VIEW_DIR', WP_STREAM_REPORTS_DIR . 'views/' );

		add_action( 'plugins_loaded', array( $this, 'load' ) );
	}

	/**
	 * Load our classes, actions/filters, only if our big brother is activated.
	 * GO GO GO!
	 *
	 * @return void
	 */
	public function load() {
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'network_admin_notices', array( $this, 'admin_notices' ) );

		if ( ! $this->is_dependency_satisfied() ) {
			return;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// Load settings, enabling extensions to hook in
		require_once WP_STREAM_REPORTS_INC_DIR . 'settings.php';
		add_action( 'init', array( 'WP_Stream_Reports_Settings', 'load' ), 9 );

		// Load sections here
		require_once WP_STREAM_REPORTS_INC_DIR . 'meta-boxes.php';
		add_action( 'init', array( 'WP_Stream_Reports_Metaboxes', 'get_instance' ), 12 );

		// Load Interval/Date class.
		require_once WP_STREAM_INC_DIR . 'date-interval.php';
		require_once WP_STREAM_REPORTS_INC_DIR . 'date-interval.php';

		add_action( 'init', array( 'WP_Stream_Reports_Date_Interval', 'get_instance' ) );

		// Register new submenu
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'network_admin_menu', array( $this, 'register_menu' ), 11 );


		// Register and enqueue the administration scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'register_ui_assets' ), 20 );
	}

	/**
	 * @param array $ajax_hooks associative array of ajax hooks action to actual functionname
	 * @param object $referer Class refering the action
	 */
	public static function handle_ajax_request( $ajax_hooks, $referer ) {
		// If we are not in ajax mode, return early
		if ( ! defined( 'DOING_AJAX' ) || ! is_object( $referer ) ) {
			return;
		}

		foreach ( $ajax_hooks as $hook => $function ) {
			add_action( "wp_ajax_{$hook}", array( $referer, $function ) );
		}

		// Check referer here so we don't have to check it on every function call
		if ( array_key_exists( $_REQUEST['action'], $ajax_hooks ) ) {
			// Checking permission
			if ( ! current_user_can( WP_Stream_Reports::VIEW_CAP ) ) {
				wp_die( __( 'Cheating huh?', 'stream-reports' ) );
			}
			check_admin_referer( 'stream-reports-page', 'wp_stream_reports_nonce' );
		}
	}

	/**
	 * Register Notification menu under Stream's main one
	 *
	 * @action admin_menu
	 * @return void
	 */
	public function register_menu() {
		self::$screen_id = add_submenu_page(
			WP_Stream_Admin::RECORDS_PAGE_SLUG,
			__( 'Reports', 'stream-reports' ),
			__( 'Reports', 'stream-reports' ),
			self::VIEW_CAP,
			self::REPORTS_PAGE_SLUG,
			array( $this, 'page' )
		);

		// Create nonce right away so it is accessible everywhere
		self::$nonce = array( 'wp_stream_reports_nonce' => wp_create_nonce( 'stream-reports-page' ) );

		$metabox = WP_Stream_Reports_Metaboxes::get_instance();
		add_action( 'load-' . self::$screen_id, array( $metabox, 'load_page' ) );
	}

	/**
	 * Register and enqueue the scripts related to our plugin.
	 *
	 * @action   admin_enqueue_scripts
	 * @uses     wp_register_script
	 * @uses     wp_enqueue_script
	 *
	 * @param $pagename the actual page name
	 *
	 * @return void
	 */
	public function register_ui_assets( $pagename ) {
		// JavaScript registration
		wp_register_script(
			'stream-reports-d3',
			WP_STREAM_REPORTS_URL . 'ui/js/d3/d3.min.js',
			array(),
			'3.4.2',
			true
		);

		wp_register_script(
			'stream-reports-nvd3',
			WP_STREAM_REPORTS_URL . 'ui/js/nvd3/nv.d3.min.js',
			array( 'stream-reports-d3' ),
			'1.1.15b',
			true
		);
		wp_register_script(
			'stream-reports',
			WP_STREAM_REPORTS_URL . 'ui/js/stream-reports.js',
			array( 'stream-reports-nvd3', 'jquery', 'underscore', 'jquery-ui-datepicker' ),
			self::VERSION,
			true
		);

		// CSS registration
		wp_register_style(
			'stream-reports-nvd3',
			WP_STREAM_REPORTS_URL . 'ui/css/nvd3/nv.d3.min.css',
			array(),
			self::VERSION,
			'screen'
		);

		wp_register_style(
			'stream-reports',
			WP_STREAM_REPORTS_URL . 'ui/css/stream-reports.css',
			array( 'stream-reports-nvd3', 'wp-stream-datepicker' ),
			self::VERSION,
			'screen'
		);

		// If we are not on the right page we return early
		if ( $pagename !== self::$screen_id ) {
			return;
		}

		// Localization
		wp_localize_script(
			'stream-reports',
			'streamReportsLocal',
			array(
				'configure' => __( 'Configure', 'stream-reports' ),
				'cancel'    => __( 'Cancel', 'stream-reports' ),
				'deletemsg' => __( 'Do you really want to delete this section? This cannot be undone.', 'stream-reports' )
			)
		);

		// JavaScript enqueue
		wp_enqueue_script(
			array(
				'stream-reports',
				'select2',
				'common',
				'dashboard',
				'postbox',
			)
		);

		// CSS enqueue
		wp_enqueue_style(
			array(
				'stream-reports',
				'select2',
			)
		);
	}

	/**
	 * Admin page callback function, redirects to each respective method based
	 * on $_GET['view']
	 *
	 * @return void
	 */
	public function page() {
		// Page class
		$class   = 'metabox-holder columns-' . get_current_screen()->get_columns();
		$add_url = add_query_arg(
			array_merge(
				array(
					'action' => 'wp_stream_reports_add_metabox',
				),
				self::$nonce
			),
			admin_url( 'admin-ajax.php' )
		);

		$view = (object) array(
			'slug' => 'all',
			'path' => null,
		);

		// Avoid throwing Notices by testing the variable
		if ( isset( $_GET['view'] ) && ! empty( $_GET['view'] ) ){
			$view->slug = sanitize_file_name( wp_unslash( $_GET['view'] ) );
		}

		// First we check if the file exists in our plugin folder, otherwhise give the user an error
		if ( ! file_exists( WP_STREAM_REPORTS_VIEW_DIR . $view->slug . '.php' ) ){
			$view->slug = 'error';
		}

		// Define the path for the view we
		$view->path = WP_STREAM_REPORTS_VIEW_DIR . $view->slug . '.php';

		// Execute some actions before including the view, to allow others to hook in here
		// Use these to do stuff related to the view you are working with
		do_action( 'stream-reports-view', $view );
		do_action( "stream-reports-view-{$view->slug}", $view );

		include_once $view->path;
	}

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		$message = '';

		if ( ! class_exists( 'WP_Stream' ) ) {
			$message .= sprintf( '<p>%s</p>', __( 'Stream Reports requires Stream plugin to be present and activated.', 'stream-reports' ) );
		} else if ( version_compare( WP_Stream::VERSION, self::STREAM_MIN_VERSION, '<' ) ) {
			$message .= sprintf( '<p>%s</p>', sprintf( __( 'Stream Reports requires Stream version %s or higher', 'stream-reports' ), self::STREAM_MIN_VERSION ) );
		}

		if ( ! empty( $message ) ) {
			self::$messages['wp_stream_db_error'] = sprintf(
				'<div class="error">%s<p>%s</p></div>',
				$message,
				sprintf(
					__( 'Please <a href="%s" target="_blank">install</a> Stream plugin version %s or higher for Stream Reports to work properly.', 'stream-reports' ),
					esc_url( 'http://wordpress.org/plugins/stream/' ),
					self::STREAM_MIN_VERSION
				)
			); // xss okay

			return false;
		}

		return true;
	}

	/**
	 * Display all messages on admin board
	 *
	 * @return void
	 */
	public static function admin_notices() {
		foreach ( self::$messages as $message ) {
			echo wp_kses_post( $message );
		}
	}

	/**
	 * Return active instance of WP_Stream_Reports, create one if it doesn't exist
	 *
	 * @return WP_Stream_Reports
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}
		return self::$instance;
	}

}

$GLOBALS['wp_stream_reports'] = WP_Stream_Reports::get_instance();
