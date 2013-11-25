<?php
/**
 * Plugin Name: Stream
 * Plugin URI: http://x-team.com
 * Description: Track and monitor every change made on your WordPress site. All logged-in user activity is recorded and organized by action and context for easy filtering. Developers can extend Stream with custom connectors to log any kind of action.
 * Version: 0.1
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

		define( 'X_STREAM_DIR', plugin_dir_path( __FILE__ ) );
		define( 'X_STREAM_URL', plugin_dir_url( __FILE__ ) );
		define( 'X_STREAM_INC_DIR', X_STREAM_DIR . 'includes/' );
		define( 'X_STREAM_CLASS_DIR', X_STREAM_DIR . 'classes/' );

		// Load settings, enabling extensions to hook in
		require_once X_STREAM_INC_DIR . 'settings.php';
		add_action( 'plugins_loaded', array( 'X_Stream_Settings', 'load' ) );

		// Add our new post type
		require_once X_STREAM_INC_DIR . 'post-type.php';
		add_action( 'init', array( 'X_Stream_Post_Type', 'load' ) );

		// Load contexts
		require_once X_STREAM_INC_DIR . 'contexts.php';
		add_action( 'init', array( 'X_Stream_Contexts', 'load' ) );

		// Load admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );

	}

	/**
	* Enqueue all required admin scripts and styles
	*
	* @return void
	*/
	static function admin_enqueue_scripts() {
		wp_enqueue_style( 'stream_admin_css', X_STREAM_URL . 'css/' . 'stream-style.css', '0.1' );
	}

	/**
	 * Return active instance of WP_Stream, create one if it doesn't exist
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