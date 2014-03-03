<?php
/**
 * Settings class for Stream Notifications
 *
 * @author X-Team <x-team.com>
 * @author Shady Sharaf <shady@x-team.com>, Jaroslav Polakovič <dero@x-team.com>
 */
class WP_Stream_Notification_Settings {

	public static $fields = array();

	/**
	 * Public constructor
	 */
	public static function load() {
		// User and role caps
		add_filter( 'user_has_cap', array( __CLASS__, '_filter_user_caps' ), 10, 4 );
		add_filter( 'role_has_cap', array( __CLASS__, '_filter_role_caps' ), 10, 3 );

		// Add Notifications settings tab to Stream settings
		add_filter( 'wp_stream_options_fields', array( __CLASS__, '_register_settings' ) );

		// Export function
		add_action( 'wp_ajax_wp_stream_notifications_export', array( 'WP_Stream_Notifications_Import_Export', 'export' ) );
		add_filter( 'pre_update_option_' . WP_Stream_Settings::KEY, array( 'WP_Stream_Notifications_Import_Export', 'import' ) );
	}

	public static function get_fields() {
		if ( empty( self::$fields ) ) {
			$fields = array(
				'notifications' => array(
					'title'  => esc_html__( 'Notifications', 'stream-notifications' ),
					'fields' => array(
						array(
							'name'    => 'role_access',
							'title'   => esc_html__( 'Role Access', 'stream-notifications' ),
							'type'    => 'multi_checkbox',
							'desc'    => esc_html__( 'Users from the selected roles above will have permission to view, create and edit Stream Notifications. However, only site Administrators can access Stream Notifications Settings.', 'stream-notifications' ),
							'choices' => WP_Stream_Settings::get_roles(),
							'default' => array( 'administrator' ),
						),
						array(
							'name'  => 'export_rules',
							'title' => esc_html__( 'Export Rules', 'stream' ),
							'type'  => 'link',
							'href'  => add_query_arg(
								array(
									'action'                     => 'wp_stream_notifications_export',
									'stream_notifications_nonce' => wp_create_nonce( 'stream-notifications-nonce' ),
								),
								admin_url( 'admin-ajax.php' )
							),
							'desc'    => esc_html__( 'Export all rules to a JSON file.', 'stream-notifications' ),
							'default' => 0,
						),
						array(
							'name'  => 'import_rules',
							'title' => esc_html__( 'Import Rules', 'stream-notifications' ),
							'type'  => 'file',
							'href'  => add_query_arg(
								array(
									'action'                     => 'wp_stream_notifications_import',
									'stream_notifications_nonce' => wp_create_nonce( 'stream-notifications-nonce' ),
								),
								admin_url( 'admin-ajax.php' )
							),
							'desc'    => esc_html__( 'Import rules from a JSON file.', 'stream-notifications' ),
							'default' => 0,
						),
					),
				),
			);

			self::$fields = apply_filters( 'wp_stream_notifications_options_fields', $fields );
		}
		return self::$fields;
	}

	/**
	 * Appends Notifications settings to Stream settings
	 *
	 * @filter wp_stream_options_fields
	 */
	public static function _register_settings( $stream_fields ) {
		return array_merge( $stream_fields, self::get_fields() );
	}

	/**
	 * Filter user caps to dynamically grant our view cap based on allowed roles
	 *
	 * @filter user_has_cap
	 *
	 * @param $allcaps
	 * @param $caps
	 * @param $args
	 * @param $user
	 *
	 * @return array
	 */
	public static function _filter_user_caps( $allcaps, $caps, $args, $user = null ) {
		$user = is_a( $user, 'WP_User' ) ? $user : wp_get_current_user();

		foreach ( $caps as $cap ) {
			if ( WP_Stream_Notifications::VIEW_CAP === $cap ) {
				foreach ( $user->roles as $role ) {
					if ( self::_role_can_access_notifications( $role ) ) {
						$allcaps[ $cap ] = true;
						break 2;
					}
				}
			}
		}

		return $allcaps;
	}

	/**
	 * Filter role caps to dynamically grant our view cap based on allowed roles
	 *
	 * @filter role_has_cap
	 *
	 * @param $allcaps
	 * @param $cap
	 * @param $role
	 *
	 * @return array
	 */
	public static function _filter_role_caps( $allcaps, $cap, $role ) {
		if ( WP_Stream_Notifications::VIEW_CAP === $cap && self::_role_can_access_notifications( $role ) ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	private static function _role_can_access_notifications( $role ) {
		if ( ! isset( WP_Stream_Settings::$options['notifications_role_access'] ) ) {
			WP_Stream_Settings::$options['notifications_role_access'] = array( 'administrator' );
		}

		if ( in_array( $role, WP_Stream_Settings::$options['notifications_role_access'] ) ) {
			return true;
		}

		return false;
	}

}
