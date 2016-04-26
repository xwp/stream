<?php
namespace WP_Stream;

class Notifier_Null extends Notifier {

	/**
	 * Notifier name
	 *
	 * @var string
	 */
	public $name = 'None';

	/**
	 * Notifier slug
	 *
	 * @var string
	 */
	public $slug = 'null';

	public function notify( $record_id, $recordarr, $options ) {
		return;
	}
}
