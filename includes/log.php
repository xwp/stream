<?php

class WP_Stream_Log {

	/**
	 * Log handler
	 * @var \WP_Stream_Log
	 */
	public static $instance = null;

	/**
	 * Previous Stream record ID, used for chaining same-session records
	 * @var int
	 */
	public $prev_stream;

	/**
	 * Load log handler class, filterable by extensions
	 * 
	 * @return void
	 */
	public static function load() {
		$log_handler    = apply_filters( 'wp_stream_log_handler', __CLASS__ );
		self::$instance = new $log_handler;
	}

	/**
	 * Log handler
	 * @param  string $message   sprintf-ready error message string
	 * @param  array  $args      sprintf (and extra) arguments to use
	 * @param  int    $object_id Target object id
	 * @param  string $action    Action performed (stream_action)
	 * @param  int    $user_id   User responsible for the action
	 * @param  array  $contexts  Contexts of the action
	 * @return void
	 */
	public function log( $connector, $message, $args, $object_id, $action, $user_id = null, array $contexts = array() ) {
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		// Allow extensions to define more contexts
		$contexts[] = $connector::$name;

		$arg_keys = array_keys( $args );
		foreach ( $arg_keys as $i => $key ) {
			$arg_keys[$i] = '_arg_' . $key;
		}
		$args = array_combine( $arg_keys, $args );

		$postarr = array(
			'post_type'   => 'stream',
			'post_status' => 'publish',
			'post_title'  => vsprintf( $message, $args ),
			'post_author' => $user_id,
			'post_parent' => self::$instance->prev_stream,
			'post_tax'    => array( // tax_input uses current_user_can which fails on user context!
				'stream_context' => $contexts,
				'stream_action'  => $action,
				),
			'post_meta'   => array_merge(
				$args,
				array(
					'_object_id'  => $object_id,
					'_ip_address' => filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP ),
					)
				),
			);

		$post_id = wp_insert_post(
			apply_filters(
				'wp_stream_post_array',
				$postarr
				)
			);

		if ( is_a( $post_id, 'WP_Error' ) ) {
			// TODO:: Log error
			do_action( 'wp_stream_post_insert_error', $post_id, $postarr );
		} else {
			self::$instance->prev_stream = $post_id;

			foreach ( $postarr['post_meta'] as $key => $vals ) {
				foreach ( (array) $vals as $val ) {
					add_post_meta( $post_id, $key, $val );
				}
			}

			foreach ( $postarr['post_tax'] as $key => $vals ) {
				wp_set_post_terms( $post_id, (array) $vals, $key );
			}

			do_action( 'wp_stream_post_inserted', $post_id, $postarr );
		}
	}
	
}