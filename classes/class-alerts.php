<?php
namespace WP_Stream;

class Alerts {
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
		add_action( 'wp_stream_admin_menu', array( $this, 'register_menu' ) );

		// Add metaboxes to post screens
		add_action( 'load-post.php', array( $this, 'register_meta_boxes' ) );
		add_action( 'load-post-new.php', array( $this, 'register_meta_boxes' ) );

		// Add scripts to post screens
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts') );
	}

	function register_scripts( $page ) {
		if ( 'post.php' == $page|| 'post-new.php' == $page ) {
			wp_enqueue_script( 'wp-strean-alerts', $this->plugin->locations['url'] . 'ui/js/alerts.js', array( 'wp-stream-select2' ) );
		}
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
			'name'							 => _x( 'Alerts', 'post type general name', 'stream' ),
			'singular_name'			 => _x( 'Alert', 'post type singular name', 'stream' ),
			'menu_name'					 => _x( 'Alerts', 'admin menu', 'stream' ),
			'name_admin_bar'		 => _x( 'Alert', 'add new on admin bar', 'stream' ),
			'add_new'						 => _x( 'Add New', 'book', 'stream' ),
			'add_new_item'			 => __( 'Add New Alert', 'stream' ),
			'new_item'					 => __( 'New Alert', 'stream' ),
			'edit_item'					 => __( 'Edit Alert', 'stream' ),
			'view_item'					 => __( 'View Alert', 'stream' ),
			'all_items'					 => __( 'Alerts', 'stream' ),
			'search_items'			 => __( 'Search Alerts', 'stream' ),
			'parent_item_colon'	 => __( 'Parent Alerts:', 'stream' ),
			'not_found'					 => __( 'No alerts found.', 'stream' ),
			'not_found_in_trash' => __( 'No alerts found in Trash.', 'stream' ),
		);

		$args = array(
			'labels'							=> $labels,
			'description'         => __( 'Alerts for Stream.', 'stream' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // @see modify_admin_menu
			'supports'            => false,
			'capabilities'				=> array(
				'publish_posts'			  => 'manage_options',
				'edit_others_posts'	  => 'manage_options',
				'delete_posts'				=> 'manage_options',
				'delete_others_posts' => 'manage_options',
				'read_private_posts'	=> 'manage_options',
				'edit_post'					  => 'manage_options',
				'delete_post'				  => 'manage_options',
				'read_post'					  => 'manage_options',
			),
		);

		register_post_type( 'wp_stream_alerts', $args );
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
			__( 'Alerts', 'stream' ),
			__( 'Alerts', 'stream' ),
			'manage_options',
			'edit.php?post_type=wp_stream_alerts'
		);
	}

	/**
	* Register metaboxes with post screens
	*
	* @return void
	*/
	function register_meta_boxes() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_boxes'), 10, 2 );
	}

	/**
	* Add metaboxes to post screens
	*
	* @return void
	*/
	function add_meta_boxes() {
		add_meta_box(
			'wp_stream_alerts_triggers',
			__( 'Triggers', 'stream' ),
			array( $this, 'display_triggers_box' ),
			'wp_stream_alerts',
			'normal',
			'high'
		);
	}

	/**
	* Display Trigger Meta Box
	*
	* @return void
	*/
	function display_triggers_box( $post ) {

		$alert = Alert::get_alert( $post->ID );

		$input = sprintf(
			'<input type="text" name="%s" id="%s" value="%s" size="30" />',
			'wp_stream_filter_author',
			'wp_stream_filter_author',
			$alert->filter_author
		);
		echo '<p><label for="">' . __( 'Trigger Authors', 'stream' ) . '</label><br/>' . $input . '</p>';

		// Action dropdown menu
		$action_values = array();

		foreach ( $this->get_terms_labels( 'action' ) as $action_id => $action_data ) {
			$action_values[] = array( 'id' => $action_id, 'text' => $action_data );
		}

		$action_input = sprintf(
			'<select class="chosen-select" name="%1$s"><option>Test</option></select>',
			'wp_stream_filter_action'
		);
		echo $action_input;

		$input = sprintf(
			'<input type="text" name="%s" id="%s" value="%s" size="30" />',
			'wp_stream_filter_context',
			'wp_stream_filter_contexts',
			$alert->filter_context
		);
		echo '<p><label for="">' . __( 'Trigger Contexts', 'stream' ) . '</label><br/>' . $input . '</p>';

	}

	function save_meta_boxes( $post_id, $post ) {

		// @todo add nonce check

		$post_type = get_post_type_object( $post->post_type );
		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) ) {
		  return $post_id;
		};

		$alert = Alert::get_alert( $post_id );

		// @todo sanitize input
		$triggers = array(
			'filter_action'  => ! empty( $_POST['wp_stream_filter_action'] ) ? $_POST['wp_stream_filter_action'] : null,
			'filter_author'  => ! empty( $_POST['wp_stream_filter_author'] ) ? $_POST['wp_stream_filter_author'] : null,
			'filter_context' => ! empty( $_POST['wp_stream_filter_context'] ) ? $_POST['wp_stream_filter_context'] : null,
		);

		foreach ( $triggers as $field => $value ) {
			$alert->$field = $value;
		}

		remove_action( 'save_post', array( $this, 'save_meta_boxes' ), 10 );
		$alert->save();
		add_action( 'save_post', array( $this, 'save_meta_boxes' ), 10, 2 );

	}

	/**
	 * Function will return all terms labels of given column
	 *
	 * @todo refactor Settings::get_terms_labels into general utility
	 * @param string $column string Name of the column
	 * @return array
	 */
	public function get_terms_labels( $column ) {
		$return_labels = array();

		if ( isset( $this->plugin->connectors->term_labels[ 'stream_' . $column ] ) ) {
			if ( 'context' === $column && isset( $this->plugin->connectors->term_labels['stream_connector'] ) ) {
				$connectors = $this->plugin->connectors->term_labels['stream_connector'];
				$contexts   = $this->plugin->connectors->term_labels['stream_context'];

				foreach ( $connectors as $connector => $connector_label ) {
					$return_labels[ $connector ]['label'] = $connector_label;
					foreach ( $contexts as $context => $context_label ) {
						if ( isset( $this->plugin->connectors->contexts[ $connector ] ) && array_key_exists( $context, $this->plugin->connectors->contexts[ $connector ] ) ) {
							$return_labels[ $connector ]['children'][ $context ] = $context_label;
						}
					}
				}
			} else {
				$return_labels = $this->plugin->connectors->term_labels[ 'stream_' . $column ];
			}

			ksort( $return_labels );
		}

		return $return_labels;
	}

}
