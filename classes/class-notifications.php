<?php
namespace WP_Stream;

class Notifications {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Post meta prefix
	 *
	 * @var string
	 */
	public $meta_prefix = 'wp_stream';

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Register custom post type
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Add custom post type to menu
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register custom post type
	 *
	 * @action init
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Notifications', 'post type general name', 'stream' ),
			'singular_name'      => _x( 'Notification', 'post type singular name', 'stream' ),
			'menu_name'          => _x( 'Notifications', 'admin menu', 'stream' ),
			'name_admin_bar'     => _x( 'Notification', 'add new on admin bar', 'stream' ),
			'add_new'            => _x( 'Add New', 'book', 'stream' ),
			'add_new_item'       => __( 'Add New Notification', 'stream' ),
			'new_item'           => __( 'New Notification', 'stream' ),
			'edit_item'          => __( 'Edit Notification', 'stream' ),
			'view_item'          => __( 'View Notification', 'stream' ),
			'all_items'          => __( 'Notifications', 'stream' ),
			'search_items'       => __( 'Search Notifications', 'stream' ),
			'parent_item_colon'  => __( 'Parent Notifications:', 'stream' ),
			'not_found'          => __( 'No notifications found.', 'stream' ),
			'not_found_in_trash' => __( 'No notifications found in Trash.', 'stream' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'Notifications for Stream.', 'stream' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // @see modify_admin_menu
			'supports'            => false,
			'capabilities'        => array(
				'publish_posts'       => 'manage_options',
				'edit_others_posts'   => 'manage_options',
				'delete_posts'        => 'manage_options',
				'delete_others_posts' => 'manage_options',
				'read_private_posts'  => 'manage_options',
				'edit_post'           => 'manage_options',
				'delete_post'         => 'manage_options',
				'read_post'           => 'manage_options',
			),
		);

		register_post_type( 'wp_stream_actions', $args );
	}

	/**
	 * Add custom post type to menu
	 *
	 * @action admin_menu
	 *
	 * @return void
	 */
	function register_menu() {
		add_submenu_page(
			$this->plugin->admin->records_page_slug,
			'Notifications',
			'Notifications',
			'manage_options',
			'edit.php?post_type=wp_stream_actions'
		);
	}
}
