<?php

class WP_Stream_Notifications_Adapter_Email extends WP_Stream_Notifications_Adapter {

	public static function register( $title = '' ) {
		parent::register( __( 'Email', 'stream-notifications' ) );
	}

	public static function fields() {
		return array(
			'users' => array(
				'title'    => __( 'Send to Users', 'stream-notifications' ),
				'type'     => 'hidden',
				'multiple' => true,
				'ajax'     => true,
				'key'      => 'author',
				'hint'     => __( 'Alert specific users via email.', 'stream-notifications' ),
			),
			'emails' => array(
				'title' => __( 'Send to Emails', 'stream-notifications' ),
				'type'  => 'text',
				'tags'  => true,
				'hint'  => __( 'Alert any arbitrary email address not tied to a specific user.', 'stream-notifications' ),
			),
			'subject' => array(
				'title' => __( 'Subject', 'stream-notifications' ),
				'type'  => 'text',
				'hint'  => __( 'Data tags are allowed.', 'stream-notifications' ),
			),
			'message' => array(
				'title' => __( 'Message', 'stream-notifications' ),
				'type'  => 'textarea',
				'hint'  => __( 'HTML and data tags are allowed.', 'stream-notifications' ),
			),
		);
	}

	public function send( $log ) {
		$users = $this->params['users'];
		$user_emails = array();
		if ( $users ) {
			$user_query = new WP_User_Query(
				array(
					'include' => $users,
					'fields'  => array( 'user_email' ),
				)
			);
			$user_emails = wp_list_pluck( $user_query->results, 'user_email' );
		}
		$emails = explode( ',', $this->params['emails'] );
		if ( ! empty( $user_emails ) ) {
			$emails = array_merge( $emails, $user_emails );
		}
		$emails  = array_filter( $emails );
		$subject = $this->replace( $this->params['subject'], $log );
		$message = $this->replace( $this->params['message'], $log );
		wp_mail( $emails, $subject, $message );
	}

}

WP_Stream_Notifications_Adapter_Email::register();
