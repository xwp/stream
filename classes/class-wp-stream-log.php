<?php

class WP_Stream_Log {

	/**
	 * Log handler
	 *
	 * @var \WP_Stream_Log
	 */
	public static $instance = null;

	/**
	 * Load log handler class, filterable by extensions
	 *
	 * @return void
	 */
	public static function load() {
		/**
		 * Filter allows developers to change log handler class
		 *
		 * @param  array   Current Class
		 * @return string  New Class for log handling
		 */
		$log_handler = apply_filters( 'wp_stream_log_handler', __CLASS__ );

		self::$instance = new $log_handler;
	}

	/**
	 * Return active instance of this class
	 *
	 * @return WP_Stream_Log
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

	/**
	 * Log handler
	 *
	 * @param         $connector
	 * @param  string $message   sprintf-ready error message string
	 * @param  array  $args      sprintf (and extra) arguments to use
	 * @param  int    $object_id Target object id
	 * @param  string $context   Context of the event
	 * @param  string $action    Action of the event
	 * @param  int    $user_id   User responsible for the event
	 *
	 * @return void
	 */
	public function log( $connector, $message, $args, $object_id, $context, $action, $user_id = null ) {
		global $wpdb;

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( is_null( $object_id ) ) {
			$object_id = 0;
		}

		$user       = new WP_User( $user_id );
		$roles      = get_option( $wpdb->get_blog_prefix() . 'user_roles' );
		$visibility = 'publish';

		if ( self::is_record_excluded( $connector, $context, $action, $user ) ) {
			$visibility = 'private';
		}

		if ( defined( 'WP_CLI' ) && empty( $user->display_name ) ) {
			$display_name = 'WP-CLI';
		} elseif ( ! empty( $user->display_name ) ) {
			$display_name = $user->display_name;
		} else {
			$display_name = '';
		}

		$author_meta = array(
			'user_email'      => (string) ! empty( $user->user_email ) ? $user->user_email : '',
			'display_name'    => (string) $display_name,
			'user_login'      => (string) ! empty( $user->user_login ) ? $user->user_login : '',
			'user_role_label' => (string) ! empty( $user->roles ) ? $roles[ $user->roles[0] ]['name'] : '',
			'agent'           => (string) WP_Stream_Author::get_current_agent(),
		);

		if ( ( defined( 'WP_CLI' ) ) && function_exists( 'posix_getuid' ) ) {
			$uid       = posix_getuid();
			$user_info = posix_getpwuid( $uid );

			$author_meta['system_user_id']   = (int) $uid;
			$author_meta['system_user_name'] = (string) $user_info['name'];
		}

		// Prevent any meta with null values from being logged
		$stream_meta = array_filter(
			$args,
			function ( $var ) {
				return ! is_null( $var );
			}
		);

		// All meta must be strings, so we will serialize any array meta values
		array_walk(
			$stream_meta,
			function( &$v ) {
				$v = (string) maybe_serialize( $v );
			}
		);

		// Get the current time in milliseconds
		$iso_8601_extended_date = wp_stream_get_iso_8601_extended_date();

		$recordarr = array(
			'object_id'   => (int) $object_id,
			'site_id'     => (int) is_multisite() ? get_current_site()->id : 1,
			'blog_id'     => (int) apply_filters( 'wp_stream_blog_id_logged', get_current_blog_id() ),
			'author'      => (int) $user_id,
			'author_role' => (string) ! empty( $user->roles ) ? $user->roles[0] : '',
			'author_meta' => (array) $author_meta,
			'created'     => (string) $iso_8601_extended_date,
			'visibility'  => (string) $visibility,
			'type'        => 'stream',
			'summary'     => (string) vsprintf( $message, $args ),
			'connector'   => (string) $connector,
			'context'     => (string) $context,
			'action'      => (string) $action,
			'stream_meta' => (array) $stream_meta,
			'ip'          => (string) wp_stream_filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP ),
		);

		WP_Stream::$db->store( array( $recordarr ) );
	}

	/**
	 * This function is use to check whether or not a record should be excluded from the log
	 *
	 * @param $connector string name of the connector being logged
	 * @param $context   string name of the context being logged
	 * @param $action    string name of the action being logged
	 * @param $user_id   int    id of the user being logged
	 * @param $ip        string ip address being logged
	 * @return bool
	 */
	public function is_record_excluded( $connector, $context, $action, $user = null, $ip = null ) {
		if ( is_null( $user ) ) {
			$user = wp_get_current_user();
		}

		if ( is_null( $ip ) ) {
			$ip = wp_stream_filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
		} else {
			$ip = wp_stream_filter_var( $ip, FILTER_VALIDATE_IP );
		}

		$user_role = isset( $user->roles[0] ) ? $user->roles[0] : null;

		$record = array(
			'connector'  => $connector,
			'context'    => $context,
			'action'     => $action,
			'author'     => $user->ID,
			'role'       => $user_role,
			'ip_address' => $ip,
		);

		$exclude_settings = isset( WP_Stream_Settings::$options['exclude_rules'] ) ? WP_Stream_Settings::$options['exclude_rules'] : array();

		if ( isset( $exclude_settings['exclude_row'] ) && ! empty( $exclude_settings['exclude_row'] ) ) {
			foreach ( $exclude_settings['exclude_row'] as $key => $value ) {
				// Prepare values
				$author_or_role = isset( $exclude_settings['author_or_role'][ $key ] ) ? $exclude_settings['author_or_role'][ $key ] : '';
				$connector      = isset( $exclude_settings['connector'][ $key ] ) ? $exclude_settings['connector'][ $key ] : '';
				$context        = isset( $exclude_settings['context'][ $key ] ) ? $exclude_settings['context'][ $key ] : '';
				$action         = isset( $exclude_settings['action'][ $key ] ) ? $exclude_settings['action'][ $key ] : '';
				$ip_address     = isset( $exclude_settings['ip_address'][ $key ] ) ? $exclude_settings['ip_address'][ $key ] : '';

				$exclude = array(
					'connector'  => ! empty( $connector ) ? $connector : null,
					'context'    => ! empty( $context ) ? $context : null,
					'action'     => ! empty( $action ) ? $action : null,
					'ip_address' => ! empty( $ip_address ) ? $ip_address : null,
					'author'     => is_numeric( $author_or_role ) ? $author_or_role : null,
					'role'       => ( ! empty( $author_or_role ) && ! is_numeric( $author_or_role ) ) ? $author_or_role : null,
				);

				$exclude_rules = array_filter( $exclude, 'strlen' );

				if ( ! empty( $exclude_rules ) ) {
					$excluded = true;

					foreach ( $exclude_rules as $exclude_key => $exclude_value ) {
						if ( $record[ $exclude_key ] !== $exclude_value ) {
							$excluded = false;
							break;
						}
					}

					if ( $excluded ) {
						return true;
					}
				}
			}
		}

		return false;
	}

}
