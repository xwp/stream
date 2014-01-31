<?php
/**
 * Plugin Name: Stream Notifications
 * Depends: Stream
 * Plugin URI: http://x-team.com
 * Description: TBD
 * Version: 0.1
 * Author: X-Team
 * Author URI: http://x-team.com/wordpress/
 * License: GPLv2+
 * Text Domain: stream-notifications
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


class WP_Stream_Notifications {

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
	 * List table object
	 * @var WP_Stream_Notifications_List_Table
	 */
	public static $list_table = null;

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
	 * @var  WP_Stream_Notification_Rule_Matcher
	 */
	public $matcher;

	/**
	 * Class constructor
	 */
	private function __construct() {
		define( 'WP_STREAM_NOTIFICATIONS_DIR', plugin_dir_path( __FILE__ ) );
		define( 'WP_STREAM_NOTIFICATIONS_URL', plugin_dir_url( __FILE__ ) );
		define( 'WP_STREAM_NOTIFICATIONS_INC_DIR', WP_STREAM_NOTIFICATIONS_DIR . 'includes/' );
		define( 'WP_STREAM_NOTIFICATIONS_CLASS_DIR', WP_STREAM_NOTIFICATIONS_DIR . 'classes/' );

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

		// Load all classes in /classes folder
		foreach ( glob( WP_STREAM_NOTIFICATIONS_DIR . '/classes/*.php' ) as $class ) {
			include $class;
		}

		// Include all adapters
		foreach ( glob( WP_STREAM_NOTIFICATIONS_DIR . '/classes/adapters/*.php' ) as $class ) {
			include $class;
		}

		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 11 );

		// Default list actions handlers
		add_action( 'wp_stream_notifications_handle_deactivate', array( $this, 'handle_rule_activation_status_change' ), 10, 3 );
		add_action( 'wp_stream_notifications_handle_activate', array( $this, 'handle_rule_activation_status_change' ), 10, 3 );
		add_action( 'wp_stream_notifications_handle_delete', array( $this, 'handle_rule_deletion' ), 10, 3 );

		// AJAX end point for form auto completion
		add_action( 'wp_ajax_stream_notification_endpoint', array( $this, 'form_ajax_ep' ) );

		// Load Matcher
		$this->matcher = new WP_Stream_Notification_Rule_Matcher();
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
			'manage_options',
			self::NOTIFICATIONS_PAGE_SLUG,
			array( $this, 'page' )
		);

		add_action( 'load-' . self::$screen_id, array( $this, 'page_form_save' ) );
	}

	/**
	 * Enqueue our scripts, in our own page only
	 *
	 * @action admin_enqueue_scripts
	 * @param  string $hook Current admin page slug
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( $hook != self::$screen_id ) {
			return;
		}

		$view = filter_input( INPUT_GET, 'view', FILTER_DEFAULT, array( 'options' => array( 'default' => 'list' ) ) );

		if ( $view == 'rule' ) {
			wp_enqueue_style( 'select2' );
			wp_enqueue_script( 'select2' );
			wp_enqueue_script( 'underscore' );
			wp_enqueue_script( 'stream-notifications-main', WP_STREAM_NOTIFICATIONS_URL . '/ui/js/main.js', array( 'underscore', 'select2' ) );
			wp_localize_script( 'stream-notifications-main', 'stream_notifications', $this->get_js_options() );
		}
	}

	public static function register_adapter( $adapter, $name, $title ) {
		self::$adapters[ $name ] = array(
			'title' => $title,
			'class' => $adapter,
		);
	}

	/**
	 * Format JS options for the form, to be used with wp_localize_script
	 *
	 * @return array  Options for our form JS handling
	 */
	public function get_js_options() {
		global $wp_roles;
		$args = array();

		$roles     = $wp_roles->roles;
		$roles_arr = array_combine( array_keys( $roles ), wp_list_pluck( $roles, 'name' ) );

		$default_operators = array(
			'='   => __( 'is', 'stream-notifications' ),
			'!='  => __( 'is not', 'stream-notifications' ),
			'in'  => __( 'in', 'stream-notifications' ),
			'!in' => __( 'not in', 'stream-notifications' ),
		);

		$args['types'] = array(
			'search' => array(
				'title'     => __( 'Summary', 'stream-notifications' ),
				'type'      => 'text',
				'operators' => array(
					'='         => __( 'is', 'stream-notifications' ),
					'!='        => __( 'is not', 'stream-notifications' ),
					'contains'  => __( 'contains', 'stream-notifications' ),
					'!contains' => __( 'does not contain', 'stream-notifications' ),
					'regex'     => __( 'regex', 'stream-notifications' ),
				),
			),
			// 'object_type' => array(
			// 	'title'     => __( 'Object Type', 'stream-notifications' ),
			// 	'type'      => 'select',
			// 	'multiple'  => true,
			// 	'operators' => array(
			// 		'='      => __( 'is', 'stream-notifications' ),
			// 		'!='     => __( 'is not', 'stream-notifications' ),
			// 		'in'     => __( 'in', 'stream-notifications' ),
			// 		'not_in' => __( 'not in', 'stream-notifications' ),
			// 		),
			// 	'options' => array( // TODO: Do we have a dynamic way to get this ?: Answer: NO, use 'Context'
			// 		'user'    => __( 'User', 'stream-notifications' ),
			// 		'post'    => __( 'Post', 'stream-notifications' ),
			// 		'comment' => __( 'Comment', 'stream-notifications' ),
			// 	),
			// ),

			'object_id' => array(
				'title'     => __( 'Object ID', 'stream-notifications' ),
				'type'      => 'text',
				'tags'      => true,
				'operators' => $default_operators,
			),

			'author_role' => array(
				'title'     => __( 'Author Role', 'stream-notifications' ),
				'type'      => 'select',
				'multiple'  => true,
				'operators' => $default_operators,
				'options' => $roles_arr,
			),

			'author' => array(
				'title'     => __( 'Author', 'stream-notifications' ),
				'type'      => 'text',
				'ajax'      => true,
				'operators' => $default_operators,
			),

			'ip' => array(
				'title'     => __( 'IP', 'stream-notifications' ),
				'type'      => 'text',
				'tags'      => true,
				'operators' => $default_operators,
			),

			'date' => array(
				'title'     => __( 'Date', 'stream-notifications' ),
				'type'      => 'date',
				'operators' => array(
					'='  => __( 'is on', 'stream-notifications' ),
					'!=' => __( 'is not on', 'stream-notifications' ),
					'<'  => __( 'is before', 'stream-notifications' ),
					'<=' => __( 'is on or before', 'stream-notifications' ),
					'>'  => __( 'is after', 'stream-notifications' ),
					'>=' => __( 'is on or after', 'stream-notifications' ),
				),
			),

			// TODO: find a way to introduce meta to the rules, problem: not translatable since it is
			// generated on run time with no prior definition
			// 'meta_query'            => array(),

			'connector' => array(
				'title'     => __( 'Connector', 'stream-notifications' ),
				'type'      => 'select',
				'operators' => $default_operators,
				'options' => WP_Stream_Connectors::$term_labels['stream_connector'],
			),
			'context' => array(
				'title'     => __( 'Context', 'stream-notifications' ),
				'type'      => 'text',
				'ajax'      => true,
				'operators' => $default_operators,
			),
			'action' => array(
				'title'     => __( 'Action', 'stream-notifications' ),
				'type'      => 'text',
				'ajax'      => true,
				'operators' => $default_operators,
			),
		);

		$args['adapters'] = array();

		foreach ( self::$adapters as $name => $options ) {
			$args['adapters'][$name] = array(
				'title'  => $options['title'],
				'fields' => $options['class']::fields(),
			);
		}

		// Localization
		$args['i18n'] = array(
			'empty_triggers' => __( 'A rule must contain at least one trigger to be saved.', 'stream-notifications' ),
		);

		return apply_filters( 'stream_notification_js_args', $args );
	}

	/**
	 * Admin page callback function, redirects to each respective method based
	 * on $_GET['view']
	 *
	 * @return void
	 */
	public function page() {
		$view = filter_input( INPUT_GET, 'view', FILTER_DEFAULT, array( 'options' => array( 'default' => 'list' ) ) );
		$id = filter_input( INPUT_GET, 'id' );

		switch ( $view ) {
			case 'rule':
				$this->page_form( $id );
				break;
			default:
				$this->page_list();
				break;
		}
	}

	/**
	 * Admin page callback for form actions
	 *
	 * @param null $id
	 *
	 * @return void
	 */
	public function page_form( $id = null ) {
		$rule = new WP_Stream_Notification_Rule( $id );
		include WP_STREAM_NOTIFICATIONS_DIR . '/views/rule-form.php';
	}

	public function page_form_save() {
		require_once WP_STREAM_NOTIFICATIONS_INC_DIR . 'list-table.php';
		self::$list_table = new WP_Stream_Notifications_List_Table( array( 'screen' => self::$screen_id ) );

		// TODO check author/user permission to update record

		$view     = filter_input( INPUT_GET, 'view', FILTER_DEFAULT, array( 'options' => array( 'default' => 'list' ) ) );
		$action   = filter_input( INPUT_GET, 'action', FILTER_DEFAULT );
		$id       = filter_input( INPUT_GET, 'id' );
		$bulk_ids = filter_input( INPUT_GET, 'wp_stream_notifications_checkbox', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		// There is a chance we go from the bottom bulk actions select box
		if ( ! $action || $action == '-1' ) {
			$action = filter_input( INPUT_GET, 'action2', FILTER_DEFAULT, array( 'options' => array( 'default' => 'render' ) ) );
		}

		if ( $_POST && 'rule' == $view ) {
			$data = $_POST;
			$rule = new WP_Stream_Notification_Rule( $id );

			if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_wpnonce' ), 'stream-notifications-form' ) ) {
				wp_die( __( 'Invalid form parameters.', 'stream-notifications' ) );
			}

			if ( empty( $data['triggers'] ) ) {
				wp_die( __( 'Rules cannot be saved without triggers!', 'stream-notifications' ) );
			}

			if ( ! isset( $data['visibility'] ) ) {
				$data['visibility'] = 'inactive'; // Checkbox woraround
			}

			$result = $rule->load_from_array( $data )->save();

			if ( $result ) {
				// Should not follow the WP naming convention, to avoid conflicts
				// if/when Stream migrates to using WP tables
				do_action( 'saved_stream_notification_rule', $rule );
			}

			if ( $result && $action != 'edit' ) {
				wp_redirect(
					add_query_arg(
						array(
							'action' => 'edit',
							'id'     => $rule->ID,
						)
					)
				);
			}
		}

		if ( 'list' == $view && 'render' != $action ) {
			if ( has_action( 'wp_stream_notifications_handle_' . $action ) ) {
				if ( $bulk_ids ) {
					foreach ( $bulk_ids as $id ) {
						do_action( 'wp_stream_notifications_handle_' . $action, $id, $action, true );
					}
				} else {
					do_action( 'wp_stream_notifications_handle_' . $action, $id, $action, false );
				}
			} else {
				wp_redirect(
					add_query_arg(
						array(
							'page' => self::NOTIFICATIONS_PAGE_SLUG,
						),
						admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
					)
				);
			}
		}

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
				admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
			),
			__( 'Add New' )
		); // xss okay

		self::$list_table->display();
		echo '</div>';
	}

	/**
	 * Callback for form AJAX operations
	 *
	 * @action wp_ajax_stream_notifications_endpoint
	 * @return void
	 */
	public function form_ajax_ep() {
		// BIG @TODO: Make the request context-aware,
		// ie: get other rules ( maybe in the same group only ? ), so an author
		// query would check if there is a author_role rule available to limit
		// the results according to it

		$type      = filter_input( INPUT_POST, 'type' );
		$is_single = filter_input( INPUT_POST, 'single' );
		$query     = filter_input( INPUT_POST, 'q' );

		if ( $is_single ) {
			switch ( $type ) {
				case 'author':
					$user_ids = explode( ',', $query );
					$user_query = new WP_User_Query(
						array(
							'include' => $user_ids,
							'fields'  => array( 'ID', 'user_email', 'display_name' ),
						)
					);
					if ( $user_query->results ) {
						$data = $this->format_json_for_select2(
							$user_query->results,
							'ID',
							'display_name'
						);
					} else {
						$data = array();
					}
					break;
				case 'action':
					$actions = WP_Stream_Connectors::$term_labels['stream_action'];
					$values  = explode( ',', $query );
					$actions = array_intersect_key( $actions, array_flip( $values ) );
					$data    = $this->format_json_for_select2( $actions );
					break;
			}
		} else {
			switch ( $type ) {
				case 'author':
					add_action( 'pre_user_query', array( $this, 'fix_user_query_display_name' ) );
					$users = get_users( array( 'search' => '*' . $query . '*' ) );
					remove_action( 'pre_user_query', array( $this, 'fix_user_query_display_name' ) );
					$data = $this->format_json_for_select2( $users, 'ID', 'display_name' );
					break;
				case 'action':
					$actions = WP_Stream_Connectors::$term_labels['stream_action'];
					$actions = preg_grep( sprintf( '/%s/i', $query ), $actions );
					$data    = $this->format_json_for_select2( $actions );
					break;
			}
		}

		// Add gravatar for authors
		if ( $type == 'author' && get_option( 'show_avatars' ) ) {
			foreach ( $data as $i => $item ) {
				if ( $avatar = get_avatar( $item['id'], 20 ) ) {
					$item['avatar'] = $avatar;
				}
				$data[$i] = $item;
			}
		}

		if ( $data ) {
			wp_send_json_success( $data );
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Take an (associative) array and format it for select2 AJAX result parser
	 * @param  array  $data (associative) Data array
	 * @param  string $key  Key of the ID column, null if associative array
	 * @param  string $val  Key of the Title column, null if associative array
	 * @return array        Formatted array, [ { id: %, title: % }, .. ]
	 */
	public function format_json_for_select2( $data, $key = null, $val = null ) {
		$return = array();
		if ( is_null( $key ) && is_null( $val ) ) { // for flat associative array
			$keys = array_keys( $data );
			$vals = array_values( $data );
		} else {
			$keys = wp_list_pluck( $data, $key );
			$vals = wp_list_pluck( $data, $val );
		}
		foreach ( $keys as $idx => $key ) {
			$return[] = array(
				'id'   => $key,
				'text' => $vals[$idx],
			);
		}
		return $return;
	}

	/*
	 * Handle the rule activation & deactivation action
	 */
	public function handle_rule_activation_status_change( $id, $action, $is_bulk = false ) {
		$data             = $_GET;
		$nonce            = filter_input( INPUT_GET, 'wp_stream_nonce' );
		$nonce_identifier = $is_bulk ? 'wp_stream_notifications_bulk_actions' : "activate-record_$id";
		$visibility       = $action == 'activate' ? 'active' : 'inactive';

		if ( ! wp_verify_nonce( $nonce, $nonce_identifier ) ) {
			return;
		}

		$activate_rule = apply_filters( 'wp_stream_notifications_before_rule_' . $action, true, $id );
		if ( $activate_rule == false ) {
			return;
		}

		$this->update_record(
			$id,
			array( 'visibility' => $visibility ),
			array( '%s' )
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
		$nonce            = filter_input( INPUT_GET, 'wp_stream_nonce' );
		$nonce_identifier = $is_bulk ? 'wp_stream_notifications_bulk_actions' : "delete-record_$id";
		$visibility       = filter_input( INPUT_GET, 'visibility', FILTER_DEFAULT );

		if ( ! wp_verify_nonce( $nonce, $nonce_identifier ) ) {
			return;
		}

		$activate_rule = apply_filters( 'wp_stream_notifications_before_rule_' . $action, true, $id );
		if ( $activate_rule == false ) {
			return;
		}

		$this->delete_record( $id );

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

	public function update_record( $id, $fields, $formats ) {
		global $wpdb;

		$wpdb->update(
			WP_Stream_DB::$table,
			$fields,
			array( 'ID' => $id, 'type' => 'notification_rule' ),
			$formats,
			array( '%d', '%s' )
		); // db call ok, cache ok

		// Refresh rule cache
		$this->matcher->refresh();
	}

	public function delete_record( $id ) {
		global $wpdb;

		$wpdb->delete(
			WP_Stream_DB::$table,
			array(
				'ID' => $id,
			)
		); // db call ok, cache ok

		// Refresh rule cache
		$this->matcher->refresh();
	}

	public function fix_user_query_display_name( $query ) {
		global $wpdb;
		$search = $query->query_vars['search'];
		if ( empty( $search ) ) {
			return;
		}
		$search = str_replace( '*', '', $search );
		$query->query_where .= $wpdb->prepare( " OR $wpdb->users.display_name LIKE %s", '%' . like_escape( $search ) . '%' );
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
		if ( ! stream_query( 'type=notification_rule&ignore_context=1' ) ) {
			$this->load();
			$this->add_sample_rule();
		}
	}

	/**
	 * Add a sample rule, used upon activation
	 *
	 */
	public function add_sample_rule() {
		$rule = new WP_Stream_Notification_Rule();
		$details = array(
			'author'     => 0,
			'summary'    => __( 'Sample Rule', 'stream-notifications' ),
			'visibility' => 'inactive',
			'type'       => 'notification_rule',
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
					'value'    => 'created,updated',
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
					'value'    => 'users',
				),
				array(
					'group'    => 1,
					'relation' => 'and',
					'type'     => 'action',
					'operator' => '=',
					'value'    => 'deleted',
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
					'subject' => __( '[Stream Notification] %%summary%%', 'stream-notifications' ),
					'message' => __( "The following just happened on your site:\r\n\r\n%%summary%%\r\n\r\nDate of action: %%created%%", 'stream-notifications' )
				),
			),
		);
		$rule->load_from_array( $details );
		$rule->save();
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

$GLOBALS['wp_stream_notifications'] = WP_Stream_Notifications::get_instance();
register_activation_hook( __FILE__, array( $GLOBALS['wp_stream_notifications'], 'on_activation' ) );
