<?php
namespace WP_Stream;

abstract class Notifier {

	public $plugin;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Plugin object.
	 * @return void
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 *	Notify receipients about the new record
	 *
	 * @param int   $record_id Record ID.
	 * @param array $recordarr Record details.
	 * @param array $options Notification options.
	 */
	abstract public function notify( $record_id, $recordarr, $options );

	/**
	 * Display settings form for configuration individual alerts
	 *
	 * @param Alert   $alert Alert currently being worked on.
	 * @param WP_Post $post Post details.
	 */
	public function display_settings_form( $alert, $post ) {
		return;
	}

	/**
	 * Process settings form for configuration individual alerts
	 *
	 * @param Alert   $alert Alert currently being worked on.
	 * @param WP_Post $post Post details.
	 */
	public function process_settings_form( $alert, $post ) {
		return;
	}

	/**
	 * Allow connectors to determine if their dependencies is satisfied or not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		return true;
	}
}
