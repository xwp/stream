<?php
namespace WP_Stream;

class Alert_Type_None extends Alert_Type {
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

	/**
	 * Does not notify user.
	 *
	 * @param int   $record_id Record that triggered alert.
	 * @param array $recordarr Record details.
	 * @param array $options Alert options.
	 * @return void
	 */
	public function alert( $record_id, $recordarr, $options ) {
		return;
	}
}
