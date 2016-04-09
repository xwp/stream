<?php
namespace WP_Stream;

class Notifier_Null extends Notifier {

  /**
	 * Notifier name
	 *
	 * @var string
	 */
  public $name = 'Null Notifier';

  /**
	 * Notifier slug
	 *
	 * @var string
	 */
  public $slug = 'null';

  public function notify( $recordarr, $options ) {
    return;
  }

}
