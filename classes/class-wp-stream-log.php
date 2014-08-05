<?php

class WP_Stream_Log {

	/**
	 * Log buffer key/identifier
	 */
	const LOG_BUFFER_OPTION_KEY = 'wp_stream_log_buffer';

	/**
	 * Log handler
	 *
	 * @var \WP_Stream_Log
	 */
	public static $instance = null;

	/**
	 * Hold record logs in a temporary buffer so we can send them all to the API at once
	 *
	 * @var array
	 */
	public static $buffer = array();

	/**
	 * Total number of records to log per API call
	 *
	 * @var int
	 */
	public static $limit = 500;

	/**
	 * Previous Stream record ID, used for chaining same-session records
	 *
	 * @var int
	 */
	public $prev_record;

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

		self::$buffer = self::get_local_records();

		add_action( 'admin_init', array( __CLASS__, 'send_buffer' ), 99 );

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
	 * Retrieve records waiting to be logged
	 *
	 * @return array
	 */
	public static function get_local_records() {
		global $wpdb;

		$local_records     = array();
		$local_record_rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name LIKE %s", self::LOG_BUFFER_OPTION_KEY . '_%' ) );

		if ( ! $local_record_rows || empty( $local_record_rows ) ) {
			return $local_records;
		}

		foreach( $local_record_rows as $local_record_row ) {
			array_merge( $local_records, json_decode( $local_record_row ) );
		}

		return $local_records;
	}

	/**
	 * Save any records waiting to be logged in the local database
	 *
	 * @return void
	 */
	public static function save_local_records() {
		$grouped_records = array();

		for ( $i = 0; $i < count( self::$buffer ); $i += self::$limit ) {
			$grouped_records[] = array_slice( self::$buffer, $i, self::$limit );
		}

		foreach ( $grouped_records as $key => $grouped_record ) {
			update_option( self::LOG_BUFFER_OPTION_KEY . '_' . $key, json_encode( $grouped_record ) );
		}
	}

	/**
	 * Store the most recent data in the buffer
	 *
	 * @return void
	 */
	public static function send_buffer() {
		WP_Stream::$db->store( array_slice( self::$buffer, 0, self::$limit ) );

		if ( WP_Stream::$db->query_meta ) {
			self::$buffer = array_slice( self::$buffer, self::$limit );
		}

		self::save_local_records();
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

		$user  = new WP_User( $user_id );
		$roles = get_option( $wpdb->get_blog_prefix() . 'user_roles' );

		$visibility = 'publish';
		if ( self::is_record_excluded( $connector, $context, $action, $user ) ) {
			$visibility = 'private';
		}

		if ( ! isset( $args['author_meta'] ) ) {
			$args['author_meta'] = array(
				'user_email'      => $user->user_email,
				'display_name'    => ( defined( 'WP_CLI' ) && empty( $user->display_name ) ) ? 'WP-CLI' : $user->display_name,
				'user_login'      => $user->user_login,
				'user_role_label' => ! empty( $user->roles ) ? $roles[ $user->roles[0] ]['name'] : null,
				'agent'           => WP_Stream_Author::get_current_agent(),
			);

			if ( ( defined( 'WP_CLI' ) ) && function_exists( 'posix_getuid' ) ) {
				$uid       = posix_getuid();
				$user_info = posix_getpwuid( $uid );

				$args['author_meta']['system_user_id']   = $uid;
				$args['author_meta']['system_user_name'] = $user_info['name'];
			}
		}

		// Remove meta with null values from being logged
		$meta = array_filter(
			$args,
			function ( $var ) {
				return ! is_null( $var );
			}
		);

		// Get current time with milliseconds
		$iso_8601_extended_date = wp_stream_get_iso_8601_extended_date();

		$recordarr = array(
			'object_id'   => $object_id,
			'site_id'     => is_multisite() ? get_current_site()->id : 1,
			'blog_id'     => apply_filters( 'blog_id_logged', is_network_admin() ? 0 : get_current_blog_id() ),
			'author'      => $user_id,
			'author_role' => ! empty( $user->roles ) ? $user->roles[0] : null,
			'created'     => $iso_8601_extended_date,
			'visibility'  => $visibility,
			'summary'     => vsprintf( $message, $args ),
			'parent'      => self::$instance->prev_record,
			'connector'   => $connector,
			'context'     => $context,
			'action'      => $action,
			'stream_meta' => $meta,
			'ip'          => wp_stream_filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP ),
		);

		self::$buffer[] = $recordarr;
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
