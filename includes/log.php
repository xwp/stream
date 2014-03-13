<?php

class WP_Stream_Log {

	/**
	 * Log handler
	 *
	 * @var \WP_Stream_Log
	 */
	public static $instance = null;

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
	 * @param  array  $contexts  Contexts of the action
	 * @param  int    $user_id   User responsible for the action
	 *
	 * @internal param string $action Action performed (stream_action)
	 * @return int
	 */
	public function log( $connector, $message, $args, $object_id, $contexts, $user_id = null ) {
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		// Remove meta with null values from being logged
		$meta = array_filter(
			$args,
			function ( $var ) {
				return ! is_null( $var );
			}
		);

		$recordarr = array(
			'object_id' => $object_id,
			'author'    => $user_id,
			'created'   => current_time( 'mysql', 1 ),
			'summary'   => vsprintf( $message, $args ),
			'parent'    => self::$instance->prev_record,
			'connector' => $connector,
			'contexts'  => $contexts,
			'meta'      => $meta,
			'ip'        => wp_stream_filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP ),
		);

		$record_id = WP_Stream_DB::get_instance()->insert( $recordarr );

		return $record_id;
	}

}
