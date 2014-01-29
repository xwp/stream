<?php

class WP_Stream_Notification_Adapter_Email extends WP_Stream_Notification_Adapter {

	public static function register( $title = '' ) {
		parent::register( __( 'Email', 'stream_notification' ) );
	}

	public static function fields() {
		return array(
			'users' => array(
				'title'    => __( 'To users', 'stream_notification' ),
				'type'     => 'hidden',
				'multiple' => true,
				'ajax'     => true,
				'key'      => 'author',
				),
			'emails' => array(
				'title' => __( 'To emails', 'stream_notification' ),
				'type'  => 'text',
				'tags'  => true,
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

	public function send( $log ) {
		$to = $this->params['to'];
		$subject = $this->replace( $this->params['subject'], $log );
		$message = $this->replace( $this->params['message'], $log );
		wp_mail( $to, $subject, $message );
	}

}

WP_Stream_Notification_Adapter_Email::register();
