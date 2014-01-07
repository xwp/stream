<?php

abstract class WP_Stream_Notification_Action {

	public $name = null;
	public $title = null;
	public $params = null;

	function __construct( $name, $title, $params ) {
		$this->name = $name;
		$this->title = $title;
		$this->params = $params;
	}

	function send( $log ) {
		
	}

}