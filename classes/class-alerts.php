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
	* Notifiers
	*
	* @var array
	*/
	public $notifiers = array();

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
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );

		add_filter( 'wp_stream_record_inserted', array( $this, 'check_records' ), 10, 2 );

		$this->load_notifiers();
	}

	function load_notifiers() {
		$notifiers = array(
			'null',
			'menu-alert',
		);

		$classes = array();
		foreach ( $notifiers as $notifier ) {
			include_once $this->plugin->locations['dir'] . '/notifiers/class-notifier-' . $notifier .'.php';
			$class_name = sprintf( '\WP_Stream\Notifier_%s', str_replace( '-', '_', $notifier ) );
			if ( ! class_exists( $class_name ) ) {
				continue;
			}
			$class = new $class_name( $this->plugin );
			if ( ! property_exists( $class, 'slug' ) ) {
				continue;
			}
			$classes[ $class->slug ] = $class;
		}

		/**
		 * Allows for adding additional notifiers via classes that extend Notifier.
		 *
		 * @param array $classes An array of Notifier objects. In the format notifier_slug => Notifier_Class()
		 */
		$this->notifiers = apply_filters( 'wp_stream_notifiers', $classes );

		// Ensure that all notifiers extend Notifier
		foreach ( $this->notifiers as $key => $notifier ) {
			if ( ! $this->is_valid_notifier( $notifier ) ) {
				unset( $this->notifiers[ $key ] );
				trigger_error(
					sprintf(
						esc_html__( 'Registered notifier %s does not extend WP_Stream\Notifier.', 'stream' ),
						esc_html( get_class( $notifier ) )
					)
				);
			}
		}
	}

	/**
	 * Checks whether a notifier class is valid
	 *
	 * @param Notifier $notifier The class to check.
	 * @return bool
	 */
	public function is_valid_notifier( $notifier ) {

		if ( ! is_a( $notifier, 'WP_Stream\Notifier' ) ) {
			return false;
		}

		if ( ! method_exists( $notifier, 'is_dependency_satisfied' ) || ! $notifier->is_dependency_satisfied() ) {
			return false;
		}

		return true;
	}

	function check_records( $record_id, $recordarr ) {

		$args = array(
			'post_type' => 'wp_stream_alerts',
		);

		$alerts = new \WP_Query( $args );
		foreach ( $alerts->posts as $alert ) {
			$alert = Alert::get_alert( $alert->ID );

			$status = $alert->check_record( $recordarr );
			if ( $status ) {
				$alert->send_alert( $record_id, $recordarr );
			}
		}

		return $recordarr;

	}

	function register_scripts( $page ) {
		if ( 'post.php' === $page || 'post-new.php' === $page ) {
			wp_enqueue_script( 'wp-strean-alerts', $this->plugin->locations['url'] . 'ui/js/alerts.js', array( 'wp-stream-select2' ) );
			wp_enqueue_style( 'wp-stream-select2' );
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
		add_action( 'save_post', array( $this, 'save_meta_boxes' ), 10, 2 );
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

		add_meta_box(
			'wp_stream_alerts_notification',
			__( 'Notifications', 'stream' ),
			array( $this, 'display_notification_box' ),
			'wp_stream_alerts',
			'normal',
			'default'
		);

		add_meta_box(
			'wp_stream_alerts_preview',
			__( 'Records matching these triggers', 'stream' ),
			array( $this, 'display_preview_box' ),
			'wp_stream_alerts',
			'advanced',
			'low'
		);
	}

	/**
	* Display Notifications Meta Box
	*
	* @return void
	*/
	function display_notification_box( $post ) {
		$alert = Alert::get_alert( $post->ID );
		$form = new Form_Generator;

		echo $form->render_field( 'select2', array( //xss ok
			'name'        => 'wp_stream_alert_type',
			'value'       => $alert->alert_type,
			'options'     => $this->get_notification_values(),
			'placeholder' => __( 'No Alert', 'stream' ),
		) );

		echo '<div class="wp-stream-alert-settings-form">';
		$alert->display_settings_form( $post );
		echo '</div>';
	}

	/**
	* Display Trigger Meta Box
	*
	* @return void
	*/
	function display_triggers_box( $post ) {

		$alert = Alert::get_alert( $post->ID );
		$form = new Form_Generator;

		$args = array(
			'name'        => 'wp_stream_filter_author',
			'value'       => $alert->filter_author,
			'options'     => array(),
			'placeholder' => __( 'Any Author', 'stream' ),
		);
		$author_html = $form->render_field( 'select2', $args );

		$args = array(
			'name'        => 'wp_stream_filter_action',
			'value'       => $alert->filter_action,
			'options'     => $this->get_action_values(),
			'placeholder' => __( 'Any Action', 'stream' ),
		);
		$action_html = $form->render_field( 'select2', $args );

		$args = array(
			'name'        => 'wp_stream_filter_context',
			'value'       => $alert->filter_context,
			'options'     => $this->get_context_values(),
			'placeholder' => __( 'Any Context', 'stream' ),
		);
		$context_html = $form->render_field( 'select2', $args );

		echo sprintf( // xss ok
			__( 'Create alert whenever %1$s %2$s inside of %3$s', 'stream' ),
			$author_html,
			$action_html,
			$context_html
		);

		wp_nonce_field( 'save_post', 'wp_stream_alerts_nonce' );

	}

	/**
	* Display Preview Meta Box
	*
	* @return void
	*/
	function display_preview_box( $post ) {

		$alert = Alert::get_alert( $post->ID );

		$table = new Preview_List_Table( $this->plugin );

		$items = $this->plugin->db->query( array(
			'action' => $alert->filter_action,
			'context' => $alert->filter_context,
			'records_per_page' => apply_filters( 'stream_records_per_page', 20 ),
		) );

		$table->set_records( $items );
		$table->display();

	}

	function get_action_values() {
		$action_values = array();
		foreach ( $this->get_terms_labels( 'action' ) as $action_id => $action_data ) {
			$action_values[] = array( 'id' => $action_id, 'text' => $action_data );
		}
		return $action_values;
	}

	function get_context_values() {
		$context_values = array();
		foreach ( $this->get_terms_labels( 'context' ) as $context_id => $context_data ) {
			if ( is_array( $context_data ) ) {
				$child_values = array();
				if ( isset( $context_data['children'] ) ) {
					$child_values = array();
					foreach ( $context_data['children'] as $child_id => $child_value ) {
						$child_values[] = array( 'id' => $child_id, 'text' => $child_value, 'parent' => $context_id );
					}
				}
				if ( isset( $context_data['label'] ) ) {
					$context_values[] = array( 'id' => $context_id, 'text' => $context_data['label'], 'children' => $child_values );
				}
			} else {
				$context_values[] = array( 'id' => $context_id, 'text' => $context_data );
			}
		}
		return $context_values;
	}

	function get_notification_values() {
		$result = array();
		$names  = wp_list_pluck( $this->notifiers, 'name', 'slug' );
		foreach ( $names as $slug => $name ) {
			$result[] = array(
				'id'   => $slug,
				'text' => $name,
			);
		}
		return $result;
	}

	function save_meta_boxes( $post_id, $post ) {

		if ( 'wp_stream_alerts' !== $post->post_type ) {
			return false;
		}

		if ( isset( $post->post_status ) && 'auto-draft' === $post->post_status ) {
			return;
		}

		check_admin_referer( 'save_post', 'wp_stream_alerts_nonce' );

		$post_type = get_post_type_object( $post->post_type );
		if ( ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
			return $post_id;
		}

		$alert = Alert::get_alert( $post_id );

		// @todo sanitize input based on possible values
		$triggers = array(
			'alert_type'     => ! empty( $_POST['wp_stream_alert_type'] ) ? $_POST['wp_stream_alert_type'] : null,
			'filter_action'  => ! empty( $_POST['wp_stream_filter_action'] ) ? $_POST['wp_stream_filter_action'] : null,
			'filter_author'  => ! empty( $_POST['wp_stream_filter_author'] ) ? $_POST['wp_stream_filter_author'] : null,
			'filter_context' => ! empty( $_POST['wp_stream_filter_context'] ) ? $_POST['wp_stream_filter_context'] : null,
		);

		foreach ( $triggers as $field => $value ) {
			$alert->$field = $value;
		}

		$alert->process_settings_form( $post );

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
