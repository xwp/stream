<?php
/**
 * Settings class for Stream Reports
 *
 * @author X-Team <x-team.com>
 * @author Shady Sharaf <shady@x-team.com>
 * @author Jaroslav Polakovič <dero@x-team.com>
 * @author Jonathan Bardo <jonathan.bardo@x-team.com>
 */
class WP_Stream_Reports_Settings {

	/**
	 * Contains the option fields for the settings
	 *
	 * @var array $fields
	 */
	public static $fields = array();

	/**
	 * Contains the array of user options for the plugin
	 *
	 * @var array $user_options
	 */
	private static $user_options;

	/**
	 * Holds the user option name (key)
	 */
	const OPTION_NAME = 'stream_reports_settings';

	/**
	 * Public constructor
	 */
	public static function load() {
		// User and role caps
		add_filter( 'user_has_cap', array( __CLASS__, '_filter_user_caps' ), 10, 4 );
		add_filter( 'role_has_cap', array( __CLASS__, '_filter_role_caps' ), 10, 3 );

		if ( WP_Stream_API::is_restricted() ) {
			return;
		}

		// Add Reports settings tab to Stream settings
		add_filter( 'wp_stream_settings_option_fields', array( __CLASS__, '_register_settings' ) );
	}

	public static function get_fields() {
		if ( empty( self::$fields ) ) {
			$fields = array();

			self::$fields = apply_filters( 'wp_stream_reports_option_fields', $fields );
		}

		return self::$fields;
	}

	/**
	 * Appends Reports settings to Stream settings
	 *
	 * @filter wp_stream_settings_option_fields
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
			if ( WP_Stream_Reports::VIEW_CAP === $cap ) {
				foreach ( $user->roles as $role ) {
					if ( self::_role_can_access( $role ) ) {
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
		if ( WP_Stream_Reports::VIEW_CAP === $cap && self::_role_can_access( $role ) ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	private static function _role_can_access( $role ) {
		// Default role if one is not set by default
		if ( ! isset( WP_Stream_Settings::$options['reports_role_access'] ) ) {
			WP_Stream_Settings::$options['reports_role_access'] = array( 'administrator' );
		}

		if ( in_array( $role, WP_Stream_Settings::$options['reports_role_access'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the settings have not been setup for this user
	 *
	 * @return boolean
	 */
	public static function is_first_visit() {
		if ( ! get_user_option( self::get_option_key() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get user option and store it in a static var for easy access
	 *
	 * @param null  $key
	 * @param array $default
	 *
	 * @return array
	 */
	public static function get_user_options( $key = null, $default = array() ) {
		if ( empty( self::$user_options ) ) {
			self::$user_options = get_user_option( self::get_option_key() );
		}

		if ( is_null( $key ) ) {
			// Return empty array if no user option is in DB
			$output = ( self::$user_options ) ?: array();
		} else {
			$output = isset( self::$user_options[ $key ] ) ? self::$user_options[ $key ] : $default;
		}

		return $output;
	}

	/**
	 * Handle option updating in the database
	 *
	 * @param string $key
	 * @param mixed $option
	 * @param bool  $redirect If the function must redirect and exit here
	 */
	public static function update_user_option( $key, $option ) {
		$user_options = self::get_user_options();

		if ( ! isset( $user_options[ $key ] ) ) {
			$user_options[ $key ] = array();
		}

		// Don't re-save if the value hasn't changed
		if ( $user_options[ $key ] != $option ) {
			$user_options[ $key ] = $option;
			$is_saved = update_user_option( get_current_user_id(), self::get_option_key(), $user_options );
		} else {
			$is_saved = true;
		}

		return $is_saved;
	}

	/**
	 * Handles saving during AJAX requests
	 *
	 * @param string $key
	 * @param mixed $option
	 */
	public static function ajax_update_user_option( $key, $option ) {
		check_ajax_referer( 'stream-reports-page', 'wp_stream_reports_nonce' );

		$is_saved = self::update_user_option( $key, $option );

		if ( $is_saved ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Updates the user option and redirects back to the main page if successful
	 *
	 * @param string $key
	 * @param mixed $option
	 */
	public static function update_user_option_and_redirect( $key, $option ) {
		$is_saved = self::update_user_option( $key, $option );

		if ( $is_saved ) {
			wp_safe_redirect(
				add_query_arg(
					array( 'page' => WP_Stream_Reports::REPORTS_PAGE_SLUG ),
					self_admin_url( 'admin.php' )
				)
			);

			exit;
		} else {
			wp_die( __( "Uh no! This wasn't suppose to happen :(", 'stream' ) );
		}
	}

	public static function get_option_key() {
		return self::OPTION_NAME;
	}

}
