<?php
namespace WP_Stream;

class Notifier_Die extends Notifier {

	/**
	 * Notifier name
	 *
	 * @var string
	 */
	public $name = 'Die Notifier';

	/**
	 * Notifier slug
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
	public function notify( $record_id, $recordarr, $options ) {
		echo '<pre>';
		print_r( $recordarr ); // @codingStandardsIgnoreLine debug not loaded in production
		echo '</pre>';
		die( 'You have been notified!' );
	}
}
