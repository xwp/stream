<?php
/**
 * Alerts feature class.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alerts
 *
 * @package WP_Stream
 */
class Alerts {

	/**
	 * Alerts post type slug
	 */
	const POST_TYPE = 'wp_stream_alerts';

	/**
	 * Enabled alert post status slug.
	 */
	const STATUS_ENABLED = 'wp_stream_enabled';

	/**
	 * Disabled alert post status slug.
	 */
	const STATUS_DISABLED = 'wp_stream_disabled';

	/**
	 * Triggered Alerts meta key for Records
	 */
	const ALERTS_TRIGGERED_META_KEY = 'wp_stream_alerts_triggered';

	/**
	 * Capability required to access alerts.
	 */
	const CAPABILITY = WP_STREAM_SETTINGS_CAPABILITY;

	/**
	 * Post meta prefix
	 *
	 * @var string
	 */
	public string $meta_prefix = 'wp_stream';

	/**
	 * Alert Types
	 *
	 * @var array
	 */
	public $alert_types = array();

	/**
	 * Alert Triggers
	 *
	 * @var array
	 */
	public $alert_triggers = array();

	/**
	 * Record-matching collaborator.
	 *
	 * Public so in-plugin callers can match alerts without an Alerts façade.
	 *
	 * @var Alerts_Trigger_Engine
	 */
	public Alerts_Trigger_Engine $trigger_engine;

	/**
	 * Admin forms / AJAX / menu collaborator.
	 *
	 * Public so in-plugin callers can render alert UI without an Alerts façade.
	 *
	 * @var Alerts_Admin_UI
	 */
	public Alerts_Admin_UI $admin_ui;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( public $plugin ) {
		$this->trigger_engine = new Alerts_Trigger_Engine( $this->plugin );
		$this->admin_ui       = new Alerts_Admin_UI( $this->plugin );

		// Register custom post type.
		add_action( 'init', array( $this, 'register_post_type' ) );

		$this->load_alert_types();
		$this->load_alert_triggers();
	}

	/**
	 * Load alert_type classes
	 *
	 * @return void
	 */
	public function load_alert_types() {
		$alert_types = array(
			'none',
			'highlight',
			'email',
			'ifttt',
			'slack',
		);

		$classes = array();
		foreach ( $alert_types as $alert_type ) {
			$file_location = $this->plugin->locations['dir'] . '/alerts/class-alert-type-' . $alert_type . '.php';
			if ( file_exists( $file_location ) ) {
				include_once $file_location;
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
			}
		}
	}

	/**
	 * Load alert_type classes
	 *
	 * @return void
	 */
	public function load_alert_triggers() {
		$alert_triggers = array(
			'author',
			'context',
			'action',
		);

		$classes = array();
		foreach ( $alert_triggers as $alert_trigger ) {
			$file_location = $this->plugin->locations['dir'] . '/alerts/class-alert-trigger-' . $alert_trigger . '.php';
			if ( file_exists( $file_location ) ) {
				include_once $file_location;
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
			}
		}
	}

	/**
	 * Checks whether a Alert Type class is valid
	 *
	 * @param Alert_Type $alert_type The class to check.
	 *
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
	 *
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
	 * Register custom post type
	 *
	 * @action init
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Alerts', 'post type general name', 'stream' ),
			'singular_name'      => _x( 'Alert', 'post type singular name', 'stream' ),
			'menu_name'          => _x( 'Alerts', 'admin menu', 'stream' ),
			'name_admin_bar'     => _x( 'Alert', 'add new on admin bar', 'stream' ),
			'add_new'            => _x( 'Add New', 'book', 'stream' ),
			'add_new_item'       => __( 'Add New Alert', 'stream' ),
			'new_item'           => __( 'New Alert', 'stream' ),
			'edit_item'          => __( 'Edit Alert', 'stream' ),
			'view_item'          => __( 'View Alert', 'stream' ),
			'all_items'          => __( 'Alerts', 'stream' ),
			'search_items'       => __( 'Search Alerts', 'stream' ),
			'parent_item_colon'  => __( 'Parent Alerts:', 'stream' ),
			'not_found'          => __( 'No alerts found.', 'stream' ),
			'not_found_in_trash' => __( 'No alerts found in Trash.', 'stream' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'Alerts for Stream.', 'stream' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // @see modify_admin_menu
			'supports'            => false,
			'capabilities'        => array(
				'publish_posts'       => self::CAPABILITY,
				'edit_others_posts'   => self::CAPABILITY,
				'delete_posts'        => self::CAPABILITY,
				'delete_others_posts' => self::CAPABILITY,
				'read_private_posts'  => self::CAPABILITY,
				'edit_post'           => self::CAPABILITY,
				'delete_post'         => self::CAPABILITY,
				'read_post'           => self::CAPABILITY,
			),
		);

		register_post_type( self::POST_TYPE, $args );

		$args = array(
			'label'                     => _x( 'Enabled', 'alert', 'stream' ),
			'public'                    => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: a number of items (e.g. "42") */
			'label_count'               => _n_noop( 'Enabled <span class="count">(%s)</span>', 'Enabled <span class="count">(%s)</span>', 'stream' ),
		);

		register_post_status( 'wp_stream_enabled', $args );

		$args = array(
			'label'                     => _x( 'Disabled', 'alert', 'stream' ),
			'public'                    => false,
			'internal'                  => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: a number of items (e.g. "42") */
			'label_count'               => _n_noop( 'Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>', 'stream' ),
		);

		register_post_status( 'wp_stream_disabled', $args );
	}

	/**
	 * Return alert object of the given ID
	 *
	 * @param string|int $post_id Post ID for the alert.
	 *
	 * @return Alert
	 */
	public function get_alert( $post_id = '' ) {
		if ( ! $post_id ) {
			return new Alert( null, $this->plugin );
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof \WP_Post ) ) {
			return new Alert( null, $this->plugin );
		}

		$alert_type = get_post_meta( $post_id, 'alert_type', true );
		$alert_meta = get_post_meta( $post_id, 'alert_meta', true );

		$obj = (object) array(
			'ID'         => $post->ID,
			'status'     => $post->post_status,
			'date'       => $post->post_date,
			'author'     => $post->post_author,
			'alert_type' => $alert_type,
			'alert_meta' => $alert_meta,
		);

		return new Alert( $obj, $this->plugin );
	}

	/**
	 * Return a list of alerts, optionally filtered by post status.
	 *
	 * @param array $statuses Optional list of alert post statuses to include.
	 *                        Defaults to enabled + disabled.
	 *
	 * @return Alert[]
	 */
	public function get_alerts( array $statuses = array() ) {
		if ( empty( $statuses ) ) {
			$statuses = array( self::STATUS_ENABLED, self::STATUS_DISABLED );
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => $statuses,
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			)
		);

		$alerts = array();
		foreach ( $posts as $post ) {
			$alerts[] = $this->get_alert( $post->ID );
		}

		return $alerts;
	}
}
