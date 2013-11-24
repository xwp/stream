<?php

abstract class X_Stream_Context {

	/**
	 * Name/slug of the context
	 * @var string
	 */
	static $name = null;

	/**
	 * Actions this context is hooked to
	 * @var array
	 */
	static $actions = array();

	/**
	 * Previous Stream entry in same request
	 * @var int
	 */
	static $prev_stream = null;

	/**
	 * Register required hooks
	 * @return array   Array of actions to hook into
	 */
	public static function register() {
		$class = get_called_class();
		// Register translated name
		$class::$name = $class::get_name();
		foreach ( $class::$actions as $action ) {
			add_action( $action, array( $class, 'callback' ), null, 5 );
		}

		add_filter( 'wp_stream_action_links_' . $class::$name, array( $class, 'action_links' ), 10, 3 );
	}

	public static function callback() {
		$action   = current_filter();
		$class    = get_called_class();
		$callback = array( $class, 'callback_' . $action );
		if ( is_callable( $callback ) ) {
			call_user_func_array( $callback, func_get_args() );
		}
	}

	public static function log( $message, $args, $object_id, $action, $user_id = null, array $contexts = array() ) {
		// DO SOMETHING
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		$class = get_called_class();

		// Allow extensions to define more contexts
		$contexts[] = $class::$name;

		// Store args as numbered meta fields
		$arg_idx = array();
		foreach ( $args as $i => $_arg ) {
			$arg_idx[$i] = "_arg_$i";
		}
		$args = array_combine( $arg_idx, $args );

		$postarr = array(
			'post_type'   => 'stream',
			'post_status' => 'publish',
			'post_title'  => vsprintf( $message, $args ),
			'post_author' => $user_id,
			'post_parent' => self::$prev_stream,
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
			self::$prev_stream = $post_id;

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

	public static function action_links( $links, $stream_id, $object_id ) {
		return $links;
	}

}