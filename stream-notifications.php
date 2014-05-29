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
	const STREAM_MIN_VERSION = '2.0.0';

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
	 * List table object
	 * @var WP_Stream_Notifications_List_Table
	 */
	public static $list_table = null;

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
*@var  WP_Stream_Notifications_Rule_Matcher
	 */
	public $matcher;

	/**
	 * Form Class Object
	 * @var WP_Stream_Notifications_Form
	 */
	public $form;

	/**
	 * Capability for the Notifications to be viewed
	 *
	 * @const string
	 */
	const VIEW_CAP = 'view_stream_notifications';


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
	 * Class constructor
	 */
	private function __construct() {
		define( 'WP_STREAM_NOTIFICATIONS_PLUGIN', plugin_basename( __FILE__ ) );
		define( 'WP_STREAM_NOTIFICATIONS_DIR', plugin_dir_path( __FILE__ ) ); // Has trailing slash
		define( 'WP_STREAM_NOTIFICATIONS_URL', plugin_dir_url( __FILE__ ) ); // Has trailing slash
		define( 'WP_STREAM_NOTIFICATIONS_INC_DIR', WP_STREAM_NOTIFICATIONS_DIR . 'includes/' ); // Has trailing slash

		add_action( 'plugins_loaded', array( $this, 'load' ) );

		// Register post type
		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'post-type.php';
		WP_Stream_Notifications_Post_Type::get_instance();

		// Refresh rules cache on updating/deleting posts
		add_action( 'save_post', array( $this, 'callback_save_post_refresh_cache' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'callback_save_post_refresh_cache' ), 10, 1 );
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

		if ( is_multisite() ) {
			add_filter( 'wp_stream_notifications_disallow_site_access', array( 'WP_Stream_Network', 'disable_admin_access' ) );
		}

		// Load settings, enabling extensions to hook in
		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'settings.php';
		add_action( 'init', array( 'WP_Stream_Notifications_Settings', 'load' ), 9 );

		// Load network class
		if ( is_multisite() ) {
			require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'network.php';
			$this->network = new WP_Stream_Notifications_Network;

			require_once WP_ADMIN . '/includes/plugins.php';
			if ( is_plugin_active_for_network( WP_STREAM_NOTIFICATIONS_PLUGIN ) ) {
				add_action( 'network_admin_menu', array( $this, 'register_menu' ), 11 );
			}
		}

		if ( ! apply_filters( 'wp_stream_notifications_disallow_site_access', false ) ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );

		// Default list actions handlers
		// @todo use save_post instead
//		add_action( 'wp_stream_notifications_handle_deactivate', array( $this, 'handle_rule_activation_status_change' ), 10, 3 );
//		add_action( 'wp_stream_notifications_handle_activate', array( $this, 'handle_rule_activation_status_change' ), 10, 3 );
//		add_action( 'wp_stream_notifications_handle_delete', array( $this, 'handle_rule_deletion' ), 10, 3 );

		// Load Matcher
		include_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'rule-match.php';
		$this->matcher = new WP_Stream_Notifications_Rule_Matcher();

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
			__( '--- Add rule', 'stream-notifications' ),
			__( '--- Add rule', 'stream-notifications' ),
			self::VIEW_CAP,
			'post-new.php?post_type=stream-notification'
		);

		// @todo fix this
		add_action( 'load-' . self::$screen_id, array( $this, 'load_list_table' ) );
		add_action( 'load-' . self::$screen_id, array( $this->form, 'load' ) );
	}

	public static function register_adapter( $adapter, $name, $title ) {
		self::$adapters[ $name ] = array(
			'title' => $title,
			'class' => $adapter,
		);
	}

	/**
	 * @action admin_enqueue_scripts
	 */
	public static function register_scripts( $hook ) {
		if ( sprintf( 'stream_page_%s', self::NOTIFICATIONS_PAGE_SLUG ) === $hook ) {
			wp_enqueue_script( 'stream-notifications-actions', WP_STREAM_NOTIFICATIONS_URL . 'ui/js/actions.js', array( 'jquery' ) );
			wp_localize_script( 'stream-notifications-actions', 'stream_notifications_actions', array(
				'messages' => array(
					'deletePermanently' => __( 'Are you sure you want to delete this rule? This cannot be undone.', 'stream-notifications' ),
				),
			) );
		}
	}

	/**
	 * Apply list actions, and load our list-table object
	 *
	 * @action load-{$screen_id}
	 *
	 * @return void
	 */
	public function load_list_table() {
		$view     = wp_stream_filter_input( INPUT_GET, 'view', FILTER_DEFAULT, array( 'options' => array( 'default' => 'list' ) ) );
		$action   = wp_stream_filter_input( INPUT_GET, 'action', FILTER_DEFAULT );
		$id       = wp_stream_filter_input( INPUT_GET, 'id' );
		$bulk_ids = wp_stream_filter_input( INPUT_GET, 'wp_stream_notifications_checkbox', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$search   = wp_stream_filter_input( INPUT_GET, 'search' );

		// There is a chance we go from the bottom bulk actions select box
		if ( ! $action || '-1' === $action ) {
			$action = wp_stream_filter_input( INPUT_GET, 'action2', FILTER_DEFAULT, array( 'options' => array( 'default' => 'render' ) ) );
		}

		if ( 'list' === $view && 'render' !== $action ) {

			if ( has_action( 'wp_stream_notifications_handle_' . $action ) ) {
				if ( $bulk_ids ) {
					foreach ( $bulk_ids as $id ) {
						do_action( 'wp_stream_notifications_handle_' . $action, $id, $action, true );
					}
				} else {
					do_action( 'wp_stream_notifications_handle_' . $action, $id, $action, false );
				}
			} elseif ( null === $search ) {
				wp_redirect(
					add_query_arg(
						array(
							'page' => self::NOTIFICATIONS_PAGE_SLUG,
						),
						is_network_admin() ? network_admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE ) : admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
					)
				);
			}
		}

		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'list-table.php';
		self::$list_table = new WP_Stream_Notifications_List_Table( array( 'screen' => self::$screen_id ) );
	}

	/**
	 * Admin page callback for list view
	 *
	 * @return void
	 */
	public function page_list() {
		self::$list_table->prepare_items();

		echo '<div class="wrap">';
		echo sprintf(
			'<h2>%s <a href="%s" class="add-new-h2">%s</a></h2>',
			__( 'Stream Notifications', 'stream-notifications' ),
			add_query_arg(
				array(
					'page' => self::NOTIFICATIONS_PAGE_SLUG,
					'view' => 'rule',
				),
				is_network_admin() ? network_admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE ) : admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
			),
			__( 'Add New' )
		); // xss okay

		self::$list_table->display();
		echo '</div>';
	}

	/*
	 * Handle the rule activation & deactivation action
	 */
	public function handle_rule_activation_status_change( $id, $action, $is_bulk = false ) {
		$data             = $_GET;
		$nonce            = wp_stream_filter_input( INPUT_GET, 'wp_stream_nonce' );
		$nonce_identifier = $is_bulk ? 'wp_stream_notifications_bulk_actions' : "activate-record_$id";
		$visibility       = ( 'activate' === $action ) ? 'active' : 'inactive';

		if ( ! wp_verify_nonce( $nonce, $nonce_identifier ) ) {
			return;
		}

		$activate_rule = apply_filters( 'wp_stream_notifications_before_rule_' . $action, true, $id );
		if ( false === $activate_rule ) {
			return;
		}

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		wp_redirect(
			add_query_arg(
				array(
					'wp_stream_nonce' => false,
					'action'          => false,
					'id'              => false,
					'visibility'      => $visibility,
				)
			)
		);
	}

	/*
	 * Handle the rule deletion
	 */
	public function handle_rule_deletion( $id, $action, $is_bulk = false ) {
		$data             = $_GET;
		$nonce            = wp_stream_filter_input( INPUT_GET, 'wp_stream_nonce' );
		$nonce_identifier = $is_bulk ? 'wp_stream_notifications_bulk_actions' : "delete-record_$id";
		$visibility       = wp_stream_filter_input( INPUT_GET, 'visibility', FILTER_DEFAULT );

		if ( ! wp_verify_nonce( $nonce, $nonce_identifier ) ) {
			return;
		}

		$activate_rule = apply_filters( 'wp_stream_notifications_before_rule_' . $action, true, $id );
		if ( false === $activate_rule ) {
			return;
		}

		wp_delete_post( $id );

		wp_redirect(
			add_query_arg(
				array(
					'wp_stream_nonce' => false,
					'action'          => false,
					'id'              => false,
					'visibility'      => $visibility,
				)
			)
		);
	}

	public function callback_save_post_refresh_cache( $post_id, $post = null ) {
		if ( ! isset( $post ) ) {
			$post = get_post( $post_id );
		}

		if ( 'stream-notification' === $post->post_type ) {
			$this->matcher->refresh();
		}
	}

	public function callback_delete_post_refresh_cache( $post_id ) {
		$post = get_post( $post_id );
		if ( 'stream-notification' !== $post->post_type ) {
			return;
		}

		add_action( 'deleted_post', function( $deleted_id ) use ( $post_id ) {
			if ( $deleted_id === $post_id ) {
				$this->matcher->refresh();
			}
		} );
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
		if ( function_exists( 'wp_stream_query' ) && ! wp_stream_query( 'type=notification_rule&ignore_context=1' ) ) {
			$this->load();
			$this->add_sample_rule();
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
