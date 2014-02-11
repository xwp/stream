<?php
/**
 * Plugin Name: Stream Reports
 * Depends: Stream
 * Plugin URI: http://x-team.com
 * Description: TBD
 * Version: 0.1
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Founda
 * tion, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

class WP_Stream_Reports {

	/**
	 * Holds plugin minimum version
	 *
	 * @const string
	 */
	const STREAM_MIN_VERSION = '1.0.7';

	/**
	 * Hold Stream instance
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
	 * Class constructor
	 */
	private function __construct() {
		define( 'WP_STREAM_REPORTS_DIR', plugin_dir_path( __FILE__ ) );
		define( 'WP_STREAM_REPORTS_URL', plugin_dir_url( __FILE__ ) );
		define( 'WP_STREAM_REPORTS_INC_DIR', WP_STREAM_REPORTS_DIR . 'includes/' );
		define( 'WP_STREAM_REPORTS_VIEW_DIR', WP_STREAM_REPORTS_DIR . 'views/' );
		define( 'WP_STREAM_REPORTS_CLASS_DIR', WP_STREAM_REPORTS_DIR . 'classes/' );

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

		if ( ! $this->is_dependency_satisfied() ) {
			return;
		}

		// Load settings, enabling extensions to hook in
		require_once WP_STREAM_REPORTS_INC_DIR . 'settings.php';
		WP_Stream_Reports_Settings::load();

		// Register new submenu
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
	}

	/**
	 * Register Notification menu under Stream's main one
	 *
	 * @action admin_menu
	 * @return void
	 */
	public function register_menu() {
		self::$screen_id = add_submenu_page(
			'wp_stream',
			__( 'Reports', 'stream-reports' ),
			__( 'Reports', 'stream-reports' ),
			self::VIEW_CAP,
			self::REPORTS_PAGE_SLUG,
			array( $this, 'page' )
		);

		// add_action( 'load-' . self::$screen_id, array( $this, 'page_form_save' ) );
		// add_action( 'load-' . self::$screen_id, array( $this->form, 'load' ) );
	}

	/**
	 * Admin page callback function, redirects to each respective method based
	 * on $_GET['view']
	 *
	 * @return void
	 */
	public function page() {
		$view = (object) array(
			'slug' => 'all',
			'path' => null,
		);
		if ( isset( $_GET['view'] ) && ! empty( $_GET['view'] ) ){
			$view->slug = $_GET['view'];
		}

		// First we check if the file exists in our plugin folder, otherwhise give the user an error
		if ( ! file_exists( WP_STREAM_REPORTS_VIEW_DIR . sanitize_file_name( $view->slug ) . '.php' ) ){
			$view->slug = 'error';
		}

		// Define the path for the view we
		$view->path = WP_STREAM_REPORTS_VIEW_DIR . sanitize_file_name( $view->slug ) . '.php';

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

$GLOBALS['wp_stream_reports'] = WP_Stream_Reports::get_instance();
