<?php

class WP_Stream_Notification_Adapter_Push extends WP_Stream_Notification_Adapter {

	public static function register( $title = '' ) {
		parent::register( __( 'Push', 'stream-notifications' ) );
	}

	public static function fields() {
		return array(
			'users' => array(
				'title'    => __( 'Send to Users', 'stream-notifications' ),
				'type'     => 'hidden',
				'multiple' => true,
				'ajax'     => true,
				'key'      => 'author',
				'args'     => array(
					'push' => true,
				),
				'hint'     => __( 'Alert specific users via push.', 'stream-notifications' ),
			),
			'subject' => array(
				'title' => __( 'Subject', 'stream-notifications' ),
				'type'  => 'text',
				'hint'  => __( 'Data tags are allowed.', 'stream-notifications' ),
			),
			'message' => array(
				'title' => __( 'Message', 'stream-notifications' ),
				'type'  => 'textarea',
				'hint'  => __( 'Data tags are allowed.', 'stream-notifications' ),
			),
		);
	}

	public function send( $log ) {
		// to be added...
	}

}

WP_Stream_Notification_Adapter_Push::register();
