<?php
/**
 * Abstract base class for Stream abilities (WordPress Abilities API integration).
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Ability
 *
 * Subclasses define a single Stream operation that is exposed via the
 * WordPress Abilities API. Each subclass declares a namespaced name,
 * input/output JSON Schemas, a permission callback, and an execute
 * callback. Subclasses are instantiated and registered by Abilities.
 */
abstract class Ability {

	/**
	 * Holds instance of plugin object.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Namespaced ability name (e.g. "stream/get-records").
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Short human-readable label.
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Description of what the ability does.
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * JSON Schema for ability input. Return an empty array for no input.
	 *
	 * @return array
	 */
	abstract public function get_input_schema();

	/**
	 * JSON Schema for ability output.
	 *
	 * @return array
	 */
	abstract public function get_output_schema();

	/**
	 * Execute the ability.
	 *
	 * @param array $input Validated input matching get_input_schema().
	 * @return mixed|\WP_Error Result conforming to get_output_schema(), or WP_Error.
	 */
	abstract public function execute( $input );

	/**
	 * Permission check. Defaults to manage_options; override per ability.
	 *
	 * @param array $input Input that will be passed to execute().
	 * @return bool|\WP_Error
	 */
	public function permission_callback( $input = array() ) {
		unset( $input );
		return current_user_can( 'manage_options' );
	}

	/**
	 * Annotation flags for the ability (readonly, destructive, idempotent).
	 *
	 * @return array
	 */
	public function get_annotations() {
		return array();
	}

	/**
	 * Meta passed to wp_register_ability(). Sets category and REST exposure.
	 *
	 * @return array
	 */
	public function get_meta() {
		$meta = array(
			'category'     => Abilities::CATEGORY_SLUG,
			'show_in_rest' => true,
		);

		$annotations = $this->get_annotations();
		if ( ! empty( $annotations ) ) {
			$meta['annotations'] = $annotations;
		}

		return $meta;
	}

	/**
	 * Register the ability with the Abilities API.
	 *
	 * @return void
	 */
	final public function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$args = array(
			'label'               => $this->get_label(),
			'description'         => $this->get_description(),
			'output_schema'       => $this->get_output_schema(),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'permission_callback' ),
			'meta'                => $this->get_meta(),
		);

		$input_schema = $this->get_input_schema();
		if ( ! empty( $input_schema ) ) {
			$args['input_schema'] = $input_schema;
		}

		wp_register_ability( $this->get_name(), $args );
	}
}
