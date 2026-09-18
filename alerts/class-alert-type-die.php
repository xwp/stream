<?php
/**
 * Used for debugging.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Type_Die
 *
 * @package WP_Stream
 */
class Alert_Type_Die extends Alert_Type {

	/**
	 * Alert type name
	 */
	public string $name = 'Die Notifier';

	/**
	 * Alert type slug
	 */
	public string $slug = 'die';

	/**
	 * Triggers a script exit when an alert is triggered. Debugging use only.
	 *
	 * @param int   $record_id Record that triggered notification.
	 * @param array $recordarr Record details.
	 * @param array $options Alert options.
	 * @return void
	 */
	public function alert( $record_id, $recordarr, $options ) {
		echo '<pre>';
		print_r( $recordarr ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- reason: die alert type is a debug notifier and dumps the record on purpose.
		echo '</pre>';
		die( 'You have been notified!' );
	}
}
