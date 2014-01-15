<?php

abstract class WP_Stream_Notification_Adapter {

	public static function register( $title ) {
		$class = get_called_class();
		$name  = strtolower( str_replace( 'WP_Stream_Notification_Adapter_', '', $class ) );
		WP_Stream_Notifications::register_adapter( $class, $name, $title );
	}

	public static function fields() {
		return array();
	}

	function send( $log ) {

	}

}
