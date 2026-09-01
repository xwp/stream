<?php
/**
 * Validates and loads core connectors, integrated connectors, and
 * connectors registered using the "wp_stream_connectors" hook.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connectors
 */
class Connectors {
	/**
	 * Built-in and integrated connector slugs.
	 *
	 * @var string[]
	 */
	const BUILTIN_CONNECTOR_SLUGS = array(
		// Core Connectors.
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

		// Integrated Connectors.
		'acf',
		'bbpress',
		'buddypress',
		'edd',
		'gravityforms',
		'jetpack',
		'mercator',
		'two-factor',
		'user-switching',
		'woocommerce',
		'wordpress-seo',
	);

	/**
	 * Holds instance of plugin object
	 *
	 * @var Plugin
	 */
	public $plugin;


	/**
	 * Fully-qualified builtin connector class names after files are included.
	 *
	 * @var string[]|null
	 */
	private ?array $available_connectors = null;

	/**
	 * Instantiated connectors, keyed by slug. Filled once by instantiate_connector_classes().
	 *
	 * @var array<string, Connector>|null
	 */
	private ?array $connector_instances = null;

	/**
	 * Registered connectors.
	 */
	public array $connectors = array();

	/**
	 * Contexts registered to Connectors
	 */
	public array $contexts = array();

	/**
	 * Action taxonomy terms
	 *
	 * Holds slug to localized label association
	 */
	public array $term_labels = array(
		'stream_connector' => array(),
		'stream_context'   => array(),
		'stream_action'    => array(),
	);

	/**
	 * Admin notice messages
	 */
	protected array $admin_notices = array();

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->load_connectors();
	}

	/**
	 * Include builtin connector files and return structurally valid class names.
	 *
	 * @return string[] Fully-qualified class names.
	 */
	private function get_available_connectors() {
		if ( is_array( $this->available_connectors ) ) {
			return $this->available_connectors;
		}

		$class_names = array();

		foreach ( self::BUILTIN_CONNECTOR_SLUGS as $slug ) {
			include_once $this->plugin->locations['dir'] . '/connectors/class-connector-' . $slug . '.php';

			$class_name = sprintf( '\WP_Stream\Connector_%s', str_replace( '-', '_', $slug ) );

			// We only add classes that extend Connector abstract class with required methods.
			if ( ! class_exists( $class_name ) || ! is_subclass_of( $class_name, Connector::class ) ) {
				continue;
			}

			$class_names[] = $class_name;
		}

		$this->available_connectors = $class_names;

		return $this->available_connectors;
	}

	/**
	 * Instantiate connector classes. Memoizes so abilities do not new twice.
	 *
	 * @param string[] $class_names Fully-qualified class names.
	 * @return array<string, Connector>
	 */
	private function instantiate_connector_classes( $class_names ) {
		if ( is_array( $this->connector_instances ) ) {
			return $this->connector_instances;
		}

		$classes = array();

		foreach ( $class_names as $class_name ) {
			$instance                   = new $class_name();
			$classes[ $instance->name ] = $instance;
		}

		$this->connector_instances = $classes;

		return $this->connector_instances;
	}

	/**
	 * Filter, gate, and register connector instances.
	 *
	 * @param array $classes Connector instances keyed by slug.
	 */
	public function register_connector_instances( $classes ) {
		$excluded_connectors = array();

		foreach ( (array) $classes as $connector ) {
			if ( ! $connector->is_dependency_satisfied() ) {
				continue;
			}

			// Check if the connector events are allowed to be registered in the WP Admin.
			if ( is_admin() && ! $connector->register_admin ) {
				continue;
			}

			// Check if the connector events are allowed to be registered in the WP Frontend.
			if ( ! is_admin() && ! $connector->register_frontend ) {
				continue;
			}

			if ( ! method_exists( $connector, 'register' ) ) {
				/* translators: %s: connector class name, intended to provide help to developers (e.g. "Connector_BuddyPress") */
				$this->plugin->admin->notice( sprintf( __( '%s class wasn\'t loaded because it doesn\'t implement the register method.', 'stream' ), $connector->name, 'Connector' ), true );
				continue;
			}

			/**
			 * Allows excluded connectors to be overridden and registered.
			 *
			 * @param bool   $is_excluded         True if excluded, otherwise false.
			 * @param string $connector           The current connector's slug.
			 * @param array  $excluded_connectors An array of all excluded connector slugs.
			 */
			$is_excluded_connector = apply_filters(
				'wp_stream_check_connector_is_excluded',
				in_array( $connector->name, $excluded_connectors, true ),
				$connector->name,
				$excluded_connectors
			);

			if ( $is_excluded_connector ) {
				continue;
			}

			// Add connector to the registry.
			$this->connectors[ $connector->name ] = $connector;

			// Register the connector.
			$connector->register();

			// Link context labels to their connector.
			$this->contexts[ $connector->name ] = $connector->get_context_labels();

			// Store connector label.
			$this->term_labels['stream_connector'][ $connector->name ] = $connector->get_label();

			// Add new terms to our label lookup array.
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
		 * @param array      $labels            All register connectors labels array
		 * @param Connectors $connector_classes The Connectors object
		 */
		do_action( 'wp_stream_after_connectors_registration', $labels, $this );
	}

	/**
	 * Load built-in connectors
	 */
	public function load_connectors() {
		$class_names = $this->get_available_connectors();
		$instances   = $this->instantiate_connector_classes( $class_names );

		/**
		 * Allows for adding additional connectors via classes that extend Connector.
		 *
		 * @param array $instances An array of Connector objects.
		 */
		$instances = apply_filters( 'wp_stream_connectors', $instances );

		$valid = array();
		foreach ( (array) $instances as $connector ) {
			// We only add classes that extend Connector abstract class with required methods.
			if ( is_subclass_of( $connector, Connector::class ) ) {
				$valid[ $connector->name ] = $connector;
			}
		}

		$this->connector_instances = $valid;

		$this->register_connector_instances( $this->connector_instances );
	}

	/**
	 * Return the slugs of all registered connectors.
	 *
	 * @param bool $include_inactive Whether to include inactive connectors.
	 * @return string[]
	 */
	public function get_slugs( $include_inactive = false ) {
		return array_keys( $include_inactive ? (array) $this->connector_instances : (array) $this->connectors );
	}

	/**
	 * Return a normalized list of all registered connectors and their
	 * context/action labels.
	 *
	 * @param bool $include_inactive Whether to include inactive connectors.
	 * @return array<int, array{slug:string,label:string,contexts:array<string,string>,actions:array<string,string>}>
	 */
	public function get_all( $include_inactive = false ) {
		$out        = array();
		$connectors = $include_inactive ? (array) $this->connector_instances : (array) $this->connectors;

		foreach ( $connectors as $slug => $connector ) {
			$out[] = array(
				'slug'     => (string) $slug,
				'label'    => method_exists( $connector, 'get_label' ) ? (string) $connector->get_label() : (string) $slug,
				'contexts' => method_exists( $connector, 'get_context_labels' ) ? (array) $connector->get_context_labels() : array(),
				'actions'  => method_exists( $connector, 'get_action_labels' ) ? (array) $connector->get_action_labels() : array(),
			);
		}

		return $out;
	}

	/**
	 * Unregisters the context hooks for all connectors.
	 */
	public function unload_connectors() {
		foreach ( $this->connectors as $connector ) {
			$connector->unregister();
		}
	}

	/**
	 * Reregisters the context hooks for all connectors.
	 */
	public function reload_connectors() {
		foreach ( $this->connectors as $connector ) {
			$connector->register();
		}
	}

	/**
	 * Unregisters the context hooks for a connectors.
	 *
	 * @param string $name  Name of the connector.
	 */
	public function unload_connector( $name ) {
		if ( ! empty( $this->connectors[ $name ] ) ) {
			$this->connectors[ $name ]->unregister();
		}
	}

	/**
	 * Reregisters the context hooks for a connector.
	 *
	 * @param string $name  Name of the connector.
	 */
	public function reload_connector( $name ) {
		if ( ! empty( $this->connectors[ $name ] ) ) {
			$this->connectors[ $name ]->register();
		}
	}
}
