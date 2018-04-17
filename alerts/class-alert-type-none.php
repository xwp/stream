<?php
/**
 * "Do nothing" Alert type.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Type_None
 *
 * @package WP_Stream
 */
class Alert_Type_None extends Alert_Type {
	/**
	 * Notifier name
	 *
	 * @var string
	 */
	public $name = 'Do Nothing';

	/**
	 * Notifier slug
	 *
	 * @var string
	 */
	public $slug = 'none';

	/**
	 * Does not notify user.
	 *
	 * @param int   $record_id Record that triggered alert.
	 * @param array $recordarr Record details.
	 * @param array $options Alert options.
	 * @return void
	 */
	public function alert( $record_id, $recordarr, $options ) {
		// Do nothing.
	}
}
