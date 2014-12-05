<?php

class WP_Stream_Notifications_Adapter_Push extends WP_Stream_Notifications_Adapter {

	const PUSHOVER_OPTION_NAME = 'ckpn_pushover_notifications_settings';

	public static function register( $title = '' ) {
		parent::register( __( 'Push', 'stream' ) );
		add_filter( 'wp_stream_serialized_labels', array( __CLASS__, 'pushover_key_labels' ) );
	}

	public static function get_application_key() {
		$options = get_option( self::PUSHOVER_OPTION_NAME, array() );
		$result  = ( isset( $options['application_key'] ) && ! empty( $options['application_key'] ) ) ? $options['application_key'] : false;

		return $result;
	}

	public static function fields() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_path  = defined( 'CKPN_FILE' ) ? CKPN_FILE : null;
		$is_installed = ( $plugin_path && defined( 'WP_PLUGIN_DIR' ) && file_exists( trailingslashit( WP_PLUGIN_DIR )  . $plugin_path ) );

		if ( ! $is_installed ) {
			$fields = array(
				'error' => array(
					'title'   => __( 'Missing Required Plugin', 'stream' ),
					'type'    => 'error',
					'message' => sprintf(
						__( 'Please install and activate the %1$s plugin to enable push alerts.', 'stream' ),
						sprintf(
							'<a href="%1$s" target="_blank">%2$s</a>',
							esc_url( 'http://wordpress.org/plugins/pushover-notifications/' ),
							__( 'Pushover Notifications', 'stream' )
						)
					),
				),
			);
		} elseif ( ! is_plugin_active( $plugin_path ) ) {
			$fields = array(
				'error' => array(
					'title'   => __( 'Required Plugin Not Activated', 'stream' ),
					'type'    => 'error',
					'message' => sprintf(
						__( 'Please activate the %1$s plugin to enable push alerts.', 'stream' ),
						sprintf(
							'<a href="%1$s">%2$s</a>',
							self_admin_url( 'plugins.php' ),
							__( 'Pushover Notifications', 'stream' )
						)
					),
				),
			);
		} elseif ( false !== self::get_application_key() ) {
			$fields = array(
				'users' => array(
					'title'    => __( 'Send to Users', 'stream' ),
					'type'     => 'hidden',
					'multiple' => true,
					'ajax'     => true,
					'key'      => 'author',
					'args'     => array(
						'push' => true,
					),
					'hint'     => array(
						// hint 1
						__( 'Alert specific users via push.', 'stream' ),

						// hint 2
						sprintf(
							__( 'Only those users with a %s in their profile can be selected.', 'stream' ),
							sprintf(
								'<a href="%s" target="_blank">%s</a>',
								self_admin_url( 'profile.php#wp-stream-highlight:ckpn_user_key' ),
								__( 'Pushover User Key', 'stream' )
							)
						),
					),
				),
				'subject' => array(
					'title' => __( 'Subject', 'stream' ),
					'type'  => 'text',
					'hint'  => __( 'Data tags are allowed.', 'stream' ),
				),
				'message' => array(
					'title' => __( 'Message', 'stream' ),
					'type'  => 'textarea',
					'hint'  => __( 'Data tags are allowed.', 'stream' ),
				),
			);
		} else {
			$fields = array(
				'error' => array(
					'title'   => __( 'Application key is missing', 'stream' ),
					'type'    => 'error',
					'message' => sprintf(
						__( 'Please provide your Application key on %1$s.', 'stream' ),
						sprintf(
							'<a href="%1$s">%2$s</a>',
							self_admin_url( 'options-general.php?page=pushover-notifications' ),
							__( 'Pushover Notifications settings page', 'stream' )
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

		$subject = isset( $this->params['subject'] ) ? $this->replace( $this->params['subject'], $log ) : null;
		$message = isset( $this->params['message'] ) ? $this->replace( $this->params['message'], $log ) : null;

		$post_fields = array(
			'token'   => $application_key,
			'message' => $message,
			'title'   => $subject,
		);

		$connection = curl_init();

		if ( ! isset( $users_pushover_keys ) || ! $users_pushover_keys ) {
			return false;
		}

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

	/**
	 * @filter wp_stream_serialized_labels
	 */
	public static function pushover_key_labels( $labels ) {
		$labels[ self::PUSHOVER_OPTION_NAME ] = array(
			'application_key' => __( 'Application API Token/Key', 'stream' ),
			'api_key'         => __( 'Your User Key', 'stream' ),
			'new_user'        => __( 'New Users', 'stream' ),
			'new_post'        => __( 'New Posts are Published', 'stream' ),
			'new_post_roles'  => __( 'Roles to Notify', 'stream' ),
			'new_comment'     => __( 'New Comments', 'stream' ),
			'notify_authors'  => __( 'Notify the Post Author (for multi-author blogs)', 'stream' ),
			'password_reset'  => __( 'Notify users when password resets are requested for their accounts', 'stream' ),
			'core_update'     => __( 'WordPress Core Update is Available', 'stream' ),
			'plugin_updates'  => __( 'Plugin & Theme Updates are Available', 'stream' ),
			'multiple_keys'   => __( 'Use Multiple Application Keys', 'stream' ),
			'sslverify'       => __( 'Verify SSL from api.pushover.net', 'stream' ),
			'logging'         => __( 'Enable Logging', 'stream' ),
		);

		return $labels;
	}

}

WP_Stream_Notifications_Adapter_Push::register();
