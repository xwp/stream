<?php

abstract class WP_Stream_Notification_Adapter {

	public $name  = null;
	public $title = null;

	public $params = null;

	function __construct( $title, $params ) {
		$this->name = strtolower( str_replace( 'WP_Stream_Notification_Action_', '', get_called_class() ) );
		$this->title = $title;
	}

	abstract function fields();

	function send( $log ) {

	}

}
