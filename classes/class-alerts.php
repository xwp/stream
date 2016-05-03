<?php
namespace WP_Stream;

class Alerts {
	/**
	 * Hold Plugin class
	 *
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
	public $alert_types = array();

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Register custom post type.
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Add custom post type to menu.
		add_action( 'wp_stream_admin_menu', array( $this, 'register_menu' ) );

		// Add metaboxes to post screens.
		add_action( 'load-post.php', array( $this, 'register_meta_boxes' ) );
		add_action( 'load-post-new.php', array( $this, 'register_meta_boxes' ) );

		// Add scripts to post screens.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );

		add_filter( 'wp_stream_record_inserted', array( $this, 'check_records' ), 10, 2 );

		$this->load_alert_types();
		$this->load_alert_triggers();
	}

	/**
	 * Load alert_type classes
	 *
	 * @return void
	 */
	function load_alert_types() {
		$alert_types = array(
			'none',
			'menu-alert',
			'highlight',
			'email',
		);

		$classes = array();
		foreach ( $alert_types as $alert_type ) {
			include_once $this->plugin->locations['dir'] . '/alerts/class-alert-type-' . $alert_type .'.php';
			$class_name = sprintf( '\WP_Stream\Alert_Type_%s', str_replace( '-', '_', $alert_type ) );
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
		 * Allows for adding additional alert_types via classes that extend Notifier.
		 *
		 * @param array $classes An array of Notifier objects. In the format alert_type_slug => Notifier_Class()
		 */
		$this->alert_types = apply_filters( 'wp_stream_alert_types', $classes );

		// Ensure that all alert_types extend Notifier.
		foreach ( $this->alert_types as $key => $alert_type ) {
			if ( ! $this->is_valid_alert_type( $alert_type ) ) {
				unset( $this->alert_types[ $key ] );
				trigger_error(
					sprintf(
						esc_html__( 'Registered alert_type %s does not extend WP_Stream\Alert_Type.', 'stream' ),
						esc_html( get_class( $alert_type ) )
					)
				);
			}
		}
	}

	/**
	 * Load alert_type classes
	 *
	 * @return void
	 */
	function load_alert_triggers() {
		$alert_triggers = array(
			'author',
			'action',
			'context',
		);

		$classes = array();
		foreach ( $alert_triggers as $alert_trigger ) {
			include_once $this->plugin->locations['dir'] . '/alerts/class-alert-trigger-' . $alert_trigger .'.php';
			$class_name = sprintf( '\WP_Stream\Alert_Trigger_%s', str_replace( '-', '_', $alert_trigger ) );
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
		 * Allows for adding additional alert_triggers via classes that extend Notifier.
		 *
		 * @param array $classes An array of Notifier objects. In the format alert_trigger_slug => Notifier_Class()
		 */
		$this->alert_triggers = apply_filters( 'wp_stream_alert_triggers', $classes );

		// Ensure that all alert_triggers extend Notifier.
		foreach ( $this->alert_triggers as $key => $alert_trigger ) {
			if ( ! $this->is_valid_alert_trigger( $alert_trigger ) ) {
				unset( $this->alert_triggers[ $key ] );
				trigger_error(
					sprintf(
						esc_html__( 'Registered alert_trigger %s does not extend WP_Stream\Alert_Trigger.', 'stream' ),
						esc_html( get_class( $alert_trigger ) )
					)
				);
			}
		}
	}

	/**
	 * Checks whether a Alert Type class is valid
	 *
	 * @param Alert_Type $alert_type The class to check.
	 * @return bool
	 */
	public function is_valid_alert_type( $alert_type ) {
		if ( ! is_a( $alert_type, 'WP_Stream\Alert_Type' ) ) {
			return false;
		}

		if ( ! method_exists( $alert_type, 'is_dependency_satisfied' ) || ! $alert_type->is_dependency_satisfied() ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether a Alert Trigger class is valid
	 *
	 * @param Alert_Trigger $alert_trigger The class to check.
	 * @return bool
	 */
	public function is_valid_alert_trigger( $alert_trigger ) {
		if ( ! is_a( $alert_trigger, 'WP_Stream\Alert_Trigger' ) ) {
			return false;
		}

		if ( ! method_exists( $alert_trigger, 'is_dependency_satisfied' ) || ! $alert_trigger->is_dependency_satisfied() ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks record being processed against active alerts.
	 *
	 * @param int   $record_id The record being processed.
	 * @param array $recordarr Record data.
	 * @return array
	 */
	function check_records( $record_id, $recordarr ) {
		$args = array(
			'post_type' => 'wp_stream_alerts',
		);

		$alerts = new \WP_Query( $args );
		foreach ( $alerts->posts as $alert ) {
			$alert = $this->get_alert( $alert->ID );

			$status = $alert->check_record( $record_id, $recordarr );
			if ( $status ) {
				$alert->send_alert( $record_id, $recordarr );
			}
		}

		return $recordarr;

	}

	/**
	 * Register scripts for page load
	 *
	 * @param string $page Current file name.
	 * @return void
	 */
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
	 * Return alert object of the given ID
	 *
	 * @param int $post_id Post ID for the alert.
	 * @return Alert
	 */
	public function get_alert( $post_id ) {
			$post = get_post( $post_id );
			$meta = get_post_custom( $post_id );

			$obj = (object) array(
				'ID'             => $post->ID,
				'date'           => $post->post_date,
				'author'         => $post->post_author,
				'filter_action'  => isset( $meta['filter_action'] ) ? $meta['filter_action'][0] : null,
				'filter_author'  => isset( $meta['filter_author'] ) ? $meta['filter_author'][0] : null,
				'filter_context' => isset( $meta['filter_context'] ) ? $meta['filter_context'][0] : null,
				'alert_type'     => isset( $meta['alert_type'] ) ? $meta['alert_type'][0] : null,
				'alert_meta'     => isset( $meta['alert_meta'] ) ? maybe_unserialize( $meta['alert_meta'][0] ) : array(),
			);

			if ( array_key_exists( $obj->alert_type, $this->alert_types ) ) {
				$obj->alert_type_obj = $this->alert_types[ $obj->alert_type ];
			} else {
				$obj->alert_type_obj = new Alert_Type_None( $this->plugin );
			}

			return new Alert( $obj );
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
			__( 'Alert Trigger', 'stream' ),
			array( $this, 'display_triggers_box' ),
			'wp_stream_alerts',
			'normal',
			'high'
		);

		add_meta_box(
			'wp_stream_alerts_alert_type',
			__( 'Alert Type', 'stream' ),
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
	 * Display Alert Type Meta Box
	 *
	 * @param WP_Post $post Post object for current alert.
	 * @return void
	 */
	function display_notification_box( $post ) {
		$alert = $this->get_alert( $post->ID );
		$form  = new Form_Generator;

		$field_html = $form->render_field( 'select', array(
			'name'        => 'wp_stream_alert_type',
			'value'       => $alert->alert_type,
			'options'     => $this->get_notification_values(),
			'placeholder' => __( 'No Alert', 'stream' ),
			'title'       => 'Alert Type:',
		) );

		echo '<p>' . esc_html__( 'Alert me by:', 'stream' ) . '</p>';
		echo $field_html; // xss ok

		$alert->display_settings_form( $post );
	}

	/**
	 * Display Trigger Meta Box
	 *
	 * @param WP_Post $post Post object for current alert.
	 * @return void
	 */
	function display_triggers_box( $post ) {
		$alert = $this->get_alert( $post->ID );

		$form  = new Form_Generator;
		do_action( 'wp_stream_alert_trigger_form_display', $form, $alert );

		//@todo use human readable text
		echo '<p>' . esc_html__( 'Create an alert whenever:', 'stream' ) . '</p>';
		echo $form->render_all(); // xss ok

		wp_nonce_field( 'save_post', 'wp_stream_alerts_nonce' );

	}

	/**
	 * Display Preview Meta Box
	 *
	 * @param WP_Post $post Post object for current alert.
	 * @return void
	 */
	function display_preview_box( $post ) {
		$alert = $this->get_alert( $post->ID );
		$table = new Preview_List_Table( $this->plugin );

		$query = array(
			'records_per_page' => apply_filters( 'stream_records_per_page', 20 ),
		);

		$query = apply_filters( 'stream_alerts_preview_query', $query, $alert );
		$items = $this->plugin->db->query( $query );

		$table->set_records( $items );
		$table->display();

	}

	/**
	 * Return all context values
	 *
	 * @return array
	 */
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

	/**
	 * Return all notification values
	 *
	 * @return array
	 */
	function get_notification_values() {
		$result = array();
		$names  = wp_list_pluck( $this->alert_types, 'name', 'slug' );
		foreach ( $names as $slug => $name ) {
			$result[ $slug ] = $name;
		}
		return $result;
	}

	/**
	 * Process alert settings
	 *
	 * @param int     $post_id Post ID for the current alert.
	 * @param WP_Post $post Post object for the current alert.
	 */
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

		$alert = $this->get_alert( $post_id );

		// @todo sanitize input based on possible values
		$triggers = array(
			'alert_type'     => ! empty( $_POST['wp_stream_alert_type'] ) ? $_POST['wp_stream_alert_type'] : null,
		);

		foreach ( $triggers as $field => $value ) {
			$alert->$field = $value;
		}

		$alert->process_settings_form( $post );

		do_action( 'wp_stream_alert_trigger_form_save', $alert );

		remove_action( 'save_post', array( $this, 'save_meta_boxes' ), 10 );
		$alert->save();
		add_action( 'save_post', array( $this, 'save_meta_boxes' ), 10, 2 );

	}
}
