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
	 * Allow connectors to determine if their dependencies is satisfied or not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		return true;
	}
}
