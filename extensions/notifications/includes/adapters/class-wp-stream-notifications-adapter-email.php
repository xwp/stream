<?php

class WP_Stream_Notifications_Adapter_Email extends WP_Stream_Notifications_Adapter {

	public static function register( $title = '' ) {
		parent::register( __( 'Email', 'stream' ) );
	}

	public static function fields() {
		return array(
			'users' => array(
				'title'    => __( 'Send to Users', 'stream' ),
				'type'     => 'hidden',
				'multiple' => true,
				'ajax'     => true,
				'key'      => 'author',
				'hint'     => __( 'Alert specific users via email.', 'stream' ),
			),
			'emails' => array(
				'title' => __( 'Send to Emails', 'stream' ),
				'type'  => 'text',
				'tags'  => true,
				'hint'  => __( 'Alert any arbitrary email address not tied to a specific user.', 'stream' ),
			),
			'subject' => array(
				'title' => __( 'Subject', 'stream' ),
				'type'  => 'text',
				'hint'  => __( 'Data tags are allowed.', 'stream' ),
			),
			'message' => array(
				'title' => __( 'Message', 'stream' ),
				'type'  => 'textarea',
				'hint'  => __( 'HTML and data tags are allowed.', 'stream' ),
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
		$emails = $this->replace( $this->params['emails'], $log );
		$emails = explode( ',', $emails );
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
