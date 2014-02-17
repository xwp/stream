<?php

class WP_Stream_Notification_Adapter_Push extends WP_Stream_Notification_Adapter {

	public static function register( $title = '' ) {
		parent::register( __( 'Push', 'stream-notifications' ) );
	}

	public static function get_application_key() {
		$options = get_option( 'ckpn_pushover_notifications_settings', array() );
		$result  = ( isset( $options['application_key'] ) && ! empty( $options['application_key'] ) ) ? $options['application_key'] : false;

		return $result;
	}

	public static function fields() {
		if ( false !== self::get_application_key() ) {
			$fields = array(
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
		} elseif ( ! is_plugin_active( 'pushover-notifications/pushover-notifications.php' ) ) {
			$fields = array(
				'error' => array(
					'title'   => __( 'Pushover Notifications plugin is required', 'stream-notifications' ),
					'type'    => 'error',
					'message' => sprintf(
						__( 'In order to use push with Stream Notifications, please activate the %1$s plugin.', 'stream-notifications' ),
						sprintf(
							'<a href="%1$s" target="_blank">%2$s</a>',
							esc_url( 'http://wordpress.org/plugins/pushover-notifications/' ),
							__( 'Pushover Notifications', 'stream-notifications' )
						)
					),
				),
			);
		} else {
			$fields = array(
				'error' => array(
					'title'   => __( 'Application key is missing', 'stream-notifications' ),
					'type'    => 'error',
					'message' => sprintf(
						__( 'Please provide your Application key on %1$s.', 'stream-notifications' ),
						sprintf(
							'<a href="%1$s">%2$s</a>',
							admin_url( 'options-general.php?page=pushover-notifications' ),
							__( 'Pushover Notifications settings page', 'stream-notifications' )
						)
					),
				),
			);
		}

		return $fields;
	}

	public function send( $log ) {
		$application_key = self::get_application_key();

		if ( false === $application_key ) {
			return false;
		}

		if ( ! empty( $this->params['users'] ) ) {
			$users_ids = explode( ',', $this->params['users'] );
			$users = get_users( array(
				'include'  => $users_ids,
				'fields'   => 'ID',
				'meta_key' => 'ckpn_user_key',
			) );
			$users_pushover_keys = array_map(
				function( $user_id ) {
					return get_user_meta( $user_id, 'ckpn_user_key', true );
				},
				$users
			);
		}

		$subject = $this->replace( $this->params['subject'], $log );
		$message = $this->replace( $this->params['message'], $log );

		$post_fields = array(
			'token'   => $application_key,
			'message' => $message,
			'title'   => $subject,
		);

		$connection = curl_init();

		foreach ( $users_pushover_keys as $key ) {
			$post_fields['user'] = $key;
			curl_setopt_array(
				$connection,
				array(
					CURLOPT_URL            => 'https://api.pushover.net/1/messages.json',
					CURLOPT_POST           => true,
					CURLOPT_RETURNTRANSFER => 1,
					CURLOPT_POSTFIELDS     => http_build_query( $post_fields ),
				)
			);
			$response = curl_exec( $connection );
		}
		curl_close( $connection );
	}

}

WP_Stream_Notification_Adapter_Push::register();
