<?php
namespace WP_Stream;

abstract class Notifier {

	/**
	 *	Notify receipients about the new record
	 *
	 * @param array $recordarr Record details
	 * @param array $options Notification options
	 *
	 */
	abstract public function notify( $recordarr, $options );

	/**
	 * Display settings form for configuration individual alerts
	 *
	 * @param Alert $alert Alert currently being worked on
	 * @param WP_Post $post Post details
	 *
	 */
	public function display_settings_form( $alert, $post ) {
		return;
	}

	/**
	 * Process settings form for configuration individual alerts
	 *
	 * @param WP_Post $post Post details
	 *
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
