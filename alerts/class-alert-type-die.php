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
class Alert_Type_Die extends Alert_Type { // @codingStandardsIgnoreLine

	/**
	 * Alert type name
	 *
	 * @var string
	 */
	public $name = 'Die Notifier';

	/**
	 * Alert type slug
	 *
	 * @var string
	 */
	public $slug = 'die';

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
		print_r( $recordarr ); // @codingStandardsIgnoreLine debug not loaded in production
		echo '</pre>';
		throw new Die_Exception( 'You have been notified!' );
	}
}
