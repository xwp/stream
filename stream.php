<?php
/**
 * Plugin Name: Stream
 * Plugin URI: http://x-team.com
 * Description: Track and monitor every change made on your WordPress site. All logged-in user activity is recorded and organized by action and context for easy filtering. Developers can extend Stream with custom connectors to log any kind of action.
 * Version: 0.2
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
class WP_Stream {

	public static $instance;

	public $contexts = array();

	/**
	 * Class constructor
	 */
	public function __construct() {

		define( 'WP_STREAM_DIR', plugin_dir_path( __FILE__ ) );
		define( 'WP_STREAM_URL', plugin_dir_url( __FILE__ ) );
		define( 'WP_STREAM_INC_DIR', WP_STREAM_DIR . 'includes/' );
		define( 'WP_STREAM_CLASS_DIR', WP_STREAM_DIR . 'classes/' );

		// Load settings, enabling extensions to hook in
		require_once WP_STREAM_INC_DIR . 'settings.php';
		add_action( 'plugins_loaded', array( 'WP_Stream_Settings', 'load' ) );

		// Add our new post type
		require_once WP_STREAM_INC_DIR . 'post-type.php';
		add_action( 'init', array( 'WP_Stream_Post_Type', 'load' ) );

		// Load contexts
		require_once WP_STREAM_INC_DIR . 'contexts.php';
		add_action( 'init', array( 'WP_Stream_Contexts', 'load' ) );

		// Load admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );

	}

	/**
	* Enqueue all required admin scripts and styles
	*
	* @return void
	*/
	static function admin_enqueue_scripts() {
		wp_enqueue_style( 'stream_admin_css', WP_STREAM_URL . 'css/' . 'stream-style.css', '0.1' );
	}

	/**
	 * Installation / Upgrade checks
	 * 
	 * @action register_activation_hook
	 * @return void
	 */
	public static function install() {
		// Install plugin tables
		require_once WP_STREAM_INC_DIR . 'install.php';
		WP_Stream_Install::check();
	}

	/**
	 * Return active instance of WP_Stream, create one if it doesn't exist
	 * 
	 * @return WP_Stream
	 */
	public function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}
		return self::$instance;
	}

}

$GLOBALS['wp_stream'] = new WP_Stream;
register_activation_hook( __FILE__, array( 'WP_Stream', 'install' ) );
