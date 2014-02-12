<?php

class WP_Stream_Notification_Adapter_Push extends WP_Stream_Notification_Adapter {

	public static function register( $title = '' ) {
		parent::register( __( 'Push', 'stream-notifications' ) );
	}

	public static function fields() {
		return array(
			// to be added...
		);
	}

	public function send( $log ) {
		// to be added...
	}

}

WP_Stream_Notification_Adapter_Push::register();
