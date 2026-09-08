<?php
/**
 * Alert Type abstract class.
 *
 * Used to register new Alert types.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Type
 *
 * @package WP_Stream
 */
abstract class Alert_Type {

	/**
	 * Unique identifier.
	 *
	 * @var string
	 */
	public string $slug = '';

	/**
	 * Whether this type can be selected when creating a new alert.
	 *
	 * Existing alerts of a non-creatable type still fire and remain editable.
	 *
	 * @var bool
	 */
	public bool $creatable = true;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Plugin object.
	 */
	public function __construct( public Plugin $plugin ) {
	}

	/**
	 *  Alert recipients about the new record
	 *
	 * @param int   $record_id Record ID.
	 * @param array $recordarr Record details.
	 * @param array $options Alert options.
	 */
	abstract public function alert( $record_id, $recordarr, $options );

	/**
	 * Display settings form for configuration of individual alerts
	 *
	 * @param Alert $alert Alert currently being worked on.
	 */
	public function display_fields( $alert ) {
		// Implementation optional, but recommended.
	}

	/**
	 * Process settings form for configuration of individual alerts
	 *
	 * @param Alert $alert Alert currently being worked on.
	 */
	public function save_fields( $alert ) {
		// Implementation optional, but recommended.
	}

	/**
	 * Allow connectors to determine if their dependencies are satisfied or not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		// Implementation optional, but recommended.
		return true;
	}
}
