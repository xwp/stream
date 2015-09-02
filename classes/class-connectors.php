<?php
namespace WP_Stream;

class Connectors {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Connectors registered
	 *
	 * @var array
	 */
	public $connectors = array();

	/**
	 * Contexts registered to Connectors
	 *
	 * @var array
	 */
	public $contexts = array();

	/**
	 * Action taxonomy terms
	 * Holds slug to localized label association
	 *
	 * @var array
	 */
	public $term_labels = array(
		'stream_connector' => array(),
		'stream_context'   => array(),
		'stream_action'    => array(),
	);

	/**
	 * Admin notice messages
	 *
	 * @var array
	 */
	protected $admin_notices = array();

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->load_connectors();
	}

	/**
	 * Load built-in connectors
	 */
	public function load_connectors() {
		$connectors = array(
			// Core
			'blogs',
			'comments',
			'editor',
			'installer',
			'media',
			'menus',
			'posts',
			'settings',
			'taxonomies',
			'users',
			'widgets',

			// Extras
			'acf',
			'bbpress',
			'buddypress',
			'edd',
			'gravityforms',
			'jetpack',
			'user-switching',
			'woocommerce',
			'wordpress-seo',
		);

		$classes = array();
		foreach ( $connectors as $connector ) {
			include_once $this->plugin->locations['dir'] . '/connectors/class-connector-' . $connector .'.php';
			$class_name = sprintf( '\WP_Stream\Connector_%s', str_replace( '-', '_', $connector ) );
			if ( ! class_exists( $class_name ) ) {
				continue;
			}
			$class = new $class_name( $this->plugin->log );
			if ( ! method_exists( $class, 'is_dependency_satisfied' ) ) {
				continue;
			}
			if ( $class->is_dependency_satisfied() ) {
				$classes[] = $class;
			}
		}

		if ( empty( $classes ) ) {
			return;
		}

		/**
		 * Allows for adding additional connectors via classes that extend Connector.
		 *
		 * @param array $classes An array of Connector objects.
		 */
		$this->connectors = apply_filters( 'wp_stream_connectors', $classes );

		foreach ( $this->connectors as $connector ) {
			if ( ! method_exists( $connector, 'get_label' ) ) {
				continue;
			}
			$this->term_labels['stream_connector'][ $connector->name ] = $connector->get_label();
		}

		// Get excluded connectors
		$excluded_connectors = array();

		foreach ( $this->connectors as $connector ) {
			if ( ! method_exists( $connector, 'get_label' ) ) {
				$this->plugin->admin->notice( sprintf( __( "%s class wasn't loaded because it doesn't implement the get_label method.", 'stream' ), $connector->name, 'Connector' ), true );
				continue;
			}
			if ( ! method_exists( $connector, 'register' ) ) {
				$this->plugin->admin->notice( sprintf( __( "%s class wasn't loaded because it doesn't implement the register method.", 'stream' ), $connector->name, 'Connector' ), true );
				continue;
			}
			if ( ! method_exists( $connector, 'get_context_labels' ) ) {
				$this->plugin->admin->notice( sprintf( __( "%s class wasn't loaded because it doesn't implement the get_context_labels method.", 'stream' ), $connector->name, 'Connector' ), true );
				continue;
			}
			if ( ! method_exists( $connector, 'get_action_labels' ) ) {
				$this->plugin->admin->notice( sprintf( __( "%s class wasn't loaded because it doesn't implement the get_action_labels method.", 'stream' ), $connector->name, 'Connector' ), true );
				continue;
			}

			// Check if the connectors extends the Connector class, if not skip it
			if ( ! is_subclass_of( $connector, '\WP_Stream\Connector' ) ) {
				$this->plugin->admin->notice( sprintf( __( "%s class wasn't loaded because it doesn't extends the %s class.", 'stream' ), $connector->name, 'Connector' ), true );
				continue;
			}

			// Store connector label
			if ( ! in_array( $connector->name, $this->term_labels['stream_connector'] ) ) {
				$this->term_labels['stream_connector'][ $connector->name ] = $connector->get_label();
			}

			$connector_name = $connector->name;
			$is_excluded    = in_array( $connector_name, $excluded_connectors );

			/**
			 * Allows excluded connectors to be overridden and registered.
			 *
			 * @param bool   $is_excluded         True if excluded, otherwise false.
			 * @param string $connector           The current connector's slug.
			 * @param array  $excluded_connectors An array of all excluded connector slugs.
			 */
			$is_excluded_connector = apply_filters( 'wp_stream_check_connector_is_excluded', $is_excluded, $connector_name, $excluded_connectors );

			if ( $is_excluded_connector ) {
				continue;
			}

			$connector->register();

			// Link context labels to their connector
			$this->contexts[ $connector->name ] = $connector->get_context_labels();

			// Add new terms to our label lookup array
			$this->term_labels['stream_action']  = array_merge(
				$this->term_labels['stream_action'],
				$connector->get_action_labels()
			);
			$this->term_labels['stream_context'] = array_merge(
				$this->term_labels['stream_context'],
				$connector->get_context_labels()
			);
		}

		$labels = $this->term_labels['stream_connector'];

		/**
		 * Fires after all connectors have been registered.
		 *
		 * @param array      $labels     All register connectors labels array
		 * @param Connectors $connectors The Connectors object
		 */
		do_action( 'wp_stream_after_connectors_registration', $labels, $this );
	}
}
