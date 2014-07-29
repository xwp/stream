<?php
/**
 * Plugin Name: Stream Notifications
 * Depends: Stream
 * Plugin URI: https://wp-stream.com/extension/notifications/
 * Description: Stream Notifications allows you to create custom rules that will notify you when certain actions are performed in the WordPress admin.
 * Version: 0.1.2
 * Author: Stream
 * Author URI: https://wp-stream.com/
 * License: GPLv2+
 * Text Domain: stream-notifications
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

class WP_Stream_Notifications {

	/**
	 * Holds plugin minimum version
	 *
	 * @const string
	 */
	const STREAM_MIN_VERSION = '1.4.6';

	/**
	 * Holds this plugin version
	 * Used in assets cache
	 *
	 * @const string
	 */
	const VERSION = '0.1.2';

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
	 * @var WP_Stream_Notifications_Network
	 */
	public $network = null;

	/**
	 * Page slug for notifications list table screen
	 *
	 * @const string
	 */
	const NOTIFICATIONS_PAGE_SLUG = 'wp_stream_notifications';
	// Todo: We should probably check whether the current user has caps to
	// view and edit the notifications as this can differ from caps to Stream.

	/**
	 * Holds admin notices messages
	 *
	 * @var array
	 */
	public static $messages = array();

	/*
	 * List of registered adapters
	 * @var array
	 */
	public static $adapters = array();

	/**
	 * Matcher object
	 *
	 * @var  WP_Stream_Notifications_Matcher
	 */
	public $matcher;

	/**
	 * Capability for the Notifications to be viewed
	 *
	 * @const string
	 */
	const VIEW_CAP = 'view_stream_notifications';


	/**
	 * Return active instance of this class, create one if it doesn't exist
	 *
	 * @return WP_Stream_Notifications
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	private function __construct() {
		define( 'WP_STREAM_NOTIFICATIONS_DIR', WP_STREAM_EXTENSIONS_DIR . 'notifications/' ); // Has trailing slash
		define( 'WP_STREAM_NOTIFICATIONS_URL', WP_STREAM_URL . 'extensions/notifications/' ); // Has trailing slash
		define( 'WP_STREAM_NOTIFICATIONS_INC_DIR', WP_STREAM_NOTIFICATIONS_DIR . 'includes/' ); // Has trailing slash

		add_action( 'plugins_loaded', array( $this, 'load' ) );

		// Register post type
		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'post-type.php';
		WP_Stream_Notifications_Post_Type::get_instance();
	}

	/**
	 * Load our classes, actions/filters, only if our big brother is activated.
	 * GO GO GO!
	 *
	 * @return void
	 */
	public function load() {

		// Plugin dependency and admin notices
		if ( ! $this->is_dependency_satisfied() ) {
			add_action( 'all_admin_notices', array( $this, 'admin_notices' ) );
			return;
		}

		// Include all adapters
		include_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'adapter.php';
		$adapters = array( 'email', 'push' );
		foreach ( $adapters as $adapter ) {
			include WP_STREAM_NOTIFICATIONS_INC_DIR . 'adapters/' . $adapter . '.php';
		}

		// Load settings, enabling extensions to hook in
		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'settings.php';
		add_action( 'init', array( 'WP_Stream_Notifications_Settings', 'load' ), 9 );

		// Load network class
		if ( is_multisite() ) {

			require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'network.php';
			$this->network = new WP_Stream_Notifications_Network;

			require_once WP_ADMIN . '/includes/plugins.php';
			if ( is_plugin_active_for_network( WP_STREAM_PLUGIN ) ) {
				add_action( 'network_admin_menu', array( $this, 'register_menu' ), 11 );
			}

			// Allow Stream to override the admin_menu creation if on multisite
			add_filter( 'wp_stream_notifications_disallow_site_access', array( 'WP_Stream_Network', 'disable_admin_access' ) );
		}

		if ( ! apply_filters( 'wp_stream_notifications_disallow_site_access', false ) ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		}

		// Load Matcher
		include_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'matcher.php';
		$this->matcher = new WP_Stream_Notifications_Matcher();

		// Register to Stream updates
		if ( class_exists( 'WP_Stream_Updater' ) ) {
			WP_Stream_Updater::instance()->register( plugin_basename( __FILE__ ) );
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
			'wp_stream',
			__( 'Notifications', 'stream-notifications' ),
			__( 'Notifications', 'stream-notifications' ),
			self::VIEW_CAP,
			'edit.php?post_type=stream-notification'
		);

		add_submenu_page(
			'wp_stream',
			__( 'Add rule', 'stream-notifications' ),
			__( '--- Add rule', 'stream-notifications' ),
			self::VIEW_CAP,
			'post-new.php?post_type=stream-notification'
		);
	}

	public static function register_adapter( $adapter, $name, $title ) {
		self::$adapters[ $name ] = array(
			'title' => $title,
			'class' => $adapter,
		);
	}

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		$message = '';

		if ( ! class_exists( 'WP_Stream' ) ) {
			$message .= sprintf( '<p>%s</p>', __( 'Stream Notifications requires Stream plugin to be present and activated.', 'stream-notifications' ) );
		} else if ( version_compare( WP_Stream::VERSION, self::STREAM_MIN_VERSION, '<' ) ) {
			$message .= sprintf( '<p>%s</p>', sprintf( __( 'Stream Notifications requires Stream version %s or higher', 'stream-notifications' ), self::STREAM_MIN_VERSION ) );
		}

		if ( ! empty( $message ) ) {
			self::$messages['wp_stream_db_error'] = sprintf(
				'<div class="error">%s<p>%s</p></div>',
				$message,
				sprintf(
					__( 'Please <a href="%s" target="_blank">install</a> Stream plugin version %s or higher for Stream Notifications to work properly.', 'stream-notifications' ),
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
	 * Plugin activation routine
	 * @return void
	 */
	public function on_activation() {
		// Add sample rule
		$args = array(
			'post_type' => WP_Stream_Notifications_Post_Type::POSTTYPE,
			'post_status' => 'any',
			'posts_per_page' => 1,
		);
		if ( ! get_posts( $args ) ) {
			add_action( 'plugins_loaded', array( $this, 'add_sample_rule' ), 11 );
		}
	}

	/**
	 * Add a sample rule, used upon activation
	 *
	 */
	public function add_sample_rule() {
		$postarr = array(
			'post_title'    => __( 'Sample Rule', 'stream-notifications' ),
			'post_status' => 'draft',
			'post_type'       => 'stream-notification',
		);

		$meta = array(
			'triggers'   => array(
				array(
					'group'    => 0,
					'relation' => 'and',
					'type'     => 'author_role',
					'operator' => '!=',
					'value'    => 'administrator',
				),
				array(
					'group'    => 0,
					'relation' => 'and',
					'type'     => 'action',
					'operator' => '=',
					'value'    => 'updated',
				),
				array(
					'group'    => 1,
					'relation' => 'and',
					'type'     => 'author_role',
					'operator' => '=',
					'value'    => 'administrator',
				),
				array(
					'group'    => 1,
					'relation' => 'and',
					'type'     => 'connector',
					'operator' => '=',
					'value'    => 'widgets',
				),
				array(
					'group'    => 1,
					'relation' => 'and',
					'type'     => 'action',
					'operator' => '=',
					'value'    => 'sorted',
				),
			),
			'groups' => array(
				1 => array(
					'group'    => 0,
					'relation' => 'or',
				),
			),
			'alerts' => array(
				array(
					'type'    => 'email',
					'users'   => '1',
					'emails'  => '',
					'subject' => sprintf( __( '[Site Activity Alert] %s', 'stream-notifications' ), get_bloginfo( 'name' ) ),
					'message' => __( "The following just happened on your site:\r\n\r\n{summary} by {author.display_name}\r\n\r\nDate of action: {created}", 'stream-notifications' )
				),
			),
		);

		$post_id = wp_insert_post( $postarr );

		if ( is_a( $post_id, 'WP_Error' ) ) {
			return $post_id;
		}

		foreach ( $meta as $key => $val ) {
			update_post_meta( $post_id, $key, $val );
		}
	}

}

$GLOBALS['wp_stream_notifications'] = WP_Stream_Notifications::get_instance();
register_activation_hook( __FILE__, array( $GLOBALS['wp_stream_notifications'], 'on_activation' ) );
