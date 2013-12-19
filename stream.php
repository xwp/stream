<?php
/**
 * Plugin Name: Stream
 * Plugin URI: http://x-team.com
 * Description: Track and monitor every change made on your WordPress site. All logged-in user activity is recorded and organized by action and context for easy filtering. Developers can extend Stream with custom connectors to log any kind of action.
 * Version: 0.8.1
 * Author: X-Team
 * Author URI: http://x-team.com/wordpress/
 * License: GPLv2+
 * Text Domain: stream
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2013 X-Team (http://x-team.com/)
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

load_plugin_textdomain( 'stream', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

class WP_Stream {

	public static $instance;

	/**
	 * @var WP_Stream_DB
	 */
	public $db = null;

	/**
	 * Class constructor
	 */
	public function __construct() {

		if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
			add_action( 'all_admin_notices', array( __CLASS__, 'php_version_notice' ) );
			return;
		}

		define( 'WP_STREAM_DIR', plugin_dir_path( __FILE__ ) );
		define( 'WP_STREAM_URL', plugin_dir_url( __FILE__ ) );
		define( 'WP_STREAM_INC_DIR', WP_STREAM_DIR . 'includes/' );
		define( 'WP_STREAM_CLASS_DIR', WP_STREAM_DIR . 'classes/' );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// Load settings, enabling extensions to hook in
		require_once WP_STREAM_INC_DIR . 'settings.php';
		add_action( 'plugins_loaded', array( 'WP_Stream_Settings', 'load' ) );

		// Load logger class
		require_once WP_STREAM_INC_DIR . 'log.php';
		add_action( 'plugins_loaded', array( 'WP_Stream_Log', 'load' ) );

		// Load connectors
		require_once WP_STREAM_INC_DIR . 'connectors.php';
		add_action( 'init', array( 'WP_Stream_Connectors', 'load' ) );

		// Load DB helper class
		require_once WP_STREAM_INC_DIR . 'db-actions.php';
		$this->db = new WP_Stream_DB;

		// Load query class
		require_once WP_STREAM_INC_DIR . 'query.php';
		require_once WP_STREAM_INC_DIR . 'context-query.php';

		// Load support for feeds
		require_once WP_STREAM_INC_DIR . 'feeds.php';
		add_action( 'init', array( 'WP_Stream_Feeds', 'load' ) );

		if ( is_admin() ) {
			require_once WP_STREAM_INC_DIR . 'admin.php';
			add_action( 'plugins_loaded', array( 'WP_Stream_Admin', 'load' ) );
		}
	}

	/**
	 * Installation / Upgrade checks
	 *
	 * @action register_activation_hook
	 * @return void
	 */
	public static function install() {
		if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
			add_action( 'all_admin_notices', array( __CLASS__, 'php_version_notice' ) );
			return;
		}
		// Install plugin tables
		require_once WP_STREAM_INC_DIR . 'install.php';
		WP_Stream_Install::check();
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

	/**
	 * Display a notice about php version
	 *
	 * @action all_admin_notices
	 */
	public static function php_version_notice() {
		echo sprintf(
			'<div class="error"><p>%s</p></div>',
			__( 'Stream requires PHP version 5.3+, plugin is currently NOT ACTIVE.', 'stream' )
			); // xss okay
	}

}

$GLOBALS['wp_stream'] = new WP_Stream;
register_activation_hook( __FILE__, array( 'WP_Stream', 'install' ) );
