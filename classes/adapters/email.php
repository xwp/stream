<?php

class WP_Stream_Notification_Adapter_Email extends WP_Stream_Notification_Adapter {

	public static function register( $title = '' ) {
		parent::register( __( 'Email', 'stream_notification' ) );
	}

	public static function fields() {
		return array(
			'to' => array(
				'title'    => __( 'To', 'stream_notification' ),
				'type'     => 'hidden',
				'multiple' => true,
				'ajax'     => true,
				'key'      => 'author',
				),
			'subject' => array(
				'title' => __( 'Subject', 'stream_notification' ),
				'type'  => 'text',
				'hint'  => __( 'ex: "%%summary%%" or "[%%created%% - %%author%%] %%summary%%", consult FAQ for documentaion.', 'stream_notification' ),
				),
			'message' => array(
				'title' => __( 'Message', 'stream_notification' ),
				'type'  => 'textarea',
				),
		);
	}

}

WP_Stream_Notification_Adapter_Email::register();