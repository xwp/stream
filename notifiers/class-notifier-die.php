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

	public function notify( $recordarr, $options ) {
		echo '<pre>';
		print_r( $recordarr ); // @codingStandardsIgnoreLine debug not loaded in production
		echo '</pre>';
		die( 'You have been notified!' );
	}
}
